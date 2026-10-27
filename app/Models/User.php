<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_STAFF = 'staff';

    public const ROLE_RECEPTIONIST = 'receptionist';

    public const ROLE_CUSTOMER = 'customer';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_STAFF,
        self::ROLE_RECEPTIONIST,
        self::ROLE_CUSTOMER,
    ];

    public const ORG_TIER_OWNER = 'owner';

    public const ORG_TIER_LEADERSHIP = 'leadership';

    public const ORG_TIER_TEAM = 'team';

    public const ORG_TIERS = [
        self::ORG_TIER_OWNER,
        self::ORG_TIER_LEADERSHIP,
        self::ORG_TIER_TEAM,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'google_id',
        'phone',
        'password',
        'role',
        'is_active',
        'public_title',
        'public_bio',
        'org_chart_tier',
        'org_chart_order',
        'show_on_org_chart',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'org_chart_order' => 'integer',
            'show_on_org_chart' => 'boolean',
        ];
    }

    /** Active staff accounts an admin has published on the public team chart. */
    public function scopePubliclyListed($query)
    {
        return $query
            ->where('show_on_org_chart', true)
            ->where('is_active', true)
            ->where('role', '!=', self::ROLE_CUSTOMER)
            ->whereIn('org_chart_tier', self::ORG_TIERS);
    }

    public function scopeTeamMembers($query)
    {
        return $query->where('role', '!=', self::ROLE_CUSTOMER);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => __('Owner'),
            self::ROLE_ADMIN => __('Administrator'),
            self::ROLE_RECEPTIONIST => __('Receptionist'),
            self::ROLE_STAFF => __('Therapist'),
            default => __('Customer'),
        };
    }

    public function publicTitle(): string
    {
        return filled($this->public_title) ? $this->public_title : $this->roleLabel();
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/u', trim((string) $this->name)))
            ->filter()
            ->take(2)
            ->map(static fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN
            && strtolower($this->email) === strtolower(config('auth.super_admin_email'));
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isReceptionist(): bool
    {
        return $this->role === self::ROLE_RECEPTIONIST;
    }

    public function homeRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_STAFF => 'staff.dashboard',
            self::ROLE_RECEPTIONIST => 'reception.dashboard',
            default => 'customer.appointments.index',
        };
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class)->withTrashed();
    }

    public function customerProfile()
    {
        return $this->hasOne(CustomerProfile::class)->withTrashed();
    }

    public function recordedTransactions()
    {
        return $this->hasMany(Transaction::class, 'recorded_by');
    }

    public function reviewedPromotionSuggestions()
    {
        return $this->hasMany(PromotionSuggestion::class, 'reviewed_by');
    }

    public function paidCommissions()
    {
        return $this->hasMany(TherapistCommission::class, 'paid_by');
    }

    public function recordedAttendanceEvents()
    {
        return $this->hasMany(StaffAttendanceEvent::class, 'recorded_by');
    }
}
