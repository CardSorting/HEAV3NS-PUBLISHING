@extends('layouts.generic')

@section('page_description', getSetting('site.description'))
@section('share_url', route('home'))
@section('share_title', getSetting('site.name') . ' - ' . getSetting('site.slogan'))
@section('share_description', getSetting('site.description'))
@section('share_type', 'article')
@section('share_img', GenericHelper::getOGMetaImage())

@section('scripts')
    {!! Minify::javascript(['/js/pages/home.js'])->withFullUrl() !!}
    @verbatim
        <script type="application/ld+json">
            {
              "@context": "http://schema.org",
              "@type": "Organization",
              "name": "{{getSetting('site.name')}}",
              "url": "{{getSetting('site.app_url')}}",
              "address": ""
            }
        </script>
    @endverbatim
@stop

@section('styles')
    {!!
        Minify::stylesheet([
            '/css/pages/home.css',
            '/css/pages/search.css',
         ])->withFullUrl()
    !!}
@stop

@section('content')
    @php
        $experienceCards = [
            [
                'image' => '/img/home-scene-1.svg',
                'title' => __('Built for monetization'),
                'description' => __('homepage_subHeader_paywall_description'),
            ],
            [
                'image' => '/img/home-scene-2.svg',
                'title' => __('Made for creators and fans'),
                'description' => __('homepage_subHeader_fans_description'),
            ],
            [
                'image' => '/img/home-scene-3.svg',
                'title' => __('Premium content, better discovery'),
                'description' => __('homepage_subHeader_content_description'),
            ],
        ];

        $featureCards = [
            [
                'icon' => 'wallet-outline',
                'title' => __('Advanced paywall'),
                'description' => __('homepage_paywall_description'),
            ],
            [
                'icon' => 'albums-outline',
                'title' => __('Advanced posting capabilities'),
                'description' => __('homepage_posting_description'),
            ],
            [
                'icon' => 'chatbubbles-outline',
                'title' => __('Live chat & Notifications'),
                'description' => __('homepage_chat_description'),
            ],
            [
                'icon' => 'phone-portrait-outline',
                'title' => __('Mobile Ready'),
                'description' => __('homepage_mobile_description'),
            ],
            [
                'icon' => 'moon-outline',
                'title' => __('Light & Dark themes'),
                'description' => __('homepage_themes_description'),
            ],
            [
                'icon' => 'language-outline',
                'title' => __('RTL & Locales'),
                'description' => __('homepage_rtl_description'),
            ],
            [
                'icon' => 'bookmarks-outline',
                'title' => __('Post Bookmarks & User lists'),
                'description' => __('homepage_lists_description'),
            ],
            [
                'icon' => 'flag-outline',
                'title' => __('Content flagging and User reports'),
                'description' => __('homepage_reports_description'),
            ],
            [
                'icon' => 'videocam-outline',
                'title' => __('Live streaming'),
                'description' => __('homepage_live_description'),
            ],
        ];
    @endphp

    @php
        $themeIllustrations = app(\App\Services\ThemeIllustrationService::class);
    @endphp

    <div class="home-page">
        <section class="home-hero">
            <div class="container position-relative">
                <div class="row align-items-center justify-content-between home-hero-row">
                    <div class="col-12 col-lg-7 col-xl-6 text-center text-lg-start">
                        <div class="home-hero-content mx-auto mx-lg-0">
                            <span class="home-eyebrow">{{ __('Built for modern creator businesses') }}</span>
                            <h1 class="home-hero-title">
                                <span class="home-hero-title-line">{{ __('Make more money') }}</span>
                                <span class="home-hero-title-accent">{{ __('from your content') }}</span>
                            </h1>
                            <p class="home-hero-copy">
                                {{ __('Launch a premium content platform that feels polished from day one, with subscriptions, direct engagement, discovery, and growth tools.') }}
                            </p>
                            <div class="home-hero-actions d-flex flex-wrap justify-content-center justify-content-lg-start">
                            <a href="{{ Auth::check() ? route('feed') : route('login') }}" class="btn btn-primary btn-grow bg-gradient-primary btn-round home-hero-btn-primary me-2 mb-2">{{ __('Get started') }}</a>
                            <a href="{{ route('search.get') }}" class="btn btn-outline-primary btn-grow btn-round d-inline-flex align-items-center justify-content-center gap-2 home-hero-btn-secondary mb-2">
                                @include('elements.icon',['icon'=>'search-outline','variant'=>'small','centered'=>false])
                                {{ __('Explore creators') }}
                            </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-5 col-xl-5 mt-5 mt-lg-0">
                        <div class="home-hero-visual home-hero-visual-classic text-center">
                            <div class="home-hero-image-wrap home-hero-image-simple">
                                <img src="{{ $themeIllustrations->src('img/home-header.svg') }}"
                                     alt="{{ __('Make more money') }}"
                                     class="img-fluid home-hero-illustration">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="home-section home-section-tight">
            <div class="container">
                <div class="home-section-heading text-center mx-auto" data-home-reveal>
                    <span class="home-section-kicker">{{ __('Platform experience') }}</span>
                    <h2>{{ __('A better experience for creators and fans') }}</h2>
                    <p>{{ __('Modern onboarding, cleaner discovery and a more polished member journey help the whole product feel premium from the first click.') }}</p>
                </div>
                <div class="row">
                    @foreach($experienceCards as $card)
                        <div class="col-12 col-md-4 mb-4" data-home-reveal data-home-reveal-delay="{{ $loop->index % 3 }}">
                            <div class="home-glass-card text-center h-100">
                                <div class="home-glass-card-media">
                                    <img src="{{ $themeIllustrations->src($card['image']) }}"
                                         alt="{{ $card['title'] }}"
                                         class="img-fluid home-box-img">
                                </div>
                                <h3>{{ $card['title'] }}</h3>
                                <p class="mb-0">{{ $card['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if(GenericHelper::getHomeCreatorAction())
        <section class="home-section">
            <div class="container">
                <div class="home-spotlight" data-home-reveal>
                    <div class="row align-items-center">
                        <div class="col-12 col-lg-6 text-center text-lg-start mb-5 mb-lg-0">
                            <div class="d-flex justify-content-center align-items-center">

                            <img src="{{ $themeIllustrations->src('img/home-creators.svg') }}"
                                 alt="{{ __('Create your creator profile in minutes') }}"
                                 class="img-fluid home-mid-img">
                            </div>

                        </div>
                        <div class="col-12 col-lg-6">
                            <span class="home-section-kicker">{{ __('Creator onboarding') }}</span>
                            <h2 class="home-spotlight-title">{{ __('Create your creator profile in minutes') }}</h2>
                            <p class="home-spotlight-copy">{{ __('Give creators a faster path to launch with subscriptions, paid posts and a profile flow that already feels ready for revenue.') }}</p>
                            <div class="home-spotlight-points">
                                <div class="home-spotlight-point">
                                    <strong>{{ __('Monetize from day one') }}</strong>
                                    <span>{{ __('Enable subscriptions, paid posts and direct support before your first audience even arrives.') }}</span>
                                </div>
                                <div class="home-spotlight-point">
                                    <strong>{{ __('Set up access in one flow') }}</strong>
                                    <span>{{ __('Handle profile details, pricing and verification in a creator journey that stays simple from start to finish.') }}</span>
                                </div>
                            </div>
                            <a href="{{ GenericHelper::getHomeCreatorAction()['url'] }}" class="btn btn-primary bg-gradient-primary btn-grow btn-round home-spotlight-btn">{{ GenericHelper::getHomeCreatorAction()['label'] }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @endif

        <section class="home-section">
            <div class="container">
                <div class="home-section-heading text-center mx-auto" data-home-reveal>
                    <span class="home-section-kicker">{{ __('Core platform') }}</span>
                    <h2>{{ __('Everything you need to launch and scale') }}</h2>
                    <p>{{ __('Subscriptions, messaging, moderation, streaming and mobile-ready flows are already built in, so the product feels complete from day one.') }}</p>
                </div>
                <div class="row">
                    @foreach($featureCards as $feature)
                        <div class="col-12 col-md-6 col-xl-4 mb-4" data-home-reveal data-home-reveal-delay="{{ $loop->index % 3 }}">
                            <div class="home-feature-card h-100">
                                <div class="home-feature-icon">
                                    @include('elements.icon',['icon'=>$feature['icon'],'variant'=>'medium','centered'=>false,'classes'=>'text-primary'])
                                </div>
                                <h3>{{ $feature['title'] }}</h3>
                                <p class="mb-0">{{ $feature['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="home-section home-creators-section">
            <div class="container">
                <div class="home-section-heading text-center mx-auto" data-home-reveal>
                    <span class="home-section-kicker">{{ __('Featured creators') }}</span>
                    <h2>{{ __('Creators worth discovering') }}</h2>
                    <p>{{ __('Discover creators, explore their vibe, and get a feel for the community your platform can bring together.') }}</p>
                </div>

                @if(count($featuredMembers))
                    @php(app(\App\Services\UserStaffBadge::class)->preload($featuredMembers))
                    <div class="creators-wrapper home-creators-wrapper" data-home-reveal>
                        <div class="row px-3">
                            @foreach($featuredMembers as $member)
                                <div class="col-12 col-md-4 p-2">
                                    @include('elements.feed.suggestion-card',['profile' => $member, 'cardRadius' => 'rounded-xl'])
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>

        <section class="home-section home-cta-section">
            <div class="container">
                <div class="home-cta-card text-center" data-home-reveal>
                    <span class="home-section-kicker">{{ __('Ready to launch') }}</span>
                    <h2>{{ __('Launch on a platform that feels premium') }}</h2>
                    <p>{{ __('Start from a polished base and spend more time on brand, creators and growth instead of rebuilding the core product experience.') }}</p>
                    <div class="d-flex flex-wrap justify-content-center">
                        <a href="{{ Auth::check() ? route('feed') : route('login') }}" class="btn btn-primary btn-grow bg-gradient-primary btn-round me-2 mb-2">{{ __('Get started') }}</a>
                        <a href="{{ route('contact') }}" class="btn btn-outline-primary btn-grow btn-round mb-2">{{ __('Talk to us') }}</a>
                    </div>
                </div>
            </div>
        </section>
    </div>
@stop

