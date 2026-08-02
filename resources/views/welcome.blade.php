<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Casa Paraiso Body and Wellness Spa offers signature full-body massage packages and request-first appointments every day from 1:00 PM to 12:00 MN.">

        <title>{{ config('app.name', 'Casa Paraiso') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:600,700|manrope:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @php
            $packageDetailsBySlug = collect(config('casa.service_packages', []))->keyBy('slug');
            $businessHours = config('casa.business_hours', []);
            $homeUrl = auth()->check() ? route(auth()->user()->homeRouteName()) : null;
            $startingRate = $services->min('price');
        @endphp

        <x-page-loading />

        <a href="#main-content" class="sr-only rounded-lg bg-casa-paper px-4 py-3 font-bold text-casa-cacao focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-[100]">Skip to content</a>

        <div class="casa-page casa-landing min-h-screen overflow-hidden">
            <header class="casa-landing-header fixed inset-x-0 top-0 z-50 border-b border-casa-border/70 bg-casa-paper/92 backdrop-blur-xl" data-landing-header="fixed">
                <div class="mx-auto flex max-w-[90rem] items-center justify-between gap-2 px-4 py-3 sm:gap-5 sm:px-6 lg:px-8">
                    <a href="/" class="rounded-xl bg-white px-2 py-1">
                        <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" class="h-10 w-32 object-cover object-center sm:h-12 sm:w-44">
                    </a>

                    <nav class="hidden items-center gap-7 text-sm font-bold text-casa-muted lg:flex" aria-label="Public navigation">
                        <a href="#treatments" class="inline-flex min-h-[44px] items-center transition hover:text-casa-cacao">Treatments</a>
                        <a href="#how-it-works" class="inline-flex min-h-[44px] items-center transition hover:text-casa-cacao">How it works</a>
                        <a href="#visit" class="inline-flex min-h-[44px] items-center transition hover:text-casa-cacao">Visit hours</a>
                    </nav>

                    @if (Route::has('login'))
                        <nav class="flex items-center gap-2 sm:gap-3" aria-label="Account navigation">
                            @auth
                                <a href="{{ $homeUrl }}" class="casa-button-primary">{{ __('Open workspace') }}</a>
                            @else
                                <a href="{{ route('login') }}" class="casa-button-primary">{{ __('Login') }}</a>
                            @endauth
                        </nav>
                    @endif
                </div>
            </header>

            <main id="main-content" class="casa-landing-main">
                <section class="casa-landing-screen-section relative" data-landing-screen-section>
                    <div class="absolute -start-24 top-12 size-80 rounded-full bg-casa-brass/8 blur-3xl"></div>
                    <div class="mx-auto grid max-w-[90rem] gap-10 px-4 py-12 sm:px-6 lg:grid-cols-[minmax(0,0.88fr)_minmax(34rem,1.12fr)] lg:items-center lg:px-8 lg:py-20 xl:gap-16 xl:py-24">
                        <div class="relative z-10">
                            <p class="casa-eyebrow">{{ $applicationSettings->business_name }}</p>
                            <h1 class="mt-6 max-w-3xl font-editorial text-5xl font-semibold leading-[0.93] text-casa-ink sm:text-6xl lg:text-7xl xl:text-[5.5rem]">
                                Let the day<br><span class="italic text-casa-cacao">soften here.</span>
                            </h1>
                            <p class="mt-7 max-w-xl text-base leading-8 text-casa-muted sm:text-lg">
                                Thoughtful full-body massage rituals, prepared around your pace and confirmed with care by our spa team.
                            </p>

                            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                                @auth
                                    <a href="{{ $homeUrl }}" class="casa-button-primary">{{ __('Open your workspace') }}</a>
                                @else
                                    <a href="{{ route('login') }}" class="casa-button-primary">{{ __('Request an appointment') }}</a>
                                    <a href="#treatments" class="casa-button-secondary">{{ __('Explore treatments') }}</a>
                                @endauth
                            </div>

                            <p class="mt-8 font-editorial text-3xl font-semibold italic leading-tight text-casa-palm sm:text-4xl">
                                “{{ config('casa.marketing_line') }}”
                            </p>

                            <dl class="mt-9 grid max-w-xl grid-cols-2 gap-px overflow-hidden rounded-2xl border border-casa-border bg-casa-border sm:grid-cols-3">
                                <div class="bg-casa-paper p-4">
                                    <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.13em] text-casa-muted">Open</dt>
                                    <dd class="mt-1.5 text-sm font-bold text-casa-text">Every day</dd>
                                </div>
                                <div class="bg-casa-paper p-4">
                                    <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.13em] text-casa-muted">Hours</dt>
                                    <dd class="mt-1.5 text-sm font-bold text-casa-text">1 PM–12 MN</dd>
                                </div>
                                <div class="col-span-2 bg-casa-paper p-4 sm:col-span-1">
                                    <dt class="text-[0.65rem] font-extrabold uppercase tracking-[0.13em] text-casa-muted">From</dt>
                                    <dd class="mt-1.5 text-sm font-bold text-casa-text">{{ $startingRate === null ? __('Contact us') : 'PHP '.number_format((float) $startingRate, 2) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="relative mx-auto w-full max-w-3xl">
                            <svg class="absolute -end-5 -top-8 z-10 h-28 w-28 text-casa-palm/70 sm:h-36 sm:w-36" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                                <path d="M24 100C45 71 66 49 101 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M48 73C31 72 24 60 23 48c17 0 29 9 25 25Zm19-20C55 40 57 25 66 15c12 12 13 27 1 38Zm17-15c1-16 12-25 24-28 3 16-5 27-24 28Zm-50 52c-13-2-21-11-22-22 13-1 23 7 22 22Z" fill="currentColor" opacity=".42"/>
                            </svg>
                            <div class="casa-canopy aspect-[4/4.25] sm:aspect-[5/4.8] lg:aspect-[6/5.3]">
                                <picture>
                                    <source media="(max-width: 768px)" srcset="{{ asset('images/spa/spa-hero-960.webp') }}">
                                    <img src="{{ asset('images/spa/spa-hero-1600.webp') }}" srcset="{{ asset('images/spa/spa-hero-960.webp') }} 960w, {{ asset('images/spa/spa-hero-1600.webp') }} 1600w" sizes="(max-width: 1024px) 92vw, 52vw" width="1600" height="854" alt="A warm linen compress being prepared in a tropical spa treatment room" class="object-[62%_center]" fetchpriority="high">
                                </picture>
                            </div>
                            <div class="absolute -bottom-5 start-4 max-w-[17rem] rounded-2xl border border-white/55 bg-casa-paper/94 p-4 shadow-casa-lift backdrop-blur sm:start-8 sm:p-5">
                                <p class="text-[0.65rem] font-extrabold uppercase tracking-[0.14em] text-casa-cacao">Request-first care</p>
                                <p class="mt-2 text-sm font-semibold leading-6 text-casa-text">Choose your preferred visit. Our team confirms the final schedule.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="treatments" class="casa-landing-screen-section border-y border-casa-border/75 bg-casa-sand/65 py-16 sm:py-20" data-landing-screen-section>
                    <div class="mx-auto max-w-[90rem] px-4 sm:px-6 lg:px-8">
                        <div class="grid gap-6 lg:grid-cols-[minmax(0,0.72fr)_minmax(0,1.28fr)] lg:items-end">
                            <div>
                                <p class="casa-eyebrow">Signature treatments</p>
                                <h2 class="mt-5 font-editorial text-5xl font-semibold leading-none text-casa-ink sm:text-6xl">Treatments made for your return to yourself.</h2>
                            </div>
                            <p class="max-w-2xl text-sm leading-7 text-casa-muted lg:justify-self-end sm:text-base">Each ritual keeps its time, inclusions, and price clear before you book an appointment. Add-ons can be coordinated with our team before your visit.</p>
                        </div>

                        <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            @foreach ($services as $service)
                                @php
                                    $durationParts = [];
                                    $hours = intdiv($service->duration_minutes, 60);
                                    $minutes = $service->duration_minutes % 60;

                                    if ($hours > 0) {
                                        $durationParts[] = $hours.' '.($hours === 1 ? __('Hour') : __('Hours'));
                                    }

                                    if ($minutes > 0) {
                                        $durationParts[] = $minutes.' '.($minutes === 1 ? __('Minute') : __('Minutes'));
                                    }

                                    $inclusions = $packageDetailsBySlug->get($service->slug)['includes'] ?? [];
                                @endphp
                                <article class="casa-editorial-card group flex min-h-full flex-col p-6 transition duration-200 hover:-translate-y-1 hover:border-casa-brass/55 sm:p-7">
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="font-editorial text-4xl font-semibold text-casa-brass/70">{{ str_pad((string) ($loop->index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                        <span class="rounded-full border border-casa-border bg-casa-sand/55 px-3 py-1.5 text-[0.65rem] font-extrabold uppercase tracking-[0.1em] text-casa-muted">{{ implode(' ', $durationParts) }}</span>
                                    </div>
                                    <h3 class="mt-8 font-editorial text-3xl font-semibold text-casa-cacao">{{ $service->name }}</h3>
                                    <p class="mt-2 text-xl font-extrabold text-casa-palm">PHP {{ number_format((float) $service->price, 2) }}</p>
                                    <p class="mt-5 text-sm leading-7 text-casa-muted">{{ $service->description }}</p>
                                    @if ($inclusions !== [])
                                        <div class="mt-6 flex flex-wrap gap-2">
                                            @foreach ($inclusions as $inclusion)
                                                <span class="rounded-full border border-casa-border bg-casa-paper px-3 py-1.5 text-[0.68rem] font-bold text-casa-muted">{{ $inclusion }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section id="how-it-works" class="casa-landing-screen-section py-16 sm:py-24" data-landing-screen-section>
                    <div class="mx-auto grid max-w-[90rem] gap-10 px-4 sm:px-6 lg:grid-cols-[minmax(20rem,0.82fr)_minmax(0,1.18fr)] lg:items-center lg:px-8 xl:gap-20">
                        <div class="casa-ritual-image aspect-[4/3] lg:aspect-[4/4.1]">
                            <picture>
                                <source media="(max-width: 768px)" srcset="{{ asset('images/spa/spa-ritual-800.webp') }}">
                                <img src="{{ asset('images/spa/spa-ritual-1400.webp') }}" srcset="{{ asset('images/spa/spa-ritual-800.webp') }} 800w, {{ asset('images/spa/spa-ritual-1400.webp') }} 1400w" sizes="(max-width: 1024px) 92vw, 42vw" width="1400" height="933" alt="Botanical oil, warm towels, and massage stones being prepared for a spa ritual" class="h-full w-full object-cover" loading="lazy">
                            </picture>
                        </div>

                        <div>
                            <p class="casa-eyebrow">A considered booking flow</p>
                            <h2 class="mt-5 max-w-2xl font-editorial text-5xl font-semibold leading-[0.98] text-casa-ink sm:text-6xl">Simple to request.<br>Personal to confirm.</h2>
                            <p class="mt-6 max-w-xl text-sm leading-7 text-casa-muted sm:text-base">A request starts the conversation. Your booking becomes final after the team checks the service, therapist, and schedule.</p>

                            <ol class="mt-9 space-y-4">
                                <li class="grid grid-cols-[3rem_minmax(0,1fr)] gap-4 rounded-2xl border border-casa-border bg-casa-paper p-5">
                                    <span class="grid size-12 place-items-center rounded-full bg-casa-cacao text-sm font-extrabold text-white">01</span>
                                    <span><strong class="block text-base text-casa-text">Choose your ritual and preferred time.</strong><span class="mt-1 block text-sm leading-6 text-casa-muted">Available dates and times are shown from active therapist schedules.</span></span>
                                </li>
                                <li class="grid grid-cols-[3rem_minmax(0,1fr)] gap-4 rounded-2xl border border-casa-border bg-casa-paper p-5">
                                    <span class="grid size-12 place-items-center rounded-full bg-casa-palm text-sm font-extrabold text-white">02</span>
                                    <span><strong class="block text-base text-casa-text">Your visit is confirmed.</strong><span class="mt-1 block text-sm leading-6 text-casa-muted">Booking immediately reserves an eligible therapist and the selected schedule.</span></span>
                                </li>
                                <li class="grid grid-cols-[3rem_minmax(0,1fr)] gap-4 rounded-2xl border border-casa-border bg-casa-paper p-5">
                                    <span class="grid size-12 place-items-center rounded-full bg-casa-brass text-sm font-extrabold text-casa-charcoal">03</span>
                                    <span><strong class="block text-base text-casa-text">Return to your account for confirmation.</strong><span class="mt-1 block text-sm leading-6 text-casa-muted">Your appointment status and wellness history stay organized in one place.</span></span>
                                </li>
                            </ol>
                        </div>
                    </div>
                </section>

                <section id="visit" class="casa-landing-screen-section bg-casa-charcoal py-16 text-white sm:py-20" data-landing-screen-section>
                    <div class="mx-auto grid max-w-[90rem] gap-6 px-4 sm:px-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(20rem,0.85fr)] lg:px-8">
                        <div class="rounded-[28px] border border-white/10 bg-white/[0.055] p-6 sm:p-8">
                            <p class="casa-eyebrow text-casa-brass-light before:bg-casa-brass">Optional additions</p>
                            <h2 class="mt-5 font-editorial text-4xl font-semibold sm:text-5xl">Make the ritual your own.</h2>
                            <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($addons as $addon)
                                    <div class="rounded-2xl border border-white/12 bg-black/10 p-4">
                                        <p class="font-bold text-white">{{ $addon['name'] }}</p>
                                        <p class="mt-1 text-sm font-extrabold text-casa-brass-light">PHP {{ number_format((float) $addon['price'], 2) }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <p class="mt-6 max-w-2xl text-sm leading-7 text-white/85">Add-ons are shown for reference and can be coordinated with our team before your confirmed appointment.</p>
                        </div>

                        <aside class="rounded-[28px] bg-casa-paper p-7 text-casa-text sm:p-9">
                            <p class="casa-eyebrow">Visit hours</p>
                            <h2 class="mt-5 font-editorial text-5xl font-semibold leading-none text-casa-cacao">{{ $businessHours['summary'] ?? __('Open every day') }}</h2>
                            <p class="mt-5 text-2xl font-extrabold text-casa-palm">{{ $businessHours['window'] ?? __('1:00 PM to 12:00 MN') }}</p>
                            <div class="casa-divider my-7"></div>
                            <p class="casa-eyebrow">Find us</p>
                            <p class="mt-3 text-base font-extrabold leading-7 text-casa-text">{{ $applicationSettings->business_address }}</p>
                            @if (filled($applicationSettings->location_landmarks))
                                <p class="mt-2 text-sm leading-6 text-casa-muted">{{ $applicationSettings->location_landmarks }}</p>
                            @endif
                            <div class="mt-5 grid gap-2">
                                @if (filled($applicationSettings->messenger_url))
                                    <a href="{{ $applicationSettings->messenger_url }}" target="_blank" rel="noopener noreferrer" class="casa-button-primary w-full">{{ __('Message us') }}</a>
                                @endif
                                @if (filled($applicationSettings->facebook_url))
                                    <a href="{{ $applicationSettings->facebook_url }}" target="_blank" rel="noopener noreferrer" class="casa-button-secondary w-full">{{ __('Visit Facebook') }}</a>
                                @endif
                                @if (filled($applicationSettings->map_url))
                                    <a href="{{ $applicationSettings->map_url }}" target="_blank" rel="noopener noreferrer" class="casa-button-secondary w-full">{{ __('Get directions') }}</a>
                                @endif
                            </div>
                            <div class="casa-divider my-7"></div>
                            <p class="font-editorial text-3xl font-semibold italic leading-tight text-casa-cacao">{{ config('casa.marketing_line') }}</p>
                            @guest
                                <a href="{{ route('login') }}" class="casa-button-primary mt-7 w-full">{{ __('Request your visit') }}</a>
                            @endguest
                        </aside>
                    </div>
                </section>

                <section class="py-16 sm:py-20">
                    <div class="mx-auto max-w-[90rem] px-4 sm:px-6 lg:px-8">
                        <div class="grid gap-4 md:grid-cols-3">
                            <article class="casa-card p-6 sm:p-7">
                                <x-nav-icon name="calendar" class="size-6 text-casa-cacao" />
                                <h2 class="mt-5 text-lg font-extrabold text-casa-text">Clear appointment status</h2>
                                <p class="mt-3 text-sm leading-7 text-casa-muted">Requests, confirmed visits, and completed care remain easy to follow.</p>
                            </article>
                            <article class="casa-card p-6 sm:p-7">
                                <x-nav-icon name="team" class="size-6 text-casa-cacao" />
                                <h2 class="mt-5 text-lg font-extrabold text-casa-text">Therapist-aware scheduling</h2>
                                <p class="mt-3 text-sm leading-7 text-casa-muted">The spa team checks availability before every booking becomes final.</p>
                            </article>
                            <article class="casa-card p-6 sm:p-7">
                                <x-nav-icon name="feedback" class="size-6 text-casa-cacao" />
                                <h2 class="mt-5 text-lg font-extrabold text-casa-text">Care that keeps listening</h2>
                                <p class="mt-3 text-sm leading-7 text-casa-muted">Completed visits can be reviewed through thoughtful service feedback.</p>
                            </article>
                        </div>
                    </div>
                </section>
            </main>

            <footer class="border-t border-casa-border bg-casa-paper">
                <div class="mx-auto flex max-w-[90rem] flex-col gap-6 px-4 py-8 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                    <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" class="h-14 w-52 object-cover object-center">
                    <div class="text-sm leading-6 text-casa-muted md:text-right">
                        <p class="font-bold text-casa-text">Open every day · 1:00 PM to 12:00 MN</p>
                        <p>{{ $applicationSettings->business_address }}</p>
                        @if (filled($applicationSettings->map_url))
                            <a href="{{ $applicationSettings->map_url }}" target="_blank" rel="noopener noreferrer" class="font-bold text-casa-palm underline underline-offset-4">{{ __('Get directions') }}</a>
                        @endif
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
