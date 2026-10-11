<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Expression;

final class ReportDateRange
{
    public const OPTIONS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'last_30_days' => 'Last 30 days',
        'all' => 'All history',
        'custom' => 'Custom dates',
    ];

    public function __construct(
        public readonly string $key,
        public readonly ?CarbonImmutable $from,
        public readonly ?CarbonImmutable $to,
    ) {}

    public static function fromFilters(array $filters): self
    {
        $now = CarbonImmutable::now('Asia/Manila');

        if (filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null)) {
            return new self('custom',
                filled($filters['date_from'] ?? null) ? CarbonImmutable::parse($filters['date_from'], 'Asia/Manila')->startOfDay() : null,
                filled($filters['date_to'] ?? null) ? CarbonImmutable::parse($filters['date_to'], 'Asia/Manila')->startOfDay() : null,
            );
        }

        return match ($filters['range'] ?? 'this_month') {
            'all' => new self('all', null, null),
            'last_month' => new self('last_month', $now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()->startOfDay()),
            'last_30_days' => new self('last_30_days', $now->subDays(29)->startOfDay(), $now->startOfDay()),
            'custom' => new self('custom', $now->startOfMonth(), $now->endOfMonth()->startOfDay()),
            default => new self('this_month', $now->startOfMonth(), $now->endOfMonth()->startOfDay()),
        };
    }

    public function apply(Builder $query, string|Expression $column): Builder
    {
        return $query
            ->when($this->from, fn (Builder $query) => $query->where($column, '>=', $this->from))
            ->when($this->to, fn (Builder $query) => $query->where($column, '<', $this->to->addDay()));
    }

    public function previous(): ?self
    {
        if (! $this->from || ! $this->to) {
            return null;
        }

        if ($this->from->day === 1 && $this->to->toDateString() === $this->from->endOfMonth()->toDateString()) {
            $from = $this->from->subMonthNoOverflow()->startOfMonth();

            return new self('custom', $from, $from->endOfMonth()->startOfDay());
        }

        $days = (int) $this->from->diffInDays($this->to) + 1;

        return new self('custom', $this->from->subDays($days), $this->from->subDay());
    }

    public function cutoff(): CarbonImmutable
    {
        $now = CarbonImmutable::now('Asia/Manila');

        return $this->to ? $now->min($this->to->endOfDay()) : $now;
    }

    public function query(): array
    {
        return array_filter([
            'range' => $this->key,
            'date_from' => $this->key === 'custom' ? $this->from?->toDateString() : null,
            'date_to' => $this->key === 'custom' ? $this->to?->toDateString() : null,
        ], fn ($value) => $value !== null);
    }

    public function label(): string
    {
        if (! $this->from && ! $this->to) {
            return __('All history');
        }

        return ($this->from?->format('M j, Y') ?? __('Beginning')).' – '.($this->to?->format('M j, Y') ?? __('Present'));
    }
}
