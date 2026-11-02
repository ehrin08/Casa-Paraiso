<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\StaffAttendance;
use App\Models\StaffAttendanceEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Short role-scoped fingerprints that change whenever a live-updating screen would.
 * Browsers poll them and refetch a page region only when its scope changes.
 */
class LiveStateFingerprint
{
    /**
     * @return array<string, string>
     */
    public function forUser(User $user): array
    {
        $scopes = [];
        $appointments = $this->appointmentQuery($user);

        if ($appointments) {
            $scopes['appointments'] = $this->hash($appointments);
        }

        if ($user->isAdmin()) {
            $scopes['attendance'] = $this->hash(StaffAttendance::query(), StaffAttendanceEvent::query()->max('id'));
        }

        if (! $user->isCustomer()) {
            $notifications = $user->notifications();
            $scopes['notifications'] = $this->hash(
                $notifications,
                $user->unreadNotifications()->count(),
                'created_at',
            );
        }

        return $scopes;
    }

    private function appointmentQuery(User $user): ?Builder
    {
        return match (true) {
            $user->isAdmin(), $user->isReceptionist() => Appointment::query(),
            $user->isStaff() => Appointment::query()->where('staff_profile_id', $user->staffProfile?->id ?? 0),
            $user->isCustomer() => Appointment::query()->where('customer_profile_id', $user->customerProfile?->id ?? 0),
            default => null,
        };
    }

    private function hash(Builder|Relation $query, mixed $extra = null, string $updatedColumn = 'updated_at'): string
    {
        $row = $query->toBase()->reorder()
            ->selectRaw('COUNT(*) AS total, MAX(id) AS last_id, MAX('.$updatedColumn.') AS last_change')
            ->first();

        return substr(sha1(implode('|', [$row->total, $row->last_id, $row->last_change, $extra])), 0, 16);
    }
}
