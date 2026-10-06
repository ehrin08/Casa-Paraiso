<?php

namespace App\Models;

use Database\Factories\CustomerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'guest_name',
        'guest_phone',
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
            $profile->customer_code = self::newCustomerCode();
        }

        $profile->save();

        if ($profile->trashed()) {
            $profile->restore();
        }

        return $profile;
    }

    public static function createGuest(string $name, ?string $phone = null): self
    {
        return self::create([
            'guest_name' => trim($name),
            'guest_phone' => filled($phone) ? trim($phone) : null,
            'customer_code' => self::newCustomerCode(),
        ]);
    }

    private static function newCustomerCode(): string
    {
        do {
            $code = 'CP-'.strtoupper(Str::random(8));
        } while (self::withTrashed()->where('customer_code', $code)->exists());

        return $code;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->user?->name ?? $this->guest_name ?? __('Customer unavailable');
    }

    public function getDisplayPhoneAttribute(): ?string
    {
        return $this->user_id ? $this->user?->phone : $this->guest_phone;
    }

    public function getDisplayEmailAttribute(): ?string
    {
        return $this->user?->email;
    }

    public function getIsGuestAttribute(): bool
    {
        return $this->user_id === null;
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('customer_code', 'like', '%'.$search.'%')
            ->orWhere('guest_name', 'like', '%'.$search.'%')
            ->orWhere('guest_phone', 'like', '%'.$search.'%')
            ->orWhereHas('user', fn (Builder $user) => $user
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')));
    }

    /** Internal, fixed SQL aliases only. */
    public static function displayNameSql(string $users = 'users', string $profiles = 'customer_profiles'): string
    {
        return "COALESCE({$users}.name, {$profiles}.guest_name)";
    }

    public function anonymize(): void
    {
        $this->forceFill([
            'guest_name' => null,
            'guest_phone' => null,
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
