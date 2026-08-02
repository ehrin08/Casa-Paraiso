<?php

namespace App\Models;

use Database\Factories\CustomerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CustomerProfile extends Model
{
    /** @use HasFactory<CustomerProfileFactory> */
    use HasFactory, SoftDeletes;

    public const CONTACT_EMAIL = 'email';

    public const CONTACT_SMS = 'sms';

    public const CONTACT_PHONE = 'phone';

    public const CONTACT_PREFERENCES = [
        self::CONTACT_EMAIL => 'Email',
        self::CONTACT_SMS => 'Text message (SMS)',
        self::CONTACT_PHONE => 'Phone call',
    ];

    protected $fillable = [
        'user_id',
        'customer_code',
        'birth_date',
        'address',
        'contact_preference',
        'notes',
        'first_visit_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'first_visit_at' => 'datetime',
        ];
    }

    public static function provisionFor(User $user): self
    {
        $profile = self::withTrashed()->firstOrNew(['user_id' => $user->id]);

        if (! $profile->customer_code) {
            do {
                $code = 'CP-'.strtoupper(Str::random(8));
            } while (self::withTrashed()->where('customer_code', $code)->exists());

            $profile->customer_code = $code;
        }

        $profile->save();

        if ($profile->trashed()) {
            $profile->restore();
        }

        return $profile;
    }

    public function anonymize(): void
    {
        $this->forceFill([
            'birth_date' => null,
            'address' => null,
            'contact_preference' => null,
            'notes' => null,
        ])->save();

        $this->delete();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    public function promotionSuggestions()
    {
        return $this->hasMany(PromotionSuggestion::class);
    }
}
