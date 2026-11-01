<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipPerkUsage extends Model
{
    public const PERK_BIRTHDAY = 'birthday';

    protected $fillable = ['customer_membership_id', 'transaction_id', 'perk', 'amount', 'used_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'used_at' => 'datetime'];
    }
}
