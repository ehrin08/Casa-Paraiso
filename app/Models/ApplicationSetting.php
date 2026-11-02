<?php

namespace App\Models;

use Database\Factories\ApplicationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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
        // Retired hand-typed chart, kept read-only as a backup; the public chart now comes from staff accounts.
        'organization_chart',
        'default_payment_method',
        'promotion_voucher_validity_days',
        'gift_voucher_denominations',
        'gift_voucher_default_expiry_months',
        'gift_voucher_combinable',
        // Default opening and closing times; dated changes live in operating_hour_rules.
        'opens_at',
        'closes_at',
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
        // A first save from a single form (such as the default hours) still needs the other required columns.
        $settings = self::query()->first() ?? new self(self::defaults());

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
            'gift_voucher_denominations' => [300, 500, 1000],
            'gift_voucher_default_expiry_months' => 6,
            'gift_voucher_combinable' => false,
            'opens_at' => config('casa.business_hours.opens_at', '13:00'),
            'closes_at' => config('casa.business_hours.closes_at', '23:00'),
            'updated_by' => null,
        ];
    }

    protected function casts(): array
    {
        return [
            'organization_chart' => 'array',
            'promotion_voucher_validity_days' => 'integer',
            'gift_voucher_denominations' => 'array',
            'gift_voucher_default_expiry_months' => 'integer',
            'gift_voucher_combinable' => 'boolean',
        ];
    }

    /** @return list<int> */
    public function giftVoucherDenominations(): array
    {
        $values = $this->gift_voucher_denominations ?: [300, 500, 1000];
        sort($values);

        return array_values(array_map('intval', $values));
    }

    /** Default opening time as H:i. */
    public function opensAt(): string
    {
        return substr((string) ($this->opens_at ?: config('casa.business_hours.opens_at', '13:00')), 0, 5);
    }

    /** Default closing time as H:i; 00:00 means midnight. */
    public function closesAt(): string
    {
        return substr((string) ($this->closes_at ?: config('casa.business_hours.closes_at', '23:00')), 0, 5);
    }

    public function hoursLabel(): string
    {
        return self::hoursRangeLabel($this->opensAt(), $this->closesAt());
    }

    public static function timeLabel(string $time): string
    {
        return substr($time, 0, 5) === '00:00' ? __('12:00 midnight') : Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('g:i A');
    }

    public static function hoursRangeLabel(string $opensAt, string $closesAt): string
    {
        return __(':opens to :closes', ['opens' => self::timeLabel($opensAt), 'closes' => self::timeLabel($closesAt)]);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
