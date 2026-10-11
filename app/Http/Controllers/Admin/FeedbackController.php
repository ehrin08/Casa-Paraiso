<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeedbackIndexRequest;
use App\Models\CustomerProfile;
use App\Models\Feedback;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    use HandlesIndexSorting;

    public function index(FeedbackIndexRequest $request): View
    {
        $validated = $request->validated();
        $sentiment = (string) ($validated['sentiment_label'] ?? '');
        $search = trim((string) ($validated['q'] ?? ''));
        $dateFrom = (string) ($validated['date_from'] ?? '');
        $dateTo = (string) ($validated['date_to'] ?? '');
        $serviceId = (int) ($validated['service_id'] ?? 0) ?: null;
        $topic = (string) ($validated['topic'] ?? '');
        $sorts = [
            'customer' => CustomerProfile::displayNameSql('feedback_customers', 'feedback_customer_profiles'),
            'service' => 'feedback_services.name',
            'rating' => 'feedback.rating',
            'sentiment' => 'feedback.sentiment_label',
            'submitted' => 'feedback.submitted_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'submitted');
        $direction = $this->indexDirection($request, 'desc');

        $query = Feedback::query()
            ->leftJoin('customer_profiles as feedback_customer_profiles', 'feedback_customer_profiles.id', '=', 'feedback.customer_profile_id')
            ->leftJoin('users as feedback_customers', 'feedback_customers.id', '=', 'feedback_customer_profiles.user_id')
            ->leftJoin('services as feedback_services', 'feedback_services.id', '=', 'feedback.service_id')
            ->select('feedback.*')
            ->when($sentiment !== '', fn (Builder $query) => $query->where('feedback.sentiment_label', $sentiment))
            ->when($serviceId, fn (Builder $query) => $query->where('feedback.service_id', $serviceId))
            ->when($topic !== '', fn (Builder $query) => $query->whereHas('topics', fn (Builder $topics) => $topics->where('topic_key', $topic)))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->whereHas('customerProfile', fn (Builder $customers) => $customers->search($search))
                    ->orWhere('feedback_services.name', 'like', "%{$search}%")
                    ->orWhere('feedback.comment', 'like', "%{$search}%");
            }));

        if ($dateFrom !== '') {
            $query->where('feedback.submitted_at', '>=', CarbonImmutable::parse($dateFrom, 'Asia/Manila')->startOfDay()->timezone(config('app.timezone')));
        }

        if ($dateTo !== '') {
            $query->where('feedback.submitted_at', '<', CarbonImmutable::parse($dateTo, 'Asia/Manila')->addDay()->startOfDay()->timezone(config('app.timezone')));
        }

        $feedback = (clone $query)
            ->with(['customerProfile.user', 'service'])
            ->orderBy($sort === 'customer' ? DB::raw($sorts[$sort]) : $sorts[$sort], $direction)
            ->orderByDesc('feedback.submitted_at')
            ->orderByDesc('feedback.id')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();
        $summary = $this->sentimentCounts((clone $query)->select([]))->toBase()->first();
        $serviceBreakdown = $this->sentimentCounts((clone $query)->select('feedback.service_id', 'feedback_services.name as service_name'))
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('feedback.service_id', 'feedback_services.name')
            ->orderByDesc('total')
            ->orderBy('service_name')
            ->orderBy('feedback.service_id')
            ->toBase()->get();
        $topicBreakdown = DB::table('feedback_topics')
            ->whereIn('feedback_id', (clone $query)->select('feedback.id')->toBase())
            ->select('topic_key')
            ->selectRaw('COUNT(*) AS total, COUNT(CASE WHEN polarity = ? THEN 1 END) AS positive', ['positive'])
            ->groupBy('topic_key')
            ->orderByDesc('total')
            ->orderBy('topic_key')
            ->get();

        return view('admin.feedback.index', [
            'feedback' => $feedback,
            'sentiment' => $sentiment,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'summary' => ['positive' => (int) $summary->positive, 'neutral' => (int) $summary->neutral, 'negative' => (int) $summary->negative],
            'serviceBreakdown' => $serviceBreakdown,
            'topicBreakdown' => $topicBreakdown,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'serviceId' => $serviceId,
            'topic' => $topic,
            'services' => Service::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function sentimentCounts(Builder $query): Builder
    {
        return $query
            ->selectRaw('COUNT(CASE WHEN feedback.sentiment_label = ? THEN 1 END) AS positive', [Feedback::SENTIMENT_POSITIVE])
            ->selectRaw('COUNT(CASE WHEN feedback.sentiment_label = ? THEN 1 END) AS neutral', [Feedback::SENTIMENT_NEUTRAL])
            ->selectRaw('COUNT(CASE WHEN feedback.sentiment_label = ? THEN 1 END) AS negative', [Feedback::SENTIMENT_NEGATIVE]);
    }

    public function show(Feedback $feedback): View
    {
        $feedback->load(['customerProfile.user', 'service', 'appointment', 'topics', 'sentimentRuns', 'annotations']);

        return view('admin.feedback.show', [
            'feedback' => $feedback,
        ]);
    }

    public function review(Request $request, Feedback $feedback): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', Rule::in(Feedback::SENTIMENT_LABELS)],
            'language' => ['required', Rule::in(['English', 'Tagalog', 'Taglish', 'mixed'])],
            'topics' => ['nullable', 'array', 'max:6'],
            'topics.*' => ['string', Rule::in(array_keys(config('sentiment.topics', [])))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->applyReview($feedback, $request->user()->id, $data);

        return redirect()->route('admin.feedback.show', $feedback)->with('status', 'feedback-reviewed');
    }

    private function applyReview(Feedback $feedback, int $reviewerId, array $data): void
    {
        DB::transaction(function () use ($feedback, $reviewerId, $data): void {
            $topics = collect($data['topics'] ?? [])->map(fn (string $key): array => ['key' => $key, 'polarity' => $data['label'] === Feedback::SENTIMENT_NEGATIVE ? 'negative' : 'positive', 'matched_terms' => ['reviewed']])->all();
            $feedback->annotations()->create(['reviewer_id' => $reviewerId, 'label' => $data['label'], 'language' => $data['language'], 'topics' => $data['topics'] ?? [], 'status' => 'adjudicated', 'notes' => $data['notes'] ?? null, 'adjudicated_at' => now()]);
            $feedback->forceFill(['sentiment_label' => $data['label'], 'sentiment_score' => match ($data['label']) { Feedback::SENTIMENT_POSITIVE => 1.0, Feedback::SENTIMENT_NEGATIVE => -1.0, default => 0.0 }, 'sentiment_source' => 'reviewed', 'sentiment_confidence' => 1.0, 'sentiment_evidence' => array_merge($feedback->sentiment_evidence ?? [], ['reviewed' => true])])->save();
            $feedback->topics()->delete();
            $feedback->topics()->createMany($topics);
            $feedback->sentimentRuns()->create(['source' => 'reviewed', 'classifier_version' => 'human-review-v1', 'label' => $data['label'], 'score' => $feedback->sentiment_score, 'confidence' => 1.0, 'evidence' => ['language' => $data['language'], 'notes' => $data['notes'] ?? null], 'is_authoritative' => true]);
        });
    }
}
