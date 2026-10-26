@php
    $user = Auth::user();
    $isCustomer = $user->isCustomer();
    $receivesBookingNotifications = $user->isAdmin() || $user->isReceptionist() || $user->isStaff();
    $unreadBookings = (int) ($unreadNotificationCount ?? 0);
    $systemIssues = (int) ($unresolvedSystemErrorCount ?? 0);

    $roleLabel = match (true) {
        $user->isSuperAdmin() => 'Super admin workspace',
        $user->isAdmin() => 'Admin workspace',
        $user->isReceptionist() => 'Reception workspace',
        $user->isStaff() => 'Therapist workspace',
        default => 'Customer lounge',
    };

    // Related pages share one drawer link and switch through section tabs on the page itself.
    $navGroups = match (true) {
        $user->isAdmin() => [
            [
                'label' => 'Daily work',
                'items' => [
                    ['label' => 'Today', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
                    ['label' => 'Appointments', 'icon' => 'calendar', 'route' => 'admin.appointments.index', 'active' => 'admin.appointments.*', 'preload' => true, 'booking_schedule' => true],
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*'],
                ],
            ],
            [
                'label' => 'Manage',
                'items' => [
                    ['label' => 'Therapists', 'icon' => 'team', 'route' => 'admin.staff.index', 'active' => ['admin.staff.*', 'admin.staff-roster.*'], 'preload' => true],
                    ['label' => 'Services', 'icon' => 'services', 'route' => 'admin.services.index', 'active' => 'admin.services.*'],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'admin.transactions.index', 'active' => ['admin.transactions.*', 'admin.commissions.*']],
                    ['label' => 'Attendance', 'icon' => 'team', 'route' => 'admin.attendance.index', 'active' => 'admin.attendance.*'],
                    ['label' => 'Insights', 'icon' => 'insights', 'route' => 'admin.reports.index', 'active' => ['admin.reports.*', 'admin.feedback.*', 'admin.promotions.*'], 'preload' => true],
                ],
            ],
        ],
        $user->isStaff() => [
            [
                'label' => 'Daily work',
                'items' => [
                    ['label' => 'Today', 'icon' => 'dashboard', 'route' => 'staff.dashboard', 'active' => 'staff.dashboard'],
                    ['label' => 'My appointments', 'icon' => 'calendar', 'route' => 'staff.appointments.index', 'active' => 'staff.appointments.*', 'preload' => true, 'booking_schedule' => true],
                    ['label' => 'My attendance', 'icon' => 'team', 'route' => 'staff.attendance.show', 'active' => 'staff.attendance.*'],
                ],
            ],
            [
                'label' => 'Review',
                'items' => [
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'staff.customers.index', 'active' => 'staff.customers.*', 'preload' => true],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'staff.transactions.index', 'active' => ['staff.transactions.*', 'staff.commissions.*']],
                    ['label' => 'Feedback', 'icon' => 'feedback', 'route' => 'staff.feedback.index', 'active' => 'staff.feedback.*'],
                ],
            ],
        ],
        $user->isReceptionist() => [
            [
                'label' => 'Daily work',
                'items' => [
                    ['label' => 'Today', 'icon' => 'dashboard', 'route' => 'reception.dashboard', 'active' => 'reception.dashboard'],
                    ['label' => 'Appointments', 'icon' => 'calendar', 'route' => 'reception.appointments.index', 'active' => 'reception.appointments.*', 'preload' => true, 'booking_schedule' => true],
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'reception.customers.index', 'active' => 'reception.customers.*', 'preload' => true],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'reception.transactions.index', 'active' => 'reception.transactions.*'],
                    ['label' => 'Staff attendance', 'icon' => 'team', 'route' => 'reception.attendance.station', 'active' => 'reception.attendance.*'],
                ],
            ],
        ],
        default => [
            [
                'label' => 'My care',
                'items' => [
                    ['label' => 'My appointments', 'icon' => 'calendar', 'route' => 'customer.appointments.index', 'active' => 'customer.appointments.*'],
                    ['label' => 'My feedback', 'icon' => 'feedback', 'route' => 'customer.feedback.index', 'active' => 'customer.feedback.*', 'preload' => true],
                ],
            ],
        ],
    };

    $accountLinks = [
        ['label' => 'My profile', 'icon' => 'profile', 'route' => 'profile.edit', 'active' => 'profile.*', 'preload' => $isCustomer],
        ...($user->isAdmin() ? [['label' => 'Settings', 'icon' => 'settings', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*']] : []),
        ...($user->isSuperAdmin() ? [
            ['label' => 'User accounts', 'icon' => 'team', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
            ['label' => 'System issues', 'icon' => 'errors', 'route' => 'admin.system-errors.index', 'active' => 'admin.system-errors.*', 'badge' => $systemIssues],
        ] : []),
    ];

    // Each destination keeps a consistent soft tone on its icon chip.
    $navTones = [
        'Today' => 'palm', 'Appointments' => 'blue', 'My appointments' => 'blue', 'Customers' => 'rose',
        'Therapists' => 'violet', 'Services' => 'brass', 'Payments' => 'cacao', 'Attendance' => 'palm',
        'My attendance' => 'palm', 'Staff attendance' => 'violet', 'Insights' => 'blue', 'Feedback' => 'rose',
        'My feedback' => 'rose', 'My profile' => 'palm', 'Settings' => 'brass', 'User accounts' => 'violet',
        'System issues' => 'rose',
    ];

    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<nav x-data data-role-navigation="{{ $user->role }}" @if($receivesBookingNotifications) data-booking-notifications data-booking-notifications-user-id="{{ $user->id }}" data-booking-notifications-count-url="{{ route('notifications.bookings.count') }}" data-booking-notifications-read-url="{{ route('notifications.bookings.read') }}" @endif>
    <div class="casa-topbar casa-glass-bar sticky top-0 z-40 border-b">
        <div class="mx-auto flex h-16 max-w-[90rem] items-center gap-2 px-3 sm:gap-3 sm:px-5 lg:px-6">
            <button type="button" class="casa-icon-button" data-drawer-toggle aria-controls="workspace-navigation" x-bind:aria-expanded="$store.drawer.isOpen" aria-label="Open navigation" @click="$store.drawer.open($el)">
                <x-nav-icon name="menu" />
            </button>

            <a href="{{ url('/') }}" class="casa-topbar-logo min-w-0 rounded-2xl bg-white px-2 py-1" aria-label="Casa Paraiso website" data-site-home-link>
                <x-application-logo class="origin-left scale-[0.72]" />
            </a>

            <div class="ms-auto flex items-center gap-2">
                <a href="{{ url('/') }}" class="casa-topbar-link" data-view-site-link>
                    <x-nav-icon name="home" />
                    <span class="hidden sm:inline">{{ __('View site') }}</span>
                    <span class="sr-only sm:hidden">{{ __('View site') }}</span>
                </a>

                @if ($receivesBookingNotifications)
                    <a href="{{ route('notifications.index') }}" @class(['casa-icon-button relative', 'casa-icon-button-active' => request()->routeIs('notifications.*')]) data-notification-bell @if(request()->routeIs('notifications.*')) aria-current="page" @endif>
                        <x-nav-icon name="notifications" />
                        <span class="sr-only">{{ __('Notifications') }}</span>
                        <span data-booking-notification-badge @class(['casa-topbar-badge', 'hidden' => $unreadBookings === 0]) aria-label="{{ $unreadBookings }} unread booking notifications"><span data-booking-notification-badge-value>{{ min($unreadBookings, 99) }}{{ $unreadBookings > 99 ? '+' : '' }}</span></span>
                    </a>
                @endif

                <x-dropdown align="right" width="w-72" content-classes="p-2 bg-casa-paper">
                    <x-slot name="trigger">
                        <button type="button" class="casa-avatar-button" data-account-menu-trigger aria-haspopup="menu" aria-label="{{ __('Account menu for :name', ['name' => $user->name]) }}">
                            <span>{{ $initials }}</span>
                            @if ($systemIssues > 0)
                                <span class="casa-avatar-dot" aria-hidden="true"></span>
                            @endif
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div data-account-menu>
                            <div class="px-3 pb-3 pt-2">
                                <p class="text-sm font-extrabold uppercase tracking-[0.05em] text-casa-cacao">{{ $roleLabel }}</p>
                                <p class="mt-1 truncate text-sm font-semibold text-casa-text">{{ $user->name }}</p>
                                <p class="truncate text-sm text-casa-muted">{{ $user->email }}</p>
                            </div>
                            <div class="border-t border-casa-border/70 pt-2">
                                @foreach ($accountLinks as $item)
                                    @php $isActive = request()->routeIs(...(array) $item['active']); @endphp
                                    <a href="{{ route($item['route']) }}" data-sidebar-label="{{ $item['label'] }}" @class(['casa-menu-link', 'casa-menu-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @if($item['preload'] ?? false) data-turbo-preload @endif>
                                        <span class="casa-nav-icon casa-tone-{{ $navTones[$item['label']] ?? 'palm' }}"><x-nav-icon :name="$item['icon']" /></span>
                                        <span>{{ $item['label'] }}</span>
                                        @if (($item['badge'] ?? 0) > 0)
                                            <span class="ms-auto rounded-full bg-casa-brass px-2 py-0.5 text-xs font-black text-casa-ink" aria-label="{{ $item['badge'] }} unresolved server errors">{{ min((int) $item['badge'], 99) }}{{ $item['badge'] > 99 ? '+' : '' }}</span>
                                        @endif
                                    </a>
                                @endforeach
                                <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                                    @csrf
                                    <button type="submit" class="casa-menu-link w-full">
                                        <span class="casa-nav-icon casa-tone-cacao"><x-nav-icon name="logout" /></span>
                                        <span>{{ __('Log out') }}</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </div>

    <div x-cloak x-show="$store.drawer.isOpen" class="casa-drawer-host" data-drawer-host>
        <div class="casa-drawer-backdrop" @click="$store.drawer.close()" aria-hidden="true"></div>
        <aside id="workspace-navigation" data-workspace-drawer class="casa-drawer" aria-label="Workspace navigation"
            @keydown.escape.window="$store.drawer.close()" @keydown.tab="$store.drawer.trapFocus($event, $el)">
            <div class="flex items-center justify-between gap-3 px-1">
                <p class="text-sm font-extrabold uppercase tracking-[0.08em] text-casa-brass-light">{{ $roleLabel }}</p>
                <button type="button" class="casa-drawer-close" aria-label="Close navigation" @click="$store.drawer.close()">
                    <x-nav-icon name="close" />
                </button>
            </div>

            <div class="mt-4 space-y-3">
                @foreach ($navGroups as $group)
                    <section aria-label="{{ $group['label'] }} navigation" @class(['casa-drawer-group', 'casa-drawer-group-divided' => ! $loop->first])>
                        @foreach ($group['items'] as $item)
                            @php $isActive = request()->routeIs(...(array) $item['active']); @endphp
                            <a href="{{ route($item['route']) }}" data-sidebar-label="{{ $item['label'] }}" @class(['casa-nav-link w-full', 'casa-nav-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @if($item['preload'] ?? false) data-turbo-preload @endif @if($item['booking_schedule'] ?? false) data-booking-schedule-link @endif @click="$store.drawer.close()">
                                <span class="casa-nav-icon casa-tone-{{ $navTones[$item['label']] ?? 'palm' }}"><x-nav-icon :name="$item['icon']" /></span>
                                <span>{{ $item['label'] }}</span>
                                @if ($item['booking_schedule'] ?? false)
                                    <span data-booking-notification-badge @class(['ms-auto rounded-full bg-casa-brass px-2 py-0.5 text-xs font-black text-casa-ink', 'hidden' => $unreadBookings === 0]) aria-label="{{ $unreadBookings }} unread booking notifications"><span data-booking-notification-badge-value>{{ min($unreadBookings, 99) }}{{ $unreadBookings > 99 ? '+' : '' }}</span></span>
                                @endif
                            </a>
                        @endforeach
                    </section>
                @endforeach
            </div>

            <a href="{{ url('/') }}" class="casa-drawer-site-link mt-auto">
                <x-nav-icon name="home" />
                <span>{{ __('View the Casa Paraiso website') }}</span>
            </a>
        </aside>
    </div>

    @if ($isCustomer)
        <nav data-mobile-customer-navigation class="casa-mobile-dock lg:hidden" aria-label="Customer navigation">
            <a href="{{ route('customer.appointments.index') }}" @class(['casa-mobile-dock-link', 'casa-mobile-dock-link-active' => request()->routeIs('customer.appointments.*')]) @if(request()->routeIs('customer.appointments.*')) aria-current="page" @endif>
                <x-nav-icon name="calendar" class="size-4" />
                <span>My appointments</span>
            </a>
            <a href="{{ route('customer.feedback.index') }}" @class(['casa-mobile-dock-link', 'casa-mobile-dock-link-active' => request()->routeIs('customer.feedback.*')]) @if(request()->routeIs('customer.feedback.*')) aria-current="page" @endif>
                <x-nav-icon name="feedback" class="size-4" />
                <span>My feedback</span>
            </a>
            <a href="{{ route('profile.edit') }}" @class(['casa-mobile-dock-link', 'casa-mobile-dock-link-active' => request()->routeIs('profile.*')]) @if(request()->routeIs('profile.*')) aria-current="page" @endif>
                <x-nav-icon name="profile" class="size-4" />
                <span>My profile</span>
            </a>
        </nav>
    @endif
</nav>
