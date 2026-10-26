@php
    $applicationName = config('app.name', 'Casa Paraiso');
    $headerTitle = null;

    if (isset($header) && preg_match('/<h1\\b[^>]*>(.*?)<\\/h1>/is', (string) $header, $matches) === 1) {
        $headerTitle = \Illuminate\Support\Str::of(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'))->squish()->toString();
    }

    $documentTitle = $pageTitle ?? $headerTitle;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $documentTitle ? $documentTitle.' · '.$applicationName : $applicationName }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:600,700|manrope:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" data-workspace-role="{{ Auth::user()?->role }}">
        <a href="#main-content" class="sr-only rounded-lg bg-casa-paper px-4 py-3 font-bold text-casa-cacao focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[100]">Skip to main content</a>
        @php
            $isCustomer = Auth::user()?->isCustomer() ?? false;
        @endphp

        <x-page-loading />

        <div class="casa-page casa-app-page">
            @include('layouts.navigation')
            <x-toast-stack />

            <x-modal-host />

            <div @class([
                'min-h-screen',
                'pb-24 lg:pb-0' => $isCustomer,
            ])>
                <!-- Page Heading -->
                @isset($header)
                    <header data-page-header class="casa-glass-bar border-b">
                        <div class="mx-auto max-w-[90rem] px-4 py-4 sm:px-5 lg:px-6">
                            <x-page-heading :variant="$isCustomer ? 'editorial' : 'operational'">
                                {{ $header }}
                            </x-page-heading>
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                @php
                    // Pages merged under one drawer link share a tab row; each keeps its own route.
                    $sectionTabGroups = [
                        ['label' => __('Payments sections'), 'tabs' => [
                            ['label' => __('Payments'), 'route' => 'admin.transactions.index', 'active' => 'admin.transactions.index'],
                            ['label' => __('Commissions'), 'route' => 'admin.commissions.index', 'active' => 'admin.commissions.index'],
                        ]],
                        ['label' => __('Insights sections'), 'tabs' => [
                            ['label' => __('Reports'), 'route' => 'admin.reports.index', 'active' => 'admin.reports.index'],
                            ['label' => __('Feedback'), 'route' => 'admin.feedback.index', 'active' => 'admin.feedback.index'],
                            ['label' => __('Customer rewards'), 'route' => 'admin.promotions.index', 'active' => 'admin.promotions.index'],
                        ]],
                        ['label' => __('Payments sections'), 'tabs' => [
                            ['label' => __('Payments'), 'route' => 'staff.transactions.index', 'active' => 'staff.transactions.index'],
                            ['label' => __('My commissions'), 'route' => 'staff.commissions.index', 'active' => 'staff.commissions.index'],
                        ]],
                    ];
                    $sectionTabs = collect($sectionTabGroups)->first(fn ($group) => request()->routeIs(...collect($group['tabs'])->pluck('active')->all()));
                @endphp

                <main id="main-content" data-page-content tabindex="-1" class="mx-auto max-w-[90rem] px-4 py-4 sm:px-5 lg:px-6">
                    @if ($sectionTabs)
                        <x-section-tabs :tabs="$sectionTabs['tabs']" :label="$sectionTabs['label']" class="mb-4" />
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>
        @stack('scripts')
    </body>
</html>
