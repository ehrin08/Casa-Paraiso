<?php

namespace App\Http\Requests\Admin;

use App\Models\Feedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeedbackIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'sentiment_label' => ['nullable', Rule::in(Feedback::SENTIMENT_LABELS)],
            'topic' => ['nullable', 'string', Rule::in(array_keys(config('sentiment.topics', [])))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), ['after_or_equal:date_from'])],
            'sort' => ['nullable', 'string'],
            'direction' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
