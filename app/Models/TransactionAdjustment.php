<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionAdjustment extends Model
{
    public const TYPE_SERVICE_DISCOUNT = 'service_discount';

    public const TYPE_ADDON_DISCOUNT = 'addon_discount';

    public const TYPE_VIP_ROOM = 'vip_room';

    public const TYPE_BIRTHDAY = 'birthday_treat';

    public const TYPE_REFERRAL = 'referral_credit';

    protected $fillable = ['transaction_id', 'type', 'label', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
