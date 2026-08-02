<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Casa Paraiso') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:600,700|manrope:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased" data-workspace-role="{{ Auth::user()?->role }}">
        <a href="#main-content" class="sr-only rounded-lg bg-casa-paper px-4 py-3 font-bold text-casa-cacao focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[100]">Skip to main content</a>
        @php
            $usesSidebar = Auth::check();
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
                'lg:ps-64' => $usesSidebar,
            ])>
                <!-- Page Heading -->
                @isset($header)
                    <header data-page-header class="border-b border-casa-border/80 bg-casa-paper/90 backdrop-blur-xl">
                        <div class="mx-auto max-w-[90rem] px-4 py-4 sm:px-5 lg:px-6">
                            <x-page-heading :variant="$isCustomer ? 'editorial' : 'operational'">
                                {{ $header }}
                            </x-page-heading>
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main id="main-content" data-page-content class="mx-auto max-w-[90rem] px-4 py-4 sm:px-5 lg:px-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
