@php
    $user = Auth::user();
    $dashboardRoute = $user->homeRouteName();
    $isCustomer = $user->isCustomer();
    $receivesBookingNotifications = $user->isAdmin() || $user->isReceptionist() || $user->isStaff();

    $roleLabel = match (true) {
        $user->isSuperAdmin() => 'Super admin workspace',
        $user->isAdmin() => 'Admin workspace',
        $user->isReceptionist() => 'Reception workspace',
        $user->isStaff() => 'Therapist workspace',
        default => 'Customer lounge',
    };

    $navGroups = match (true) {
        $user->isAdmin() => [
            [
                'label' => 'Daily work',
                'items' => [
                    ['label' => 'Today', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
                    ['label' => 'Appointments', 'icon' => 'calendar', 'route' => 'admin.appointments.index', 'active' => 'admin.appointments.*', 'preload' => true, 'badge' => $unreadNotificationCount ?? 0, 'booking_schedule' => true],
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*'],
                ],
            ],
            [
                'label' => 'Manage',
                'items' => [
                    ['label' => 'Therapists', 'icon' => 'team', 'route' => 'admin.staff.index', 'active' => ['admin.staff.*', 'admin.staff-roster.*'], 'preload' => true],
                    ['label' => 'Services', 'icon' => 'services', 'route' => 'admin.services.index', 'active' => 'admin.services.*'],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'admin.transactions.index', 'active' => 'admin.transactions.*'],
                    ['label' => 'Commissions', 'icon' => 'payments', 'route' => 'admin.commissions.index', 'active' => 'admin.commissions.*'],
                    ['label' => 'Attendance', 'icon' => 'team', 'route' => 'admin.attendance.index', 'active' => 'admin.attendance.*'],
                ],
            ],
            [
                'label' => 'Review',
                'items' => [
                    ['label' => 'Customer rewards', 'icon' => 'insights', 'route' => 'admin.promotions.index', 'active' => 'admin.promotions.*', 'preload' => true],
                    ['label' => 'Feedback', 'icon' => 'feedback', 'route' => 'admin.feedback.index', 'active' => 'admin.feedback.*'],
                    ['label' => 'Reports', 'icon' => 'insights', 'route' => 'admin.reports.index', 'active' => 'admin.reports.*'],
                ],
            ],
        ],
        $user->isStaff() => [
            [
                'label' => 'Daily work',
                'items' => [
                    ['label' => 'Today', 'icon' => 'dashboard', 'route' => 'staff.dashboard', 'active' => 'staff.dashboard'],
                    ['label' => 'My appointments', 'icon' => 'calendar', 'route' => 'staff.appointments.index', 'active' => 'staff.appointments.*', 'preload' => true, 'badge' => $unreadNotificationCount ?? 0, 'booking_schedule' => true],
                    ['label' => 'My attendance', 'icon' => 'team', 'route' => 'staff.attendance.show', 'active' => 'staff.attendance.*'],
                ],
            ],
            [
                'label' => 'Review',
                'items' => [
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'staff.customers.index', 'active' => 'staff.customers.*', 'preload' => true],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'staff.transactions.index', 'active' => 'staff.transactions.*'],
                    ['label' => 'My commissions', 'icon' => 'payments', 'route' => 'staff.commissions.index', 'active' => 'staff.commissions.*'],
                    ['label' => 'Feedback', 'icon' => 'feedback', 'route' => 'staff.feedback.index', 'active' => 'staff.feedback.*'],
                ],
            ],
        ],
        $user->isReceptionist() => [
            [
                'label' => 'Daily work',
                'items' => [
                    ['label' => 'Today', 'icon' => 'dashboard', 'route' => 'reception.dashboard', 'active' => 'reception.dashboard'],
                    ['label' => 'Appointments', 'icon' => 'calendar', 'route' => 'reception.appointments.index', 'active' => 'reception.appointments.*', 'preload' => true, 'badge' => $unreadNotificationCount ?? 0, 'booking_schedule' => true],
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

    $notificationLink = $receivesBookingNotifications
        ? [['label' => 'Notifications', 'icon' => 'notifications', 'route' => 'notifications.index', 'active' => 'notifications.*']]
        : [];

    $accountLinks = $user->isAdmin()
        ? [
            ...$notificationLink,
            ...($user->isSuperAdmin() ? [
                ['label' => 'User accounts', 'icon' => 'team', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
                ['label' => 'System issues', 'icon' => 'errors', 'route' => 'admin.system-errors.index', 'active' => 'admin.system-errors.*', 'badge' => $unresolvedSystemErrorCount ?? 0],
            ] : []),
            ['label' => 'Settings', 'icon' => 'settings', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*'],
            ['label' => 'My profile', 'icon' => 'profile', 'route' => 'profile.edit', 'active' => 'profile.*', 'preload' => $isCustomer],
        ]
        : [
            ...$notificationLink,
            ['label' => 'My profile', 'icon' => 'profile', 'route' => 'profile.edit', 'active' => 'profile.*', 'preload' => $isCustomer],
        ];

    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<nav x-data="{ open: false }" x-on:keydown.escape.window="open = false; $store.sidebar.hideTooltip()" data-role-navigation="{{ $user->role }}" @if($receivesBookingNotifications) data-booking-notifications data-booking-notifications-user-id="{{ $user->id }}" data-booking-notifications-count-url="{{ route('notifications.bookings.count') }}" data-booking-notifications-read-url="{{ route('notifications.bookings.read') }}" @endif>
    <div class="sticky top-0 z-40 border-b border-casa-border/80 bg-casa-paper/94 px-4 py-2 backdrop-blur-xl lg:hidden">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route($dashboardRoute) }}" class="min-w-0 rounded-xl bg-white px-2 py-1">
                <x-application-logo class="origin-left scale-[0.72]" />
            </a>

            <div class="flex items-center gap-2">
                <span class="hidden text-right sm:block">
                    <span class="block text-sm font-extrabold uppercase tracking-[0.05em] text-casa-cacao">{{ $roleLabel }}</span>
                    <span class="block max-w-36 truncate text-sm font-semibold text-casa-muted">{{ $user->name }}</span>
                </span>
                <button type="button" class="casa-icon-button" aria-label="Open account navigation" aria-controls="mobile-workspace-navigation" x-bind:aria-expanded="open" @click="open = true">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <aside id="desktop-workspace-navigation" data-desktop-sidebar aria-label="Desktop workspace navigation" class="casa-wood-panel fixed inset-y-0 start-0 z-30 hidden flex-col overflow-y-auto border-e border-white/10 p-3 lg:flex"
        @mouseover="$store.sidebar.showTooltip($event)" @focusin="$store.sidebar.showTooltip($event)"
        @mouseout="$store.sidebar.deferHideTooltip()" @focusout="$store.sidebar.deferHideTooltip()">
        <div class="casa-sidebar-heading flex items-center justify-between gap-2">
            <a href="{{ route($dashboardRoute) }}" class="casa-sidebar-logo min-w-0 rounded-xl bg-casa-paper px-2.5 py-2 shadow-casa-card">
                <x-application-logo />
            </a>
            <button type="button" x-cloak class="casa-icon-button border-white/15 bg-white/10 text-white" aria-controls="desktop-workspace-navigation"
                :aria-expanded="!$store.sidebar.collapsed" :aria-label="$store.sidebar.collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :title="$store.sidebar.collapsed ? 'Expand sidebar' : 'Collapse sidebar'" @click="$store.sidebar.toggle()">
                <svg class="size-5" :class="{ 'rotate-180': $store.sidebar.collapsed }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 4H4v16h5M15 8l-4 4 4 4M11 12h9" />
                </svg>
            </button>
        </div>

        <div class="casa-sidebar-account mt-3 rounded-xl border border-white/10 bg-white/[0.065] p-3">
            <div class="flex items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full border border-casa-brass/35 bg-casa-brass/15 text-sm font-extrabold tracking-[0.04em] text-casa-sand">{{ $initials }}</span>
                <span class="casa-sidebar-expanded-only min-w-0">
                    <span class="block text-sm font-extrabold uppercase tracking-[0.05em] text-casa-brass-light">{{ $roleLabel }}</span>
                    <span class="mt-1 block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                    <span class="mt-0.5 block truncate text-sm text-white/65">{{ $user->email }}</span>
                </span>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            @foreach ($navGroups as $group)
                <section aria-label="{{ $group['label'] }} navigation">
                    <p class="casa-sidebar-expanded-only px-3 text-sm font-extrabold uppercase tracking-[0.05em] text-white/60">{{ $group['label'] }}</p>
                    <div class="mt-2 space-y-1">
                        @foreach ($group['items'] as $item)
                            @php $isActive = request()->routeIs(...(array) $item['active']); @endphp
                            <a href="{{ route($item['route']) }}" data-sidebar-label="{{ $item['label'] }}" @class(['casa-nav-link w-full', 'casa-nav-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @if($item['preload'] ?? false) data-turbo-preload @endif @if($item['booking_schedule'] ?? false) data-booking-schedule-link @endif>
                                <x-nav-icon :name="$item['icon']" />
                                <span class="casa-sidebar-link-label">{{ $item['label'] }}</span>
                                @if ($item['booking_schedule'] ?? false)
                                    <span data-booking-notification-badge @class(['ms-auto rounded-full bg-casa-brass px-2 py-0.5 text-xs font-black text-casa-ink', 'hidden' => ($item['badge'] ?? 0) === 0]) aria-label="{{ $item['badge'] }} unread booking notifications"><span data-booking-notification-badge-value>{{ min((int) $item['badge'], 99) }}{{ $item['badge'] > 99 ? '+' : '' }}</span></span>
                                @elseif (($item['badge'] ?? 0) > 0)
                                    <span class="ms-auto rounded-full bg-casa-brass px-2 py-0.5 text-xs font-black text-casa-ink" aria-label="{{ $item['badge'] }} unresolved server errors">{{ min((int) $item['badge'], 99) }}{{ $item['badge'] > 99 ? '+' : '' }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endforeach

            <section aria-label="Account navigation">
                <p class="casa-sidebar-expanded-only px-3 text-sm font-extrabold uppercase tracking-[0.05em] text-white/60">Account</p>
                <div class="mt-2 space-y-1">
                    @foreach ($accountLinks as $item)
                        @php $isActive = request()->routeIs(...(array) $item['active']); @endphp
                        <a href="{{ route($item['route']) }}" data-sidebar-label="{{ $item['label'] }}" @class(['casa-nav-link w-full', 'casa-nav-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @if($item['preload'] ?? false) data-turbo-preload @endif>
                            <x-nav-icon :name="$item['icon']" />
                            <span class="casa-sidebar-link-label">{{ $item['label'] }}</span>
                            @if (($item['badge'] ?? 0) > 0)
                                <span class="ms-auto rounded-full bg-casa-brass px-2 py-0.5 text-xs font-black text-casa-ink" aria-label="{{ $item['badge'] }} unresolved server errors">{{ min((int) $item['badge'], 99) }}{{ $item['badge'] > 99 ? '+' : '' }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="mt-auto pt-4">
            <p class="casa-sidebar-expanded-only mb-2 px-2 text-sm leading-5 text-white/65">Open every day<br><span class="font-bold text-white/80">1:00 PM to 12:00 MN</span></p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" data-sidebar-label="Log out" class="casa-sidebar-logout casa-button-secondary w-full border-white/15 bg-white/8 text-white hover:bg-white/14 hover:text-white">
                    <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4H4v16h5M15 8l4 4-4 4M9 12h10" /></svg>
                    <span class="casa-sidebar-link-label">Log out</span>
                </button>
            </form>
        </div>
    </aside>

    <div x-cloak x-show="$store.sidebar.tooltip" class="casa-sidebar-tooltip" aria-hidden="true"
        :style="{ top: ($store.sidebar.tooltip?.top ?? 0) + 'px', left: ($store.sidebar.tooltip?.left ?? 0) + 'px' }"
        @mouseenter="$store.sidebar.keepTooltip()" @mouseleave="$store.sidebar.deferHideTooltip()"
        x-text="$store.sidebar.tooltip?.label"></div>

    <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 lg:hidden" style="display: none;">
        <div class="absolute inset-0 bg-casa-charcoal/72 backdrop-blur-sm" @click="open = false"></div>
        <aside id="mobile-workspace-navigation" x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full" class="casa-wood-panel absolute inset-y-0 start-0 flex w-[min(19rem,88vw)] flex-col overflow-y-auto p-3 shadow-casa-lift" aria-label="Mobile workspace navigation">
            <div class="flex items-center justify-between gap-4">
                <a href="{{ route($dashboardRoute) }}" class="rounded-xl bg-casa-paper px-2 py-1.5" @click="open = false">
                    <x-application-logo class="origin-left scale-[0.72]" />
                </a>
                <button type="button" class="casa-icon-button border-white/15 bg-white/10 text-white" aria-label="Close navigation" @click="open = false">
                    <svg class="size-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>

            <div class="mt-4 rounded-xl border border-white/10 bg-white/[0.065] p-3">
                <p class="text-sm font-extrabold uppercase tracking-[0.05em] text-casa-brass-light">{{ $roleLabel }}</p>
                <p class="mt-1 truncate text-sm font-semibold text-white">{{ $user->name }}</p>
                <p class="mt-0.5 truncate text-sm text-white/65">{{ $user->email }}</p>
            </div>

            <div class="mt-4 space-y-4">
                @foreach ($navGroups as $group)
                    <section aria-label="{{ $group['label'] }} navigation">
                        <p class="px-3 text-sm font-extrabold uppercase tracking-[0.05em] text-white/60">{{ $group['label'] }}</p>
                        <div class="mt-2 space-y-1">
                            @foreach ($group['items'] as $item)
                                @php $isActive = request()->routeIs(...(array) $item['active']); @endphp
                                <a href="{{ route($item['route']) }}" @class(['casa-nav-link w-full', 'casa-nav-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @if($item['booking_schedule'] ?? false) data-booking-schedule-link @endif @click="open = false">
                                    <x-nav-icon :name="$item['icon']" />
                                    <span>{{ $item['label'] }}</span>
                                    @if ($item['booking_schedule'] ?? false)
                                        <span data-booking-notification-badge @class(['ms-auto rounded-full bg-casa-brass px-2 py-0.5 text-xs font-black text-casa-ink', 'hidden' => ($item['badge'] ?? 0) === 0]) aria-label="{{ $item['badge'] }} unread booking notifications"><span data-booking-notification-badge-value>{{ min((int) $item['badge'], 99) }}{{ $item['badge'] > 99 ? '+' : '' }}</span></span>
                                    @elseif (($item['badge'] ?? 0) > 0)
                                        <span class="ms-auto rounded-full bg-casa-brass px-2 py-0.5 text-xs font-black text-casa-ink" aria-label="{{ $item['badge'] }} unresolved server errors">{{ min((int) $item['badge'], 99) }}{{ $item['badge'] > 99 ? '+' : '' }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <section aria-label="Account navigation">
                    <p class="px-3 text-sm font-extrabold uppercase tracking-[0.05em] text-white/60">Account</p>
                    <div class="mt-2 space-y-1">
                        @foreach ($accountLinks as $item)
                            @php $isActive = request()->routeIs(...(array) $item['active']); @endphp
                            <a href="{{ route($item['route']) }}" @class(['casa-nav-link w-full', 'casa-nav-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @click="open = false">
                                <x-nav-icon :name="$item['icon']" />
                                <span>{{ $item['label'] }}</span>
                                @if (($item['badge'] ?? 0) > 0)
                                    <span class="ms-auto rounded-full bg-casa-brass px-2 py-0.5 text-xs font-black text-casa-ink" aria-label="{{ $item['badge'] }} unresolved server errors">{{ min((int) $item['badge'], 99) }}{{ $item['badge'] > 99 ? '+' : '' }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-auto pt-6" data-turbo="false">
                @csrf
                <button type="submit" class="casa-button-secondary w-full border-white/15 bg-white/10 text-white hover:bg-white/15 hover:text-white">Log out</button>
            </form>
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
