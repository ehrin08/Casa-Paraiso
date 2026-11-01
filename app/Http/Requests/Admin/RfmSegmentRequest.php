<?php

namespace App\Http\Requests\Admin;

use App\Models\Addon;
use App\Models\RfmSegment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RfmSegmentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isAdmin() ?? false; }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $segment = $this->route('segment');
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('rfm_segments', 'name')->ignore($segment instanceof RfmSegment ? $segment->id : null)],
            'description' => ['nullable', 'string', 'max:1000'],
            'addon_code' => ['nullable', 'string', Rule::in(Addon::query()->where('is_active', true)->pluck('code')->all())],
            'recommended_membership_tier_id' => ['nullable', 'integer', Rule::exists('membership_tiers', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ];

        foreach (array_keys(RfmSegment::METRICS) as $metric) {
            $rules["{$metric}_score_min"] = ['nullable', 'integer', 'between:1,5'];
            $rules["{$metric}_score_max"] = ['nullable', 'integer', 'between:1,5'];
        }

        return $rules;
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (RfmSegment::METRICS as $metric => $label) {
                    $min = $this->input("{$metric}_score_min");
                    $max = $this->input("{$metric}_score_max");

                    if (filled($min) && filled($max) && (int) $min > (int) $max) {
                        $validator->errors()->add("{$metric}_score_max", __('The highest :metric score must be at least the lowest score.', ['metric' => strtolower(__($label))]));
                    }
                }
            },
        ];
    }
}
