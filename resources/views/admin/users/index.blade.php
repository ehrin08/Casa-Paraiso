<x-app-layout>
    @php
        $roleLabels = ['super_admin' => __('Super administrator'), 'admin' => __('Administrator'), 'receptionist' => __('Receptionist'), 'staff' => __('Therapist'), 'customer' => __('Customer')];
        $selectionQuery = $filters + ['page' => $users->currentPage()];
        $cancelUrl = route('admin.users.index', $selectionQuery);
        $hasOldInput = old('_user_form') === ($selectedUser ? 'edit' : 'create') && (! $selectedUser || (int) old('_user_id') === $selectedUser->id);
        $formValue = fn ($field, $default = '') => $hasOldInput ? old($field, $default) : $default;
        $initials = fn ($name) => collect(preg_split('/\s+/u', trim($name)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    @endphp

    <x-slot name="header">
        <div class="users-heading">
            <h1 class="font-display text-3xl font-black text-casa-text">{{ __('User accounts') }}</h1>
            <p>{{ __('Manage system access for Casa Paraiso spa and wellness.') }}</p>
        </div>
        <div class="users-access-notice">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v3"/></svg>
            <div><strong>{{ __('Super admin only') }}</strong><p>{{ __('Only super administrators can view and manage user accounts.') }}</p></div>
        </div>
    </x-slot>

    <div class="users-workspace" data-users-workspace>
        @if (session('eligibility_conflicts'))
            <div class="users-errors" role="alert">
                <strong>{{ __('Resolve these confirmed appointments before changing therapist access') }}</strong>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach (session('eligibility_conflicts') as $conflict)
                        <a href="{{ $conflict['url'] }}" class="casa-button-secondary">{{ $conflict['number'] }} · {{ \Illuminate\Support\Carbon::parse($conflict['starts_at'])->timezone('Asia/Manila')->format('M d, g:i A') }}</a>
                    @endforeach
                </div>
            </div>
        @endif

        <x-list-toolbar :active-filters="count($filters)" :collapsible="true" class="users-toolbar casa-list-toolbar--filters">
            <form method="GET" action="{{ route('admin.users.index') }}" class="users-filter-form">
                <div class="users-field users-search-field">
                    <label for="users-search">{{ __('Search by name or email') }}</label>
                    <div class="users-search-control">
                        <input id="users-search" name="q" type="search" maxlength="255" value="{{ $filters['q'] ?? '' }}" class="casa-input" placeholder="{{ __('Search names or email addresses…') }}">
                        <button type="submit" aria-label="{{ __('Search user accounts') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg></button>
                    </div>
                    <x-input-error :messages="$errors->get('q')" />
                </div>
                <div class="users-field">
                    <label for="users-role">{{ __('Role') }}</label>
                    <select id="users-role" name="role" class="casa-input" x-on:change="$el.form.requestSubmit()">
                        <option value="">{{ __('All roles') }}</option>
                        @foreach ($roleLabels as $role => $label)<option value="{{ $role }}" @selected(($filters['role'] ?? null) === $role)>{{ $label }}</option>@endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role')" />
                </div>
                <div class="users-field">
                    <label for="users-status">{{ __('Status') }}</label>
                    <select id="users-status" name="status" class="casa-input" x-on:change="$el.form.requestSubmit()">
                        <option value="">{{ __('All statuses') }}</option>
                        <option value="active" @selected(($filters['status'] ?? null) === 'active')>{{ __('Active') }}</option>
                        <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>{{ __('Inactive') }}</option>
                    </select>
                    <x-input-error :messages="$errors->get('status')" />
                </div>
                <div class="users-filter-actions">
                    @if ($filters)<a href="{{ route('admin.users.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a>@endif
                    <button type="submit" class="casa-button-secondary users-apply">{{ __('Apply filters') }}</button>
                </div>
            </form>
        </x-list-toolbar>
        <noscript><style>.users-toolbar [data-filter-panel], .users-toolbar .users-apply { display: block; } .users-toolbar [data-filter-toggle] { display: none; }</style></noscript>

        <div class="users-grid">
            <section class="users-panel users-records" aria-labelledby="users-list-heading">
                <div class="users-panel-heading"><h2 id="users-list-heading">{{ __('User accounts') }}</h2><p>{{ trans_choice(':count account|:count accounts', $users->total()) }}</p></div>
                @if ($users->isEmpty())
                    <x-empty-state title="{{ __('No user accounts found') }}" description="{{ __('Try another name, email, role, or status.') }}">
                        <x-slot name="action">
                            @if ($filters)
                                <a href="{{ route('admin.users.index') }}" class="casa-button-secondary">{{ __('Clear filters') }}</a>
                            @endif
                        </x-slot>
                    </x-empty-state>
                @else
                    <x-table-shell class="users-table" label="{{ __('Scrollable user accounts') }}">
                        <thead><tr>@foreach ([__('Name'), __('Email'), __('Role'), __('Status'), __('Actions')] as $label)<th scope="col">{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($users as $managedUser)
                                <tr @class(['is-selected' => $selectedUser?->id === $managedUser->id]) data-user-id="{{ $managedUser->id }}">
                                    <td><div class="users-person"><span class="users-avatar" aria-hidden="true">{{ $initials($managedUser->name) }}</span><span>{{ $managedUser->name }}</span></div></td>
                                    <td class="users-email">{{ $managedUser->email }}</td>
                                    <td><span class="users-role users-role--{{ $managedUser->role }}">{{ $roleLabels[$managedUser->role] ?? $managedUser->role }}</span></td>
                                    <td><span @class(['users-status', 'users-status--inactive' => ! $managedUser->is_active])>{{ $managedUser->is_active ? __('Active') : __('Inactive') }}</span></td>
                                    <td>
                                        @if ($managedUser->isSuperAdmin())
                                            <span class="users-protected">{{ __('Protected') }}</span>
                                        @else
                                            <a href="{{ route('admin.users.index', $selectionQuery + ['selected' => $managedUser->id]) }}#user-access-form" class="users-edit" aria-label="{{ __('Edit access for :name', ['name' => $managedUser->name]) }}" @if ($selectedUser?->id === $managedUser->id) aria-current="true" @endif><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 4 5 5M4 20l5-1L21 7a2 2 0 0 0-4-4L5 15l-1 5Z"/></svg></a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-table-shell>
                    <div class="users-pagination">{{ $users->links() }}</div>
                @endif
            </section>

            <aside id="user-access-form" class="users-panel users-form-panel" aria-labelledby="users-form-heading" @if ($selectedUser) data-selected-user="{{ $selectedUser->id }}" @endif tabindex="-1">
                <h2 id="users-form-heading">{{ $selectedUser ? __('Edit user access') : __('Add user (pre-authorize access)') }}</h2>
                <p class="users-help">{{ $selectedUser ? __('Update this account’s role and sign-in access.') : __('Create a user account and set their role and access.') }}</p>
                <form method="POST" action="{{ $selectedUser ? route('admin.users.update', $selectedUser) : route('admin.users.store') }}" class="users-access-form">
                    @csrf
                    @if ($selectedUser) @method('PUT') @endif
                    <input type="hidden" name="_user_form" value="{{ $selectedUser ? 'edit' : 'create' }}">
                    @if ($selectedUser)<input type="hidden" name="_user_id" value="{{ $selectedUser->id }}">@endif
                    @if ($hasOldInput && $errors->any())
                        <div class="users-errors" role="alert"><p>{{ __('The account could not be saved. Review the fields below.') }}</p><x-input-error :messages="$errors->get('staff_eligibility')" /></div>
                    @endif
                    <div class="users-field">
                        <label for="user-name">{{ __('Name') }}</label>
                        <input id="user-name" name="name" value="{{ $formValue('name', $selectedUser?->name ?? '') }}" class="casa-input" required maxlength="255" autocomplete="name">
                        @if ($hasOldInput)<x-input-error :messages="$errors->get('name')" />@endif
                    </div>
                    <div class="users-field">
                        <label for="user-email">{{ __('Email') }}</label>
                        <input id="user-email" name="email" type="email" value="{{ $selectedUser?->google_id ? $selectedUser->email : $formValue('email', $selectedUser?->email ?? '') }}" class="casa-input" required maxlength="255" autocomplete="email" @readonly($selectedUser?->google_id) @if ($selectedUser?->google_id) aria-describedby="user-email-help" @endif>
                        @if ($selectedUser?->google_id)<p id="user-email-help" class="users-help">{{ __('Linked emails are updated only through Google sign-in.') }}</p>@endif
                        @if ($hasOldInput)<x-input-error :messages="$errors->get('email')" />@endif
                    </div>
                    <div class="users-field">
                        <label for="user-role">{{ __('Role') }}</label>
                        <select id="user-role" name="role" class="casa-input" required>
                            @foreach ($assignableRoles as $role)<option value="{{ $role }}" @selected($formValue('role', $selectedUser?->role ?? 'staff') === $role)>{{ $roleLabels[$role] }}</option>@endforeach
                        </select>
                        @if ($hasOldInput)<x-input-error :messages="$errors->get('role')" />@endif
                    </div>
                    <div class="users-field">
                        <label for="user-status">{{ __('Status') }}</label>
                        <select id="user-status" name="is_active" class="casa-input" required>
                            <option value="1" @selected((string) $formValue('is_active', $selectedUser ? (int) $selectedUser->is_active : 1) === '1')>{{ __('Active') }}</option>
                            <option value="0" @selected((string) $formValue('is_active', $selectedUser ? (int) $selectedUser->is_active : 1) === '0')>{{ __('Inactive') }}</option>
                        </select>
                        @if ($hasOldInput)<x-input-error :messages="$errors->get('is_active')" />@endif
                    </div>
                    @if ($selectedUser?->isStaff() && $selectedUser->staffProfile)<a class="users-profile-link" href="{{ route('admin.staff.edit', $selectedUser->staffProfile) }}">{{ __('Manage therapist profile') }}</a>@endif
                    <div class="users-access-notice users-form-notice"><x-nav-icon name="team"/><div><strong>{{ __('Super admin only') }}</strong><p>{{ __('User account management is restricted to super administrators.') }}</p></div></div>
                    <div class="users-form-actions">
                        <button type="submit" class="casa-button-primary">{{ $selectedUser ? __('Save changes') : __('Create user') }}</button>
                        <a href="{{ $cancelUrl }}#user-access-form" class="casa-button-secondary">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </aside>
        </div>
    </div>
</x-app-layout>
