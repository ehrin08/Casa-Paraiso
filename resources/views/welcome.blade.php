<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Casa Paraiso Body and Wellness Spa offers massage treatments with instant appointment confirmation every day from 1:00 PM to 12:00 MN.">
        <title>Casa Paraiso Body and Wellness Spa</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=cormorant-garamond:600,700|manrope:400,500,600,700,800&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="casa-landing-body font-sans antialiased">
        <!-- THESIS: A welcoming, continuous spa visit from reservation to contact.
        OWN-WORLD: Cream, forest green, brown, serif headings, real Casa Paraiso interiors, fine borders and palm linework.
        STORY: Discover published care and treatments, understand booking, then sign in or contact the spa.
        FIRST VIEWPORT: Compact navigation, a two-column booking headline and arched lobby image, then a three-part hours and price strip.
        FORM: The user's supplied homepage reference, adapted to the official logo and current public data.
        FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, and DESIGN.md -->
        @php
            $businessHours = config('casa.business_hours', []);
            $homeUrl = auth()->check() ? route(auth()->user()->homeRouteName()) : null;
            $accountUrl = $homeUrl ?? route('login');
            $startingRate = $services->min('price');
            $organizationChart = $applicationSettings->publicOrganizationChart();
            $showCompanyProfile = $applicationSettings->hasPublicCompanyProfile();
            $hasHistory = filled($applicationSettings->company_history);
            $leaders = collect([
                'owner' => $organizationChart['owner'] ?? null,
                'admin' => $organizationChart['admin'] ?? null,
            ])->filter();
            $hasTeam = $organizationChart['team'] !== [];
            $hasChart = $leaders->isNotEmpty() || $hasTeam;
            $neutralImage = 'images/spa/casa-towel-detail-720.webp';
            $serviceImages = [
                'gaia-touch' => 'images/spa/casa-massage-720.webp',
                'tethys-flow' => 'images/spa/casa-hot-compress-720.webp',
                'hestia-warmth' => 'images/spa/casa-hot-stone-720.webp',
                'aurora-breeze' => 'images/spa/casa-treatment-room-800.webp',
            ];
            $addonImages = [
                'back-massage-30' => 'images/spa/casa-massage-720.webp',
                'hot-compress' => 'images/spa/casa-hot-compress-720.webp',
                'hot-stone' => 'images/spa/casa-hot-stone-720.webp',
                'vip-room' => 'images/spa/casa-treatment-room-800.webp',
                'ventosa' => 'images/spa/casa-ventosa-720.webp',
            ];
        @endphp

        <x-page-loading />
        <a href="#main-content" class="landing-skip-link">Skip to content</a>

        <div class="casa-landing">
            <header class="casa-landing-header" x-data="{ open: false }" x-on:keydown.escape.window="open = false" x-on:click.outside="open = false" data-landing-header="fixed">
                <div class="landing-container landing-header-inner">
                    <a href="{{ url('/') }}" class="landing-logo" aria-label="Casa Paraiso home">
                        <img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" width="500" height="500">
                    </a>
                    <nav class="landing-desktop-nav" aria-label="Public navigation">
                        @if ($showCompanyProfile)<a href="#about">About</a>@endif
                        <a href="#treatments">Treatments</a>
                        <a href="#how-it-works">How it works</a>
                        <a href="#visit">Visit &amp; contact</a>
                    </nav>
                    <div class="landing-header-actions">
                        <a href="{{ $accountUrl }}" class="landing-button landing-button--outline">{{ auth()->check() ? __('Open workspace') : __('Log in') }}</a>
                        <button type="button" class="landing-menu-toggle" aria-label="Open public navigation" aria-controls="landing-mobile-navigation" aria-expanded="false" x-bind:aria-expanded="open.toString()" x-on:click="open = !open" data-landing-mobile-menu>
                            <x-landing-icon name="menu" />
                        </button>
                    </div>
                </div>
                <nav id="landing-mobile-navigation" class="landing-mobile-nav" aria-label="Mobile public navigation" x-cloak x-show="open" style="display: none;">
                    @if ($showCompanyProfile)<a href="#about" x-on:click="open = false">About</a>@endif
                    <a href="#treatments" x-on:click="open = false">Treatments</a>
                    <a href="#how-it-works" x-on:click="open = false">How it works</a>
                    <a href="#visit" x-on:click="open = false">Visit &amp; contact</a>
                    <button type="button" x-on:click="open = false" aria-label="Close public navigation"><x-landing-icon name="close" /> Close menu</button>
                </nav>
            </header>

            <main id="main-content" class="casa-landing-main">
                <section class="landing-hero" aria-labelledby="landing-title" data-landing-section="hero">
                    <x-landing-palm class="landing-palm landing-palm--hero" />
                    <div class="landing-hero-copy">
                        <p class="landing-kicker">Welcome to Casa Paraiso</p>
                        <h1 id="landing-title">{{ config('casa.marketing_line') }}</h1>
                        <p class="landing-hero-description">A calm space to slow down, unwind, and feel cared for.</p>
                        <div class="landing-hero-actions">
                            <a href="{{ $accountUrl }}" class="landing-button landing-button--primary">{{ auth()->check() ? __('Open workspace') : __('Book an appointment') }}</a>
                            <a href="#treatments" class="landing-text-link">Explore treatments <x-landing-icon name="arrow" /></a>
                        </div>
                        @guest
                            <a href="{{ route('login') }}" class="landing-login-note">Log in to book your visit.</a>
                        @endguest
                    </div>
                    <div class="landing-hero-image">
                        <img src="{{ asset('images/spa/casa-lobby-1600.webp') }}" srcset="{{ asset('images/spa/casa-lobby-960.webp') }} 960w, {{ asset('images/spa/casa-lobby-1600.webp') }} 1600w" sizes="(max-width: 767px) 100vw, 53vw" width="1600" height="1200" alt="The real Casa Paraiso reception and lounge with seating and woven pendant lights" loading="eager" fetchpriority="high">
                    </div>
                </section>

                <div class="landing-facts" aria-label="Spa hours and starting price">
                    <div class="landing-container landing-facts-inner">
                        <p><x-landing-icon name="clock" /> <span>{{ $businessHours['summary'] ?? __('Open every day') }}</span></p>
                        <p><x-landing-icon name="moon" /> <span>{{ $businessHours['window'] ?? __('1:00 PM to 12:00 MN') }}</span></p>
                        <p><x-landing-icon name="leaf" /> <span>
                            @if ($startingRate === null)
                                <a href="#visit">Contact us for prices</a>
                            @else
                                {{ $servicesAreBookable ? __('Treatments from') : __('Reference packages from') }} {{ 'PHP '.number_format((float) $startingRate, 2) }}
                            @endif
                        </span></p>
                    </div>
                </div>

                @if ($showCompanyProfile)
                    <section id="about" class="landing-about landing-section" aria-labelledby="landing-about-title" data-landing-section="about" data-landing-company-profile>
                        <div class="landing-container landing-about-grid">
                            <div>
                                <p class="landing-kicker">About Casa Paraiso</p>
                                <h2 id="landing-about-title">A place to pause.</h2>
                                @if ($hasHistory)
                                    <p class="landing-history" data-company-history>{{ $applicationSettings->company_history }}</p>
                                @else
                                    <p>Thoughtful care, a welcoming space, and treatments that help you leave the day behind.</p>
                                @endif
                            </div>
                            <img class="landing-about-image" src="{{ asset('images/spa/casa-towel-detail-1024.webp') }}" srcset="{{ asset('images/spa/casa-towel-detail-720.webp') }} 720w, {{ asset('images/spa/casa-towel-detail-1024.webp') }} 1024w" sizes="(max-width: 767px) 90vw, 45vw" width="1024" height="768" alt="A prepared towel and tray in the Casa Paraiso treatment room" loading="lazy">
                        </div>
                    </section>
                    @if ($hasChart)
                        <section class="landing-team landing-section" aria-labelledby="landing-team-title" data-landing-section="team" data-organization-chart>
                            <x-landing-palm class="landing-palm landing-palm--team-left" />
                            <x-landing-palm class="landing-palm landing-palm--team-right" />
                            <div class="landing-container">
                                <h2 id="landing-team-title">The people behind your care</h2>
                                <div class="landing-org-chart" role="list" aria-label="Casa Paraiso reporting structure">
                                    @if ($leaders->isNotEmpty())
                                        <div class="landing-leaders {{ $hasTeam ? 'landing-leaders--with-team' : '' }}" role="presentation">
                                            @foreach ($leaders as $tier => $node)
                                                <x-landing-org-node :node="$node" data-org-tier="{{ $tier }}" />
                                            @endforeach
                                        </div>
                                    @endif
                                    @if ($hasTeam)
                                        <div class="landing-team-grid {{ $leaders->isNotEmpty() ? 'landing-team-grid--connected' : '' }}" role="presentation">
                                            @foreach ($organizationChart['team'] as $node)
                                                <x-landing-org-node :node="$node" tier="team" data-org-tier="team" />
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>
                    @endif
                @endif

                <section id="treatments" class="landing-treatments landing-section" aria-labelledby="landing-treatments-title" data-landing-section="treatments">
                    <div class="landing-container">
                        <p class="landing-kicker">Treatments</p>
                        <h2 id="landing-treatments-title">Find your moment of rest.</h2>
                        <div class="landing-treatment-grid" @if ($servicesAreBookable) data-landing-catalog-cta @endif>
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
                                    $serviceImage = $serviceImages[$service->slug] ?? $neutralImage;
                                @endphp
                                <article class="landing-treatment-card" data-landing-treatment>
                                    <img src="{{ asset($serviceImage) }}" alt="" width="720" height="480" loading="lazy">
                                    <div class="landing-treatment-content">
                                        <h3>{{ $service->name }}</h3>
                                        <p class="landing-treatment-description">{{ $service->description }}</p>
                                        <div class="landing-treatment-meta">
                                            <p><x-landing-icon name="clock" /> {{ implode(' ', $durationParts) }}</p>
                                            <p class="landing-price">PHP {{ number_format((float) $service->price, 2) }}</p>
                                        </div>
                                        @if ($servicesAreBookable)
                                            <a href="{{ $accountUrl }}" class="landing-text-link" aria-label="{{ auth()->check() ? __('Open workspace for :service', ['service' => $service->name]) : __('Book :service', ['service' => $service->name]) }}">{{ auth()->check() ? __('Open workspace') : __('Book appointment') }} <x-landing-icon name="arrow" /></a>
                                        @else
                                            <a href="#visit" class="landing-text-link" aria-label="{{ __('Ask about :service', ['service' => $service->name]) }}">Ask about availability <x-landing-icon name="arrow" /></a>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        @if (! $servicesAreBookable)
                            <p class="landing-catalog-note" data-landing-catalog-fallback>These signature packages are shown for reference. Contact us for current availability and help choosing your next treatment.</p>
                        @endif
                    </div>
                </section>

                <section id="how-it-works" class="landing-how landing-section" aria-labelledby="landing-how-title" data-landing-section="how-it-works">
                    <div class="landing-container landing-how-grid">
                        <img class="landing-how-image" src="{{ asset('images/spa/casa-treatment-room-1200.webp') }}" srcset="{{ asset('images/spa/casa-treatment-room-800.webp') }} 800w, {{ asset('images/spa/casa-treatment-room-1200.webp') }} 1200w" sizes="(max-width: 767px) 90vw, 43vw" width="1200" height="1200" alt="A prepared massage bed in the real Casa Paraiso treatment room" loading="lazy">
                        <div>
                            <p class="landing-kicker">How it works</p>
                            <h2 id="landing-how-title">Your next visit, made simple.</h2>
                            <ol class="landing-steps">
                                <li><span class="landing-step-number" aria-hidden="true">01</span><div><h3>Choose your treatment</h3><p>Log in, choose a treatment, and pick an available time.</p></div></li>
                                <li><span class="landing-step-number" aria-hidden="true">02</span><div><h3>Your visit is confirmed</h3><p>A successful booking reserves and confirms your appointment immediately.</p></div></li>
                                <li><span class="landing-step-number" aria-hidden="true">03</span><div><h3>Keep everything in one place</h3><p>Review appointments and visit details in your account.</p></div></li>
                            </ol>
                        </div>
                    </div>
                </section>

                @if ($addons->isNotEmpty())
                    <section class="landing-addons landing-section" aria-labelledby="landing-addons-title" data-landing-section="addons">
                        <div class="landing-container">
                            <div class="landing-addons-heading">
                                <div><p class="landing-kicker">Optional additions</p><h2 id="landing-addons-title">A little extra comfort.</h2></div>
                                <p>Optional additions · Coordinate with our spa team</p>
                            </div>
                            <div class="landing-addon-grid">
                                @foreach ($addons as $addon)
                                    <article class="landing-addon" data-landing-addon>
                                        <img src="{{ asset($addonImages[$addon['code']] ?? $neutralImage) }}" alt="" width="720" height="480" loading="lazy">
                                        <h3>{{ $addon['name'] }}</h3>
                                        <p>PHP {{ number_format((float) $addon['price'], 2) }}@if ($addon['duration_minutes'] > 0) <span>· +{{ $addon['duration_minutes'] }} minutes</span>@endif</p>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                <section id="visit" class="landing-visit" aria-labelledby="landing-visit-title" data-landing-section="visit">
                    <div class="landing-visit-copy">
                        <p class="landing-kicker">Visit &amp; contact</p>
                        <h2 id="landing-visit-title">We look forward to welcoming you.</h2>
                        <div class="landing-contact-details">
                            <p><x-landing-icon name="clock" /><span>{{ $businessHours['summary'] ?? __('Open every day') }} · {{ $businessHours['window'] ?? __('1:00 PM to 12:00 MN') }}</span></p>
                            @if (filled($applicationSettings->business_address))
                                <p><x-landing-icon name="pin" /><span>{{ $applicationSettings->business_address }}@if (filled($applicationSettings->location_landmarks))<span class="landing-landmark">{{ $applicationSettings->location_landmarks }}</span>@endif</span></p>
                            @elseif (filled($applicationSettings->location_landmarks))
                                <p><x-landing-icon name="pin" /><span>{{ $applicationSettings->location_landmarks }}</span></p>
                            @endif
                        </div>
                        <a href="{{ $accountUrl }}" class="landing-button landing-button--warm">{{ auth()->check() ? __('Open workspace') : __('Book an appointment') }}</a>
                        <div class="landing-contact-actions">
                            @if (filled($applicationSettings->messenger_url))<a href="{{ $applicationSettings->messenger_url }}" target="_blank" rel="noopener noreferrer"><x-landing-icon name="message" /> Message us</a>@endif
                            @if (filled($applicationSettings->facebook_url))<a href="{{ $applicationSettings->facebook_url }}" target="_blank" rel="noopener noreferrer"><x-landing-icon name="facebook" /> Visit Facebook</a>@endif
                            @if (filled($applicationSettings->map_url))<a href="{{ $applicationSettings->map_url }}" target="_blank" rel="noopener noreferrer"><x-landing-icon name="pin" /> Get directions</a>@endif
                        </div>
                    </div>
                    <img class="landing-visit-image" src="{{ asset('images/spa/casa-lobby-1600.webp') }}" srcset="{{ asset('images/spa/casa-lobby-960.webp') }} 960w, {{ asset('images/spa/casa-lobby-1600.webp') }} 1600w" sizes="(max-width: 767px) 100vw, 50vw" width="1600" height="1200" alt="The welcoming reception and seating area at Casa Paraiso" loading="lazy">
                </section>
            </main>

            <footer class="landing-footer">
                <div class="landing-container landing-footer-inner">
                    <a href="{{ url('/') }}" class="landing-logo" aria-label="Casa Paraiso home"><img src="{{ asset('images/casa_paraiso_logo.jpg') }}" alt="Casa Paraiso Body and Wellness Spa" width="500" height="500" loading="lazy"></a>
                    <nav class="landing-footer-nav" aria-label="Footer navigation">
                        @if ($showCompanyProfile)<a href="#about">About</a>@endif
                        <a href="#treatments">Treatments</a><a href="#how-it-works">How it works</a><a href="#visit">Visit &amp; contact</a>
                    </nav>
                    <a href="{{ $accountUrl }}" class="landing-button landing-button--outline">{{ auth()->check() ? __('Open workspace') : __('Log in') }}</a>
                    <p class="landing-copyright">&copy; {{ date('Y') }} {{ $applicationSettings->business_name }}. {{ __('All rights reserved.') }}</p>
                </div>
            </footer>
        </div>
    </body>
</html>
