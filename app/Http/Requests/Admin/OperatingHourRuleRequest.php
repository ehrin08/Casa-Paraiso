<?php

namespace App\Http\Requests\Admin;

use App\Models\OperatingHourRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OperatingHourRuleRequest extends FormRequest
{
    public const END_SAME_DAY = 'same_day';

    public const END_UNTIL = 'until';

    public const END_INDEFINITE = 'indefinite';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $today = now(config('casa.business_hours.timezone', 'Asia/Manila'))->toDateString();
        $timed = Rule::requiredIf(fn () => $this->input('type') !== OperatingHourRule::TYPE_CLOSED);

        return [
            'type' => ['required', Rule::in(OperatingHourRule::TYPES)],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'end_mode' => ['required', Rule::in([self::END_SAME_DAY, self::END_UNTIL, self::END_INDEFINITE])],
            'ends_on' => ['nullable', 'required_if:end_mode,'.self::END_UNTIL, 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'start_time' => ['nullable', $timed, 'date_format:H:i', OperatingHoursRequest::halfHour()],
            'end_time' => ['nullable', $timed, 'date_format:H:i', OperatingHoursRequest::halfHour()],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_on.after_or_equal' => __('Choose today or a later date.'),
            'ends_on.required_if' => __('Choose the last date.'),
            'ends_on.after_or_equal' => __('The last date cannot be before the first date.'),
            'start_time.required' => __('Choose a start time.'),
            'end_time.required' => __('Choose an end time.'),
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('type') === OperatingHourRule::TYPE_CLOSED) {
                    return;
                }

                if (! OperatingHoursRequest::endsAfterStart((string) $this->input('start_time'), (string) $this->input('end_time'))) {
                    $validator->errors()->add('end_time', __('The end time must be after the start time.'));

                    return;
                }

                $overlapping = $this->input('type') === OperatingHourRule::TYPE_SPECIAL_HOURS && OperatingHourRule::query()
                    ->where('type', OperatingHourRule::TYPE_SPECIAL_HOURS)
                    ->covering($this->startsOn(), $this->endsOn() ?? '9999-12-31')
                    ->exists();

                if ($overlapping) {
                    $validator->errors()->add('starts_on', __('Special hours are already set on some of these dates. End or remove those first.'));
                }
            },
        ];
    }

    public function startsOn(): string
    {
        return (string) $this->input('starts_on');
    }

    /** The last covered date, or null when the rule lasts until further notice. */
    public function endsOn(): ?string
    {
        return match ($this->input('end_mode')) {
            self::END_UNTIL => (string) $this->input('ends_on'),
            self::END_INDEFINITE => null,
            default => $this->startsOn(),
        };
    }

    /** @return array<string, mixed> */
    public function ruleAttributes(): array
    {
        $timed = $this->input('type') !== OperatingHourRule::TYPE_CLOSED;

        return [
            'type' => $this->input('type'),
            'starts_on' => $this->startsOn(),
            'ends_on' => $this->endsOn(),
            'start_time' => $timed ? $this->input('start_time') : null,
            'end_time' => $timed ? $this->input('end_time') : null,
            'reason' => $this->input('reason'),
        ];
    }
}
