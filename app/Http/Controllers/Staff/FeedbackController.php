<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $staffProfile = $request->user()->staffProfile;
        $sentiment = (string) $request->query('sentiment_label');
        $search = trim((string) $request->query('q'));
        $sorts = [
            'customer' => 'feedback_customers.name',
            'service' => 'feedback_services.name',
            'rating' => 'feedback.rating',
            'sentiment' => 'feedback.sentiment_label',
            'submitted' => 'feedback.submitted_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'submitted');
        $direction = $this->indexDirection($request, 'desc');

        $feedback = Feedback::query()
            ->with(['customerProfile.user', 'service', 'appointment', 'topics', 'sentimentRuns', 'annotations'])
            ->leftJoin('customer_profiles as feedback_customer_profiles', 'feedback_customer_profiles.id', '=', 'feedback.customer_profile_id')
            ->leftJoin('users as feedback_customers', 'feedback_customers.id', '=', 'feedback_customer_profiles.user_id')
            ->leftJoin('services as feedback_services', 'feedback_services.id', '=', 'feedback.service_id')
            ->select('feedback.*')
            ->whereHas('appointment', fn ($query) => $query->where('staff_profile_id', $staffProfile?->id ?? 0))
            ->when(in_array($sentiment, Feedback::SENTIMENT_LABELS, true), fn ($query) => $query->where('feedback.sentiment_label', $sentiment))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('feedback_customers.name', 'like', "%{$search}%")
                    ->orWhere('feedback_services.name', 'like', "%{$search}%")
                    ->orWhere('feedback.comment', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('feedback.submitted_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('staff.feedback.index', [
            'feedback' => $feedback,
            'sentiment' => $sentiment,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    public function show(Request $request, Feedback $feedback): View
    {
        $feedback->load(['customerProfile.user', 'service', 'appointment', 'topics', 'sentimentRuns', 'annotations']);

        abort_unless((int) $feedback->appointment?->staff_profile_id === (int) ($request->user()->staffProfile?->id ?? 0), 403);

        return view('staff.feedback.show', [
            'feedback' => $feedback,
        ]);
    }
}
