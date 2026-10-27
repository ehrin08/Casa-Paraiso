<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Builds the public homepage chart from the staff accounts an admin has published. */
class OrganizationChart
{
    /** Only presentation columns leave the database; email, phone and role internals never reach the page. */
    private const PUBLIC_COLUMNS = ['id', 'name', 'role', 'photo_path', 'public_title', 'public_bio', 'org_chart_tier', 'org_chart_order'];

    /** @return array{owner: Collection<int, User>, leadership: Collection<int, User>, team: Collection<int, User>} */
    public function tiers(): array
    {
        $members = Schema::hasColumn('users', 'org_chart_tier')
            ? User::query()
                ->publiclyListed()
                ->orderBy('org_chart_order')
                ->orderBy('name')
                ->get(self::PUBLIC_COLUMNS)
            : collect();

        return collect(User::ORG_TIERS)
            ->mapWithKeys(fn (string $tier): array => [$tier => $members->where('org_chart_tier', $tier)->values()])
            ->all();
    }
}
