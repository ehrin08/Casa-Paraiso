<?php

namespace App\Http\Requests\Admin;

use App\Models\Transaction;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplicationSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'business_address' => ['nullable', 'string', 'max:1000'],
            'location_landmarks' => ['nullable', 'string', 'max:500'],
            'facebook_url' => ['nullable', 'url:https', 'max:2048', $this->urlHostRule(['facebook.com', 'www.facebook.com'])],
            'messenger_url' => ['nullable', 'url:https', 'max:2048', $this->urlHostRule(['m.me', 'www.m.me'])],
            'map_url' => ['nullable', 'url:https', 'max:2048', $this->urlHostRule(['google.com', 'www.google.com', 'maps.google.com'])],
            'default_payment_method' => ['required', Rule::in(Transaction::PAYMENT_METHODS)],
        ];
    }

    /** @param array<int, string> $hosts */
    private function urlHostRule(array $hosts): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($hosts): void {
            if (blank($value)) {
                return;
            }

            if (! in_array(strtolower((string) parse_url((string) $value, PHP_URL_HOST)), $hosts, true)) {
                $fail('The '.$attribute.' must use an approved service domain.');
            }
        };
    }
}
