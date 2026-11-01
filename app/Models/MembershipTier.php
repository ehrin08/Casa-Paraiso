<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MembershipTier extends Model
{
    protected $fillable = [
        'name',
        'code',
        'rank',
        'stars',
        'description',
        'service_discount_percent',
        'addon_discount_percent',
        'addon_discount_codes',
        'vip_room_price',
        'birthday_treat_label',
        'birthday_treat_addon_code',
        'birthday_treat_service_id',
        'referral_reward_amount',
        'validity_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'stars' => 'integer',
            'service_discount_percent' => 'decimal:2',
            'addon_discount_percent' => 'decimal:2',
            'addon_discount_codes' => 'array',
            'vip_room_price' => 'decimal:2',
            'referral_reward_amount' => 'decimal:2',
            'validity_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('rank')->orderBy('name');
    }

    public function birthdayTreatService()
    {
        return $this->belongsTo(Service::class, 'birthday_treat_service_id');
    }

    public function memberships()
    {
        return $this->hasMany(CustomerMembership::class);
    }

    /** @return list<string> */
    public function benefitLines(): array
    {
        $addonNames = Addon::query()->whereIn('code', [...($this->addon_discount_codes ?? []), 'vip-room'])->pluck('name', 'code');
        $lines = [];

        if ((float) $this->service_discount_percent > 0) {
            $lines[] = __(':percent% off all services', ['percent' => $this->percent($this->service_discount_percent)]);
        }

        if ((float) $this->addon_discount_percent > 0 && $this->addon_discount_codes) {
            $lines[] = __(':percent% off :addons', [
                'percent' => $this->percent($this->addon_discount_percent),
                'addons' => collect($this->addon_discount_codes)->map(fn ($code) => $addonNames[$code] ?? $code)->implode(', '),
            ]);
        }

        if ($this->vip_room_price !== null) {
            $lines[] = __(':room for PHP :price', ['room' => $addonNames['vip-room'] ?? __('VIP Room'), 'price' => number_format((float) $this->vip_room_price, 2)]);
        }

        if ($this->birthday_treat_label) {
            $lines[] = __('Birthday treat: :treat', ['treat' => $this->birthday_treat_label]);
        }

        if ((float) $this->referral_reward_amount > 0) {
            $lines[] = __('Referral reward: PHP :amount off for both members', ['amount' => number_format((float) $this->referral_reward_amount, 2)]);
        }

        return $lines;
    }

    private function percent(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    }
}
