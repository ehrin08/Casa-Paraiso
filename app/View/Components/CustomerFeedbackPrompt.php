<?php

namespace App\View\Components;

use App\Models\Appointment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Opens the feedback form for a customer's completed visits until they submit or choose Later.
 */
class CustomerFeedbackPrompt extends Component
{
    public const MODAL_NAME = 'customer-feedback-prompt';

    public const DISMISSED_SESSION_KEY = 'customer_feedback_prompt_dismissed';

    /** @var Collection<int, Appointment>|null */
    private ?Collection $appointments = null;

    public function shouldRender(): bool
    {
        $user = Auth::user();
        $recoveringModal = (string) old('_modal');

        if (! $user?->isCustomer() || ! $user->customerProfile
            || session()->has(self::DISMISSED_SESSION_KEY)
            || request()->routeIs('customer.feedback.*', 'customer.appointments.create')
            || ($recoveringModal !== '' && $recoveringModal !== self::MODAL_NAME)
            || (session()->has('errors') && $recoveringModal !== self::MODAL_NAME)) {
            return false;
        }

        return $this->appointments()->isNotEmpty();
    }

    public function render(): View
    {
        $appointments = $this->appointments();

        return view('components.customer-feedback-prompt', [
            'modalName' => self::MODAL_NAME,
            'appointments' => $appointments,
            'selectedAppointmentId' => $appointments->first()?->id,
        ]);
    }

    /**
     * @return Collection<int, Appointment>
     */
    private function appointments(): Collection
    {
        return $this->appointments ??= Appointment::query()
            ->with('service')
            ->awaitingFeedback((int) Auth::user()->customerProfile->id)
            ->get();
    }
}
