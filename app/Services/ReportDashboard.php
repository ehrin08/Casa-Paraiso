<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportDashboard
{
    public function __construct(
        private readonly RfmScorer $scorer,
        private readonly RfmRuleEngine $rules,
    ) {}

    public function forRange(ReportDateRange $range): array
    {
        $metrics = $this->metrics($range);
        $previous = $range->previous();
        $priorMetrics = $previous ? $this->metrics($previous) : null;

        foreach ($metrics as $key => $value) {
            $metrics[$key] = [
                'value' => $value,
                'change' => $priorMetrics && $priorMetrics[$key] > 0 ? ($value - $priorMetrics[$key]) / $priorMetrics[$key] * 100 : null,
                'has_previous' => $previous !== null,
            ];
        }

        $statuses = $this->appointments($range)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $sentiment = $range->apply(Feedback::query(), 'submitted_at')
            ->selectRaw('sentiment_label, COUNT(*) as total')->groupBy('sentiment_label')->get();
        $sentimentCounts = array_fill_keys(['positive', 'neutral', 'negative', 'unclassified'], 0);
        foreach ($sentiment as $row) {
            $key = in_array($row->sentiment_label, Feedback::SENTIMENT_LABELS, true) ? $row->sentiment_label : 'unclassified';
            $sentimentCounts[$key] += (int) $row->total;
        }

        return [
            'metrics' => $metrics,
            'comparison_label' => $previous?->label(),
            'revenue' => $this->revenue($range),
            'appointments' => $this->distribution(array_combine(Appointment::STATUSES, array_map(fn ($status) => (int) ($statuses[$status] ?? 0), Appointment::STATUSES))),
            'sentiment' => $this->distribution($sentimentCounts),
            'services' => $this->paid($range)
                ->leftJoin('services', 'services.id', '=', 'transactions.service_id')
                ->selectRaw("COALESCE(services.name, 'Unassigned service') as name, SUM(transactions.amount) as total")
                ->groupBy('transactions.service_id', 'services.name')->orderByDesc('total')->orderBy('name')->limit(5)->get(),
            'rfm' => $this->rfm($range),
            'cutoff' => $range->cutoff(),
        ];
    }

    private function paid(ReportDateRange $range): Builder
    {
        return $range->apply(Transaction::query()->where('payment_status', Transaction::PAYMENT_PAID)->whereNotNull('paid_at'), 'transactions.paid_at');
    }

    private function appointments(ReportDateRange $range): Builder
    {
        return $range->apply(Appointment::query(), DB::raw('COALESCE(scheduled_start_at, requested_start_at)'));
    }

    private function metrics(ReportDateRange $range): array
    {
        $visits = $this->appointments($range)->where('status', Appointment::STATUS_COMPLETED)
            ->selectRaw('COUNT(*) as completed, COUNT(DISTINCT customer_profile_id) as customers')->first();

        return [
            'revenue' => (float) $this->paid($range)->sum('amount'),
            'completed' => (int) $visits->completed,
            'customers' => (int) $visits->customers,
        ];
    }

    private function distribution(array $counts): array
    {
        $total = array_sum($counts);
        $offset = 0;
        $rows = [];
        foreach ($counts as $key => $count) {
            if ($key === 'unclassified' && $count === 0) {
                continue;
            }
            $percentage = $total ? $count / $total * 100 : 0;
            $rows[] = compact('key', 'count', 'percentage', 'offset');
            $offset += $percentage;
        }

        return ['total' => $total, 'rows' => $rows];
    }

    private function revenue(ReportDateRange $range): array
    {
        $daily = $this->paid($range)->selectRaw('DATE(paid_at) as day, SUM(amount) as total')->groupByRaw('DATE(paid_at)')->orderBy('day')->pluck('total', 'day');
        $from = $range->from ?? ($daily->isNotEmpty() ? CarbonImmutable::parse($daily->keys()->first(), 'Asia/Manila') : $range->cutoff()->startOfDay());
        $to = $range->to ?? ($daily->isNotEmpty() ? CarbonImmutable::parse($daily->keys()->last(), 'Asia/Manila')->max($range->cutoff()->startOfDay()) : $range->cutoff()->startOfDay());
        // One-sided future ranges have no elapsed revenue to plot.
        $to = $to->max($from);
        $days = (int) $from->diffInDays($to) + 1;
        $unit = $days < 8 ? 'day' : ($days <= 90 ? 'week' : 'month');
        $buckets = [];
        for ($start = $from; $start->lte($to); $start = $end->addDay()) {
            $end = match ($unit) {
                'day' => $start,
                'week' => $start->addDays(6)->min($to),
                default => $start->endOfMonth()->startOfDay()->min($to),
            };
            $buckets[] = ['from' => $start->toDateString(), 'to' => $end->toDateString(), 'label' => $unit === 'month' ? $start->format('M Y') : ($start->eq($end) ? $start->format('M j') : $start->format('M j').'–'.$end->format('j')), 'total' => 0.0];
        }
        // Fold daily aggregates into buckets without loading transaction records.
        $index = 0;
        foreach ($daily as $day => $amount) {
            while (isset($buckets[$index + 1]) && $day > $buckets[$index]['to']) {
                $index++;
            }
            $buckets[$index]['total'] += (float) $amount;
        }

        return ['unit' => $unit, 'buckets' => $buckets, 'max' => max(1, ...array_column($buckets, 'total'))];
    }

    private function rfm(ReportDateRange $range): array
    {
        $cutoff = $range->cutoff();
        $history = Transaction::query()->where('payment_status', Transaction::PAYMENT_PAID)->whereNotNull('paid_at')
            ->where('paid_at', '<=', $cutoff)
            ->whereHas('appointment', fn (Builder $query) => $query->where('status', Appointment::STATUS_COMPLETED)
                ->whereRaw('COALESCE(scheduled_start_at, requested_start_at) <= ?', [$cutoff]))
            ->selectRaw('customer_profile_id, MAX(paid_at) as last_paid, COUNT(*) as frequency, SUM(amount) as monetary')
            ->groupBy('customer_profile_id');
        $periodRevenue = $this->paid($range)->selectRaw('customer_profile_id, SUM(amount) as period_revenue')->groupBy('customer_profile_id')->pluck('period_revenue', 'customer_profile_id');
        $population = $history->get()->mapWithKeys(fn ($row) => [(int) $row->customer_profile_id => $this->scorer->metrics($row->last_paid, (int) $row->frequency, (float) $row->monetary, $cutoff)]);
        $scores = $this->scorer->scoreAll($population);
        $segments = $this->rules->rules();
        $customerIds = CustomerProfile::withTrashed()->where('customer_profiles.created_at', '<=', $cutoff)->pluck('id');
        $groups = [];
        foreach ($customerIds as $customerId) {
            $key = isset($scores[$customerId]) ? ($this->rules->match($scores[$customerId], $segments)?->id ?? 'unassigned') : 'unassigned';
            $groups[$key]['customers'] = ($groups[$key]['customers'] ?? 0) + 1;
            $groups[$key]['revenue'] = ($groups[$key]['revenue'] ?? 0) + (float) ($periodRevenue[$customerId] ?? 0);
        }
        $total = $customerIds->count();
        $rows = [];
        foreach ([...$segments->map(fn ($segment) => ['id' => $segment->id, 'key' => $segment->preset_key ?? 'segment-'.$segment->id, 'name' => $segment->name])->all(), ['id' => 'unassigned', 'key' => 'unassigned', 'name' => 'Unassigned']] as $segment) {
            $group = $groups[$segment['id']] ?? ['customers' => 0, 'revenue' => 0];
            $count = (int) $group['customers'];
            $rows[] = ['key' => $segment['key'], 'name' => $segment['name'], 'customers' => $count, 'percentage' => $total ? $count / $total * 100 : 0, 'average' => $count ? (float) $group['revenue'] / $count : 0];
        }

        return ['total' => $total, 'rows' => $rows];
    }
}
