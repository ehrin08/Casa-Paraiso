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
    <body @class([
        'font-sans text-casa-text antialiased',
        'lg:h-dvh lg:overflow-hidden' => $fitDesktopViewport,
    ])>
        <a href="#main-content" class="sr-only rounded-lg bg-casa-paper px-4 py-3 font-bold text-casa-cacao focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[100]">Skip to main content</a>
        <x-page-loading />
        <x-toast-stack />

        <div
            data-guest-viewport="{{ $fitDesktopViewport ? 'desktop-fixed' : 'fluid' }}"
            @class([
                'grid min-h-screen bg-casa-bg lg:grid-cols-[minmax(0,1.12fr)_minmax(430px,0.7fr)]',
                'lg:h-dvh lg:min-h-0 lg:overflow-hidden' => $fitDesktopViewport,
            ])
        >
            <section @class([
                'relative hidden min-h-screen overflow-hidden bg-casa-charcoal lg:block',
                'lg:h-dvh lg:min-h-0' => $fitDesktopViewport,
            ])>
                <picture class="absolute inset-0">
                    <source media="(max-width: 1200px)" srcset="{{ asset('images/spa/spa-auth-720.webp') }}">
                    <img src="{{ asset('images/spa/spa-auth-1024.webp') }}" alt="" class="h-full w-full object-cover" fetchpriority="high">
                </picture>
                <div class="absolute inset-0 bg-gradient-to-t from-casa-charcoal via-casa-charcoal/22 to-casa-charcoal/15"></div>

                <div @class([
                    'relative z-10 flex min-h-screen flex-col justify-between p-10 xl:p-14',
                    'lg:h-dvh lg:min-h-0' => $fitDesktopViewport,
                ])>
                    <a href="/" class="inline-flex w-fit rounded-2xl bg-casa-paper p-3 shadow-casa-lift">
                        <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" class="h-16 w-60 object-cover object-center xl:h-20 xl:w-72">
                    </a>

                    <div class="max-w-xl pb-6">
                        <p class="casa-eyebrow text-casa-sand before:bg-casa-brass">Tropical wellness sanctuary</p>
                        <h1 class="mt-5 font-editorial text-5xl font-semibold leading-[0.96] text-white xl:text-6xl">
                            Your calm place,<br>before you arrive.
                        </h1>
                        <p class="mt-5 max-w-md text-sm leading-7 text-white/78">
                            Book an appointment, follow its status, and keep every step of your Casa Paraiso care close at hand.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-2 text-[0.68rem] font-extrabold uppercase tracking-[0.12em] text-white/72">
                            <span class="rounded-full border border-white/20 bg-black/15 px-3 py-2">Bookings</span>
                            <span class="rounded-full border border-white/20 bg-black/15 px-3 py-2">Wellness care</span>
                            <span class="rounded-full border border-white/20 bg-black/15 px-3 py-2">Open daily</span>
                        </div>
                    </div>
                </div>
            </section>

            <main
                id="main-content"
                data-guest-scroll-region="{{ $fitDesktopViewport ? 'true' : 'false' }}"
                @class([
                    'flex min-h-screen items-center justify-center px-4 py-8 sm:px-8 lg:px-10',
                    'lg:h-dvh lg:min-h-0 lg:items-start lg:overflow-y-auto lg:overscroll-contain' => $fitDesktopViewport,
                ])
            >
                <div @class([
                    'w-full max-w-md',
                    'lg:my-auto' => $fitDesktopViewport,
                ])>
                    <div class="mb-5 overflow-hidden rounded-[24px] bg-casa-charcoal lg:hidden">
                        <div class="relative h-28">
                            <img src="{{ asset('images/spa/spa-auth-720.webp') }}" alt="A warmly lit tropical spa treatment room" class="h-full w-full object-cover object-center">
                            <div class="absolute inset-0 bg-gradient-to-r from-casa-charcoal/65 to-transparent"></div>
                            <a href="/" class="absolute start-4 top-4 inline-flex rounded-xl bg-casa-paper p-2">
                                <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" class="h-10 w-40 object-cover object-center">
                            </a>
                        </div>
                    </div>

                    <div class="casa-editorial-card p-6 sm:p-8">
                        <div class="mb-6">
                            <p class="casa-eyebrow">{{ $eyebrow ?? __('Casa Paraiso') }}</p>
                            <h1 class="mt-3 font-editorial text-4xl font-semibold leading-none text-casa-text">
                                {{ $title ?? __('Welcome') }}
                            </h1>
                            @isset($subtitle)
                                <p class="mt-2 text-sm leading-6 text-casa-muted">{{ $subtitle }}</p>
                            @endisset
                        </div>

                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
