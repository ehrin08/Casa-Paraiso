<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipReferralCredit extends Model
{
    protected $fillable = ['customer_profile_id', 'customer_membership_id', 'amount', 'used_transaction_id', 'used_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'used_at' => 'datetime'];
    }

    public function customerProfile()
    {
        return $this->belongsTo(CustomerProfile::class)->withTrashed();
    }

    public function membership()
    {
        return $this->belongsTo(CustomerMembership::class, 'customer_membership_id');
    }
}
