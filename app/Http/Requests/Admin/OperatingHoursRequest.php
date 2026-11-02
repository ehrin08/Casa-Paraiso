<?php

namespace App\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** The default opening and closing times. A closing time of 00:00 means midnight. */
class OperatingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'opens_at' => ['required', 'date_format:H:i', self::halfHour()],
            'closes_at' => ['required', 'date_format:H:i', self::halfHour()],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isEmpty() && ! self::endsAfterStart((string) $this->input('opens_at'), (string) $this->input('closes_at'))) {
                    $validator->errors()->add('closes_at', __('The closing time must be after the opening time.'));
                }
            },
        ];
    }

    /** Booking starts run on 30-minute steps, so hours do too. */
    public static function halfHour(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (preg_match('/^\d{2}:(00|30)$/', (string) $value) !== 1) {
                $fail(__('Choose a time on the hour or half hour.'));
            }
        };
    }

    /** Times stay within one business date; an end of 00:00 is midnight at the end of it. */
    public static function endsAfterStart(string $start, string $end): bool
    {
        $minutes = fn (string $time) => ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
        $endMinutes = $end === '00:00' ? 1440 : $minutes($end);

        return $endMinutes > $minutes($start);
    }
}
