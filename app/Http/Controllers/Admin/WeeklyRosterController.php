<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\StaffScheduleConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RosterBulkRequest;
use App\Http\Requests\Admin\RosterIndexRequest;
use App\Http\Requests\Admin\RosterShiftRequest;
use App\Http\Requests\Admin\WeeklyRosterRequest;
use App\Models\StaffScheduleShift;
use App\Models\StaffScheduleWeek;
use App\Services\TherapistSchedulePage;
use App\Services\WeeklyRoster;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WeeklyRosterController extends Controller
{
    public function __construct(private readonly WeeklyRoster $roster, private readonly TherapistSchedulePage $page) {}

    public function index(RosterIndexRequest $request): View
    {
        return view('admin.staff-schedules.index', $this->page->build($request->validated()));
    }

    public function show(RosterIndexRequest $request): JsonResponse
    {
        return response()->json($this->roster->payload($request->validated('week') ?? now('Asia/Manila')->toDateString()));
    }

    public function startDraft(WeeklyRosterRequest $request): RedirectResponse|JsonResponse
    {
        return $this->mutate($request, fn () => $this->roster->startDraft($request->validated('week'), (int) $request->validated('expected_revision')),
            __('Draft started from the complete team schedule.'));
    }

    public function copyReview(RosterIndexRequest $request): View|RedirectResponse
    {
        try {
            $review = $this->roster->copyReview($request->validated('week') ?? now('Asia/Manila')->toDateString());
        } catch (ValidationException $exception) {
            return $this->reviewFailure($request, $request->validated('week') ?? now('Asia/Manila')->toDateString(), $exception);
        }

        return $this->editor($request, 'copy', $review);
    }

    public function copy(WeeklyRosterRequest $request): RedirectResponse|JsonResponse
    {
        return $this->mutate($request, fn () => $this->roster->copyPrevious($request->validated('week'),
            (int) $request->validated('expected_revision'), $request->validated('source_signature')), __('Source hours copied to the draft.'));
    }

    public function createShift(RosterIndexRequest $request, StaffScheduleWeek $scheduleWeek): View
    {
        return $this->editor($request, 'shift', $this->draftState($scheduleWeek), ['shift' => null]);
    }

    public function editShift(RosterIndexRequest $request, StaffScheduleWeek $scheduleWeek, StaffScheduleShift $shift): View
    {
        abort_unless($shift->staff_schedule_week_id === $scheduleWeek->id && $shift->version === StaffScheduleShift::VERSION_DRAFT, 404);

        return $this->editor($request, 'shift', $this->draftState($scheduleWeek), ['shift' => $shift->load('staffProfile.user')]);
    }

    public function storeShift(RosterShiftRequest $request, StaffScheduleWeek $scheduleWeek): RedirectResponse|JsonResponse
    {
        return $this->mutate($request, fn () => $this->roster->saveShift($scheduleWeek, $request->shiftData(),
            (int) $request->validated('expected_revision')), __('Draft shift saved.'));
    }

    public function updateShift(RosterShiftRequest $request, StaffScheduleWeek $scheduleWeek, int $shift): RedirectResponse|JsonResponse
    {
        return $this->mutate($request, fn () => $this->roster->saveShift($scheduleWeek, $request->shiftData(),
            (int) $request->validated('expected_revision'), $shift), __('Draft shift updated.'));
    }

    public function destroyShift(WeeklyRosterRequest $request, StaffScheduleWeek $scheduleWeek, int $shift): RedirectResponse|JsonResponse
    {
        return $this->mutate($request, fn () => $this->roster->deleteShift($scheduleWeek, $shift,
            (int) $request->validated('expected_revision')), __('Draft shift removed.'));
    }

    public function bulkEdit(RosterIndexRequest $request, StaffScheduleWeek $scheduleWeek): View
    {
        return $this->editor($request, 'bulk', $this->draftState($scheduleWeek));
    }

    public function bulkReview(RosterBulkRequest $request, StaffScheduleWeek $scheduleWeek): View|RedirectResponse
    {
        try {
            $review = $this->roster->bulkReview($scheduleWeek, $request->bulkData(), (int) $request->validated('expected_revision'));
        } catch (ValidationException $exception) {
            return redirect($request->recoveryUrl())->withErrors($exception->errors())->withInput();
        }

        return $this->editor($request, 'bulk-review', $review, ['bulkData' => $request->bulkData()]);
    }

    public function bulkReplace(RosterBulkRequest $request, StaffScheduleWeek $scheduleWeek): RedirectResponse|JsonResponse
    {
        return $this->mutate($request, fn () => $this->roster->bulkReplace($scheduleWeek, $request->bulkData(),
            (int) $request->validated('expected_revision')), __('Selected days replaced in the draft.'));
    }

    public function publishReview(RosterIndexRequest $request, StaffScheduleWeek $scheduleWeek): View|RedirectResponse
    {
        try {
            $review = $this->roster->publishReview($scheduleWeek);
        } catch (ValidationException $exception) {
            return $this->reviewFailure($request, $scheduleWeek->week_start_date->toDateString(), $exception);
        }

        return $this->editor($request, 'publish', $review);
    }

    private function reviewFailure(RosterIndexRequest $request, string $week, ValidationException $exception): RedirectResponse
    {
        return redirect()->route('admin.staff-roster.index', [
            ...Arr::only($request->validated(), ['q', 'staff_profile_id', 'page']),
            'week' => $this->roster->weekStart($week)->toDateString(), 'view' => 'draft',
        ])->withErrors($exception->errors());
    }

    public function publish(WeeklyRosterRequest $request, StaffScheduleWeek $scheduleWeek): RedirectResponse|JsonResponse
    {
        return $this->mutate($request, fn () => $this->roster->publish($scheduleWeek, $request->user()->id,
            (int) $request->validated('expected_revision')), __('Team schedule published. These hours now drive booking availability.'));
    }

    private function draftState(StaffScheduleWeek $week): array
    {
        $state = $this->roster->snapshot($week->week_start_date);
        abort_unless($state['editable'] && $state['has_draft'], 403, __('Start an editable draft from Therapist schedules. Past weeks are view-only.'));

        return $state;
    }

    private function editor(Request $request, string $editor, array $state, array $extra = []): View
    {
        $filters = $request instanceof WeeklyRosterRequest ? $request->context() : $request->validated();
        $filters['week'] = $state['start']->toDateString();
        $data = $this->page->build($filters, $state);
        $data['reviewNames'] = $this->page->reviewNames($state['changes'] ?? collect());
        $data['editor'] = $editor;
        $data['editorTitle'] = match ($editor) {
            'shift' => isset($extra['shift']) ? __('Edit shift') : __('Add shift'),
            'bulk' => __('Apply hours'),
            'bulk-review' => __('Review selected days'),
            'copy' => __('Copy a published week'),
            'publish' => __('Review team publication'),
        };
        $partial = $request->boolean('modal') && $request->header('X-Casa-Panel') === '1';

        return view($partial ? 'admin.staff-schedules.partials.editor' : 'admin.staff-schedules.editor', [...$data, ...$extra]);
    }

    private function mutate(WeeklyRosterRequest $request, Closure $callback, string $message): RedirectResponse|JsonResponse
    {
        try {
            $callback();
        } catch (StaffScheduleConflictException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('Publishing would leave confirmed appointments outside therapist hours.'), 'conflicts' => $exception->conflicts], 422);
            }

            return redirect($request->recoveryUrl())->withErrors(['roster' => __('Publication blocked. Resolve the affected confirmed bookings, then review again.')])->withInput();
        } catch (ValidationException $exception) {
            if ($request->expectsJson()) {
                throw $exception;
            }

            return redirect($request->recoveryUrl())->withErrors($exception->errors())->withInput();
        }
        $context = $request->context();
        $context['week'] = $this->roster->weekStart($context['week'])->toDateString();
        if ($request->expectsJson()) {
            return response()->json($this->roster->payload($context['week']));
        }

        return redirect()->route('admin.staff-roster.index', $context)->with('roster_status', $message);
    }
}
