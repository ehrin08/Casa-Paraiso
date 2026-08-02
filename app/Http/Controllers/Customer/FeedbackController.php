<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Concerns\HandlesIndexSorting;
use App\Http\Controllers\Controller;
use App\Http\Requests\FeedbackRequest;
use App\Models\Appointment;
use App\Models\Feedback;
use App\Services\HybridSentimentClassifier;
use App\Services\FeedbackSentimentUpdater;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    use HandlesIndexSorting;

    public function index(Request $request): View
    {
        $customerProfile = $request->user()->customerProfile;
        $sentiment = (string) $request->query('sentiment_label');
        $search = trim((string) $request->query('q'));
        $sorts = [
            'service' => 'feedback_services.name',
            'rating' => 'feedback.rating',
            'sentiment' => 'feedback.sentiment_label',
            'submitted' => 'feedback.submitted_at',
        ];
        $sort = $this->indexSort($request, $sorts, 'submitted');
        $direction = $this->indexDirection($request, 'desc');

        $completedAppointments = Appointment::query()
            ->with(['service', 'feedback'])
            ->where('customer_profile_id', $customerProfile?->id ?? 0)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->latest('completed_at')
            ->get();

        $appointments = $completedAppointments
            ->filter(fn (Appointment $appointment) => ! $appointment->feedback)
            ->values();

        $feedback = Feedback::query()
            ->with(['service', 'appointment'])
            ->leftJoin('services as feedback_services', 'feedback_services.id', '=', 'feedback.service_id')
            ->select('feedback.*')
            ->where('customer_profile_id', $customerProfile?->id ?? 0)
            ->when(in_array($sentiment, Feedback::SENTIMENT_LABELS, true), fn ($query) => $query->where('feedback.sentiment_label', $sentiment))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('feedback_services.name', 'like', "%{$search}%")
                    ->orWhere('feedback.comment', 'like', "%{$search}%");
            }))
            ->orderBy($sorts[$sort], $direction)
            ->orderByDesc('feedback.submitted_at')
            ->paginate((int) config('casa.pagination.per_page', 15))
            ->withQueryString();

        return view('customer.feedback.index', [
            'completedAppointments' => $completedAppointments,
            'feedback' => $feedback,
            'sentiment' => $sentiment,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'appointments' => $appointments,
            'selectedAppointmentId' => $request->integer('appointment_id') ?: null,
        ]);
    }

    public function create(Request $request): View
    {
        $customerProfile = $request->user()->customerProfile;
        $appointments = Appointment::query()
            ->with(['service', 'feedback'])
            ->where('customer_profile_id', $customerProfile?->id ?? 0)
            ->where('status', Appointment::STATUS_COMPLETED)
            ->whereDoesntHave('feedback')
            ->latest('completed_at')
            ->get();

        $selectedAppointmentId = $request->integer('appointment_id') ?: null;

        return view('customer.feedback.create', [
            'appointments' => $appointments,
            'selectedAppointmentId' => $selectedAppointmentId,
        ]);
    }

    public function store(FeedbackRequest $request, HybridSentimentClassifier $classifier, FeedbackSentimentUpdater $updater): RedirectResponse
    {
        $data = $request->validated();
        $customerProfile = $request->user()->customerProfile;
        $appointment = Appointment::query()
            ->where('customer_profile_id', $customerProfile?->id ?? 0)
            ->whereKey($data['appointment_id'])
            ->firstOrFail();

        if ($appointment->status !== Appointment::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['appointment_id' => __('Feedback is available only for completed appointments.')]);
        }

        $sentiment = $classifier->classify((int) $data['rating'], $data['comment'] ?? null);

        $feedback = Feedback::query()->firstOrCreate([
            'appointment_id' => $appointment->id,
        ], [
            'customer_profile_id' => $customerProfile->id,
            'service_id' => $appointment->service_id,
            'rating' => $data['rating'],
            'comment' => $data['comment'] ?? null,
            'sentiment_label' => $sentiment['label'],
            'sentiment_score' => $sentiment['score'],
            'submitted_at' => now(),
        ]);

        if (! $feedback->wasRecentlyCreated) {
            throw ValidationException::withMessages(['appointment_id' => __('Feedback was already submitted for this appointment.')]);
        }

        $updater->persist($feedback, $sentiment);

        return redirect()
            ->route('customer.feedback.index')
            ->with('status', 'feedback-submitted');
    }
}
