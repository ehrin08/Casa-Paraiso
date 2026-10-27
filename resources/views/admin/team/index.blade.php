<x-app-layout>
    <x-slot name="header">
        <div class="team-heading">
            <h1>{{ __('Team page') }}</h1>
            <p>{{ __('Choose which staff accounts appear on the homepage chart, with their photo, title and level.') }}</p>
        </div>

        <a href="{{ url('/').'#team' }}" class="casa-button-secondary" target="_blank" rel="noopener">{{ __('View on website') }}</a>
    </x-slot>

    @php
        $activeFilters = collect([$search, $role, $visibility])->filter(fn ($value) => filled($value))->count();
        $tierLabels = [
            \App\Models\User::ORG_TIER_OWNER => __('Owner'),
            \App\Models\User::ORG_TIER_LEADERSHIP => __('Leadership'),
            \App\Models\User::ORG_TIER_TEAM => __('Team'),
        ];
        $roleLabels = [
            \App\Models\User::ROLE_SUPER_ADMIN => __('Super administrator'),
            \App\Models\User::ROLE_ADMIN => __('Administrator'),
            \App\Models\User::ROLE_RECEPTIONIST => __('Receptionist'),
            \App\Models\User::ROLE_STAFF => __('Therapist'),
        ];
    @endphp

    <div class="team-workspace" data-team-workspace>
        @if (session('status') === 'team-card-updated')
            <p class="team-flash" role="status">{{ __('Team card saved.') }}</p>
        @endif

        <x-stat-strip :items="[
            ['label' => __('On the website'), 'value' => $shownCount, 'meta' => __('Active accounts with a level'), 'tone' => 'green'],
            ['label' => __('Not shown'), 'value' => $hiddenCount, 'meta' => __('Hidden, inactive or no level'), 'tone' => 'dark'],
            ['label' => __('Shown without photo'), 'value' => $missingPhotoCount, 'meta' => __('Initials are used instead'), 'tone' => 'gold'],
        ]" />

        <x-list-toolbar :count="$members->total()" :reset-url="route('admin.team.index')" :active-filters="$activeFilters" :collapsible="true" class="team-toolbar casa-list-toolbar--filters">
            <form method="GET" action="{{ route('admin.team.index') }}" class="team-filter-form" x-data>
                <div class="team-search">
                    <label for="team-search" class="sr-only">{{ __('Search staff') }}</label>
                    <input id="team-search" type="search" name="q" value="{{ $search }}" class="casa-input" placeholder="{{ __('Search name, email or title…') }}">
                </div>
                <div>
                    <label for="team-role" class="sr-only">{{ __('Role') }}</label>
                    <select id="team-role" name="role" class="casa-input" x-on:change="$el.form.requestSubmit()">
                        <option value="">{{ __('All roles') }}</option>
                        @foreach ($roles as $value)
                            <option value="{{ $value }}" @selected($role === $value)>{{ $roleLabels[$value] ?? $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="team-visibility" class="sr-only">{{ __('Website visibility') }}</label>
                    <select id="team-visibility" name="visibility" class="casa-input" x-on:change="$el.form.requestSubmit()">
                        <option value="">{{ __('Shown and hidden') }}</option>
                        <option value="shown" @selected($visibility === 'shown')>{{ __('On the website') }}</option>
                        <option value="hidden" @selected($visibility === 'hidden')>{{ __('Not shown') }}</option>
                    </select>
                </div>
                <button type="submit" class="casa-button-secondary">{{ __('Search') }}</button>
            </form>
        </x-list-toolbar>

        <section class="team-records" aria-label="{{ __('Staff team cards') }}">
            @if ($members->isEmpty())
                <x-empty-state :title="__('No staff accounts found')" :description="__('Try another name, role or visibility filter.')">
                    <x-slot name="action">
                        @if ($activeFilters)
                            <a href="{{ route('admin.team.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a>
                        @endif
                    </x-slot>
                </x-empty-state>
            @else
                <x-table-shell class="team-table" label="{{ __('Scrollable staff team cards') }}">
                    <thead>
                        <tr>@foreach ([__('Person'), __('Public title'), __('Level'), __('Order'), __('Website'), __('Actions')] as $label)<th scope="col">{{ $label }}</th>@endforeach</tr>
                    </thead>
                    <tbody>
                        @foreach ($members as $member)
                            @php
                                $listed = $member->show_on_org_chart && $member->is_active && filled($member->org_chart_tier);
                                $locked = $member->role === \App\Models\User::ROLE_SUPER_ADMIN && ! auth()->user()->isSuperAdmin();
                            @endphp
                            <tr data-team-member="{{ $member->id }}">
                                <td>
                                    <div class="team-person">
                                        <x-user-avatar :user="$member" class="team-avatar" />
                                        <div>
                                            <p class="team-name">{{ $member->name }}</p>
                                            <p class="team-role">{{ $roleLabels[$member->role] ?? $member->role }}@unless ($member->is_active) · {{ __('Inactive') }}@endunless</p>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $member->publicTitle() }}</td>
                                <td>{{ $tierLabels[$member->org_chart_tier] ?? '—' }}</td>
                                <td class="team-order">{{ $member->org_chart_order }}</td>
                                <td>
                                    <span @class(['team-status', 'team-status--shown' => $listed])>{{ $listed ? __('Shown') : __('Hidden') }}</span>
                                </td>
                                <td>
                                    @if ($locked)
                                        <span class="team-locked">{{ __('Owner only') }}</span>
                                    @else
                                        <a data-panel-link data-turbo="false" aria-haspopup="dialog" href="{{ route('admin.team.edit', $member) }}" class="team-edit" aria-label="{{ __('Edit team card for :name', ['name' => $member->name]) }}">{{ __('Edit card') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table-shell>
            @endif
        </section>
        {{ $members->links('pagination.compact') }}
    </div>
</x-app-layout>
