<?php

namespace App\Models;

use Database\Factories\ApplicationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ApplicationSetting extends Model
{
    /** @use HasFactory<ApplicationSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'business_name',
        'contact_email',
        'contact_phone',
        'business_address',
        'location_landmarks',
        'facebook_url',
        'messenger_url',
        'map_url',
        'company_history',
        'organization_chart',
        'default_payment_method',
        'promotion_voucher_validity_days',
        'updated_by',
    ];

    public static function current(): self
    {
        if (! self::tableAvailable()) {
            return new self(self::defaults());
        }

        return self::query()->first() ?? new self(self::defaults());
    }

    /** @param array<string, mixed> $attributes */
    public static function updateCurrent(array $attributes): self
    {
        $settings = self::query()->first() ?? new self;

        if (! $settings->exists) {
            $settings->setAttribute($settings->getKeyName(), 1);
        }

        $settings->fill($attributes);
        $settings->save();

        return $settings;
    }

    public static function tableAvailable(): bool
    {
        return Schema::hasTable((new self)->getTable());
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'business_name' => config('casa.business_name'),
            'contact_email' => null,
            'contact_phone' => null,
            'business_address' => 'Barangay Cuta East, Santa Teresita, Batangas, Philippines',
            'location_landmarks' => 'In front of Alfamart and PLDT; in the same building as BDO Network Bank.',
            'facebook_url' => 'https://www.facebook.com/61579320037378',
            'messenger_url' => 'https://m.me/61579320037378',
            'map_url' => 'https://www.google.com/maps/search/?api=1&query=Casa+Paraiso+Body+%26+Wellness+Spa%2C+Cuta+East%2C+Santa+Teresita%2C+Batangas',
            'company_history' => null,
            'organization_chart' => null,
            'default_payment_method' => Transaction::METHOD_CASH,
            'promotion_voucher_validity_days' => (int) config('casa.customer_rewards.default_validity_days', 90),
            'updated_by' => null,
        ];
    }

    protected function casts(): array
    {
        return [
            'organization_chart' => 'array',
            'promotion_voucher_validity_days' => 'integer',
        ];
    }

    /** @return array{owner?: array{title: string, description?: string|null}, admin?: array{title: string, description?: string|null}, team: array<int, array{title: string, description?: string|null}>} */
    public function publicOrganizationChart(): array
    {
        $chart = is_array($this->organization_chart) ? $this->organization_chart : [];
        $team = collect($chart['team'] ?? [])
            ->filter(static fn (mixed $role): bool => is_array($role) && filled($role['title'] ?? null))
            ->values()
            ->all();

        return array_filter([
            'owner' => is_array($chart['owner'] ?? null) && filled($chart['owner']['title'] ?? null) ? $chart['owner'] : null,
            'admin' => is_array($chart['admin'] ?? null) && filled($chart['admin']['title'] ?? null) ? $chart['admin'] : null,
            'team' => $team,
        ], static fn (mixed $value, string $key): bool => $key === 'team' || $value !== null, ARRAY_FILTER_USE_BOTH);
    }

    public function hasPublicCompanyProfile(): bool
    {
        $chart = $this->publicOrganizationChart();

        return filled($this->company_history)
            || isset($chart['owner'])
            || isset($chart['admin'])
            || $chart['team'] !== [];
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
