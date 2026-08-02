@php
    $user = Auth::user();
    $dashboardRoute = $user->homeRouteName();
    $isCustomer = $user->isCustomer();

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
                'label' => 'Manage',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
                    ['label' => 'Schedule', 'icon' => 'calendar', 'route' => 'admin.appointments.index', 'active' => 'admin.appointments.*', 'preload' => true],
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'admin.customers.index', 'active' => 'admin.customers.*'],
                    ['label' => 'Team & Services', 'icon' => 'team', 'route' => 'admin.staff.index', 'active' => ['admin.staff.*', 'admin.services.*'], 'preload' => true],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'admin.transactions.index', 'active' => 'admin.transactions.*'],
                    ['label' => 'Commissions', 'icon' => 'payments', 'route' => 'admin.commissions.index', 'active' => 'admin.commissions.*'],
                    ['label' => 'Attendance', 'icon' => 'team', 'route' => 'admin.attendance.index', 'active' => 'admin.attendance.*'],
                    ['label' => 'Insights', 'icon' => 'insights', 'route' => 'admin.promotions.index', 'active' => ['admin.promotions.*', 'admin.feedback.*', 'admin.reports.*'], 'preload' => true],
                ],
            ],
        ],
        $user->isStaff() => [
            [
                'label' => 'Today',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'staff.dashboard', 'active' => 'staff.dashboard'],
                    ['label' => 'Attendance', 'icon' => 'team', 'route' => 'staff.attendance.show', 'active' => 'staff.attendance.*'],
                    ['label' => 'My Schedule', 'icon' => 'calendar', 'route' => 'staff.appointments.index', 'active' => 'staff.appointments.*', 'preload' => true],
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'staff.customers.index', 'active' => 'staff.customers.*', 'preload' => true],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'staff.transactions.index', 'active' => 'staff.transactions.*'],
                    ['label' => 'My Commissions', 'icon' => 'payments', 'route' => 'staff.commissions.index', 'active' => 'staff.commissions.*'],
                    ['label' => 'Feedback', 'icon' => 'feedback', 'route' => 'staff.feedback.index', 'active' => 'staff.feedback.*'],
                ],
            ],
        ],
        $user->isReceptionist() => [
            [
                'label' => 'Front desk',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'reception.dashboard', 'active' => 'reception.dashboard'],
                    ['label' => 'Attendance', 'icon' => 'team', 'route' => 'reception.attendance.station', 'active' => 'reception.attendance.*'],
                    ['label' => 'Appointments', 'icon' => 'calendar', 'route' => 'reception.appointments.index', 'active' => 'reception.appointments.*', 'preload' => true],
                    ['label' => 'Customers', 'icon' => 'customers', 'route' => 'reception.customers.index', 'active' => 'reception.customers.*', 'preload' => true],
                    ['label' => 'Payments', 'icon' => 'payments', 'route' => 'reception.transactions.index', 'active' => 'reception.transactions.*'],
                ],
            ],
        ],
        default => [
            [
                'label' => 'My wellness',
                'items' => [
                    ['label' => 'Appointments', 'icon' => 'calendar', 'route' => 'customer.appointments.index', 'active' => ['customer.appointments.index', 'customer.appointments.show', 'customer.appointments.create']],
                    ['label' => 'Feedback', 'icon' => 'feedback', 'route' => 'customer.feedback.index', 'active' => 'customer.feedback.*', 'preload' => true],
                ],
            ],
        ],
    };

    $accountLinks = $user->isAdmin()
        ? [
            ...($user->isSuperAdmin() ? [['label' => 'User access', 'icon' => 'team', 'route' => 'admin.users.index', 'active' => 'admin.users.*']] : []),
            ['label' => 'Settings', 'icon' => 'settings', 'route' => 'admin.settings.index', 'active' => 'admin.settings.*'],
            ['label' => 'Profile', 'icon' => 'profile', 'route' => 'profile.edit', 'active' => 'profile.*', 'preload' => $isCustomer],
        ]
        : [
            ['label' => 'Profile', 'icon' => 'profile', 'route' => 'profile.edit', 'active' => 'profile.*', 'preload' => $isCustomer],
        ];

    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

<nav x-data="{ open: false }" x-on:keydown.escape.window="open = false" data-role-navigation="{{ $user->role }}">
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

    <aside data-desktop-sidebar class="casa-wood-panel fixed inset-y-0 start-0 z-30 hidden w-64 flex-col overflow-y-auto border-e border-white/10 p-3 lg:flex">
        <a href="{{ route($dashboardRoute) }}" class="rounded-xl bg-casa-paper px-2.5 py-2 shadow-casa-card">
            <x-application-logo class="origin-left scale-[0.82]" />
        </a>

        <div class="mt-3 rounded-xl border border-white/10 bg-white/[0.065] p-3">
            <div class="flex items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full border border-casa-brass/35 bg-casa-brass/15 text-sm font-extrabold tracking-[0.04em] text-casa-sand">{{ $initials }}</span>
                <span class="min-w-0">
                    <span class="block text-sm font-extrabold uppercase tracking-[0.05em] text-casa-brass-light">{{ $roleLabel }}</span>
                    <span class="mt-1 block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                    <span class="mt-0.5 block truncate text-sm text-white/65">{{ $user->email }}</span>
                </span>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            @foreach ($navGroups as $group)
                <section aria-label="{{ $group['label'] }} navigation">
                    <p class="px-3 text-sm font-extrabold uppercase tracking-[0.05em] text-white/60">{{ $group['label'] }}</p>
                    <div class="mt-2 space-y-1">
                        @foreach ($group['items'] as $item)
                            @php $isActive = request()->routeIs(...(array) $item['active']); @endphp
                            <a href="{{ route($item['route']) }}" @class(['casa-nav-link w-full', 'casa-nav-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @if($item['preload'] ?? false) data-turbo-preload @endif>
                                <x-nav-icon :name="$item['icon']" />
                                <span>{{ $item['label'] }}</span>
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
                        <a href="{{ route($item['route']) }}" @class(['casa-nav-link w-full', 'casa-nav-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @if($item['preload'] ?? false) data-turbo-preload @endif>
                            <x-nav-icon :name="$item['icon']" />
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="mt-auto pt-4">
            <p class="mb-2 px-2 text-sm leading-5 text-white/65">Open every day<br><span class="font-bold text-white/80">1:00 PM to 12:00 MN</span></p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="casa-button-secondary w-full border-white/15 bg-white/8 text-white hover:bg-white/14 hover:text-white">
                    Log out
                </button>
            </form>
        </div>
    </aside>

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
                                <a href="{{ route($item['route']) }}" @class(['casa-nav-link w-full', 'casa-nav-link-active' => $isActive]) @if($isActive) aria-current="page" @endif @click="open = false">
                                    <x-nav-icon :name="$item['icon']" />
                                    <span>{{ $item['label'] }}</span>
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
                <span>Appointments</span>
            </a>
            <a href="{{ route('customer.feedback.index') }}" @class(['casa-mobile-dock-link', 'casa-mobile-dock-link-active' => request()->routeIs('customer.feedback.*')]) @if(request()->routeIs('customer.feedback.*')) aria-current="page" @endif>
                <x-nav-icon name="feedback" class="size-4" />
                <span>Feedback</span>
            </a>
            <a href="{{ route('profile.edit') }}" @class(['casa-mobile-dock-link', 'casa-mobile-dock-link-active' => request()->routeIs('profile.*')]) @if(request()->routeIs('profile.*')) aria-current="page" @endif>
                <x-nav-icon name="profile" class="size-4" />
                <span>Profile</span>
            </a>
        </nav>
    @endif
</nav>
