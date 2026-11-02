<footer class="footer py-5">
    <div class="container">
        <div class="">
            <div class="mx-auto ">
                <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-center">
                    <div class="">
                        <!-- About -->
                        <div class="headline d-flex">
                            <a href="{{route('home')}}">
                                <img class="brand-logo d-inline-block align-top"
                                     src="{{ GenericHelper::getCurrentThemeLogo() }}"
                                     data-theme-light-src="{{ asset(getSetting('site.light_logo')) }}"
                                     data-theme-dark-src="{{ asset(getSetting('site.dark_logo')) }}"
                                     alt="{{__("Site logo")}}">
                            </a>
                        </div>
                    </div>
                    <div class="d-flex justify-content-md-center align-items-center mt-4 mt-md-0 footer-social-links">
                        @if(getSetting('general.social_facebook_url'))
                            <a class="m-2" href="{{getSetting('general.social_facebook_url')}}" target="_blank" alt="{{__("Facebook")}}" title="{{__("Facebook")}}">
                                @include('elements.icon',['icon'=>'logo-facebook','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_twitter_url'))
                            <a class="m-2" href="{{getSetting('general.social_twitter_url')}}" target="_blank" alt="{{__("Twitter")}}" title="{{__("Twitter")}}">
                                @include('elements.icon',['icon'=>'x-logo','variant'=>'small','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_instagram_url'))
                            <a class="m-2" href="{{getSetting('general.social_instagram_url')}}" target="_blank" alt="{{__("Instagram")}}" title="{{__("Instagram")}}">
                                @include('elements.icon',['icon'=>'logo-instagram','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_whatsapp_url'))
                            <a class="m-2" href="{{getSetting('general.social_whatsapp_url')}}" target="_blank" alt="{{__("Whatsapp")}}" title="{{__("Whatsapp")}}">
                                @include('elements.icon',['icon'=>'logo-whatsapp','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_tiktok_url'))
                            <a class="m-2" href="{{getSetting('general.social_tiktok_url')}}" target="_blank" alt="{{__("Tiktok")}}" title="{{__("Tiktok")}}">
                                @include('elements.icon',['icon'=>'logo-tiktok','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_youtube_url'))
                            <a class="m-2" href="{{getSetting('general.social_youtube_url')}}" target="_blank" alt="{{__("Youtube")}}" title="{{__("Youtube")}}">
                                @include('elements.icon',['icon'=>'logo-youtube','variant'=>'medium','classes' => 'opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_telegram_link'))
                            <a class="m-2" href="{{getSetting('general.social_telegram_link')}}" target="_blank" alt="{{__("Telegram")}}" title="{{__("Telegram")}}">
                                @include('elements.icon',['icon'=>'paper-plane','variant'=>'medium','classes' => 'text-lg opacity-8'])
                            </a>
                        @endif
                        @if(getSetting('general.social_reddit_url'))
                            <a class="m-2" href="{{getSetting('general.social_reddit_url')}}" target="_blank" alt="{{__("Reddit")}}" title="{{__("Reddit")}}">
                                @include('elements.icon',['icon'=>'logo-reddit','variant'=>'medium','classes' => 'text-lg opacity-8'])
                            </a>
                        @endif
                    </div>
                </div>

                <div class="d-flex flex-column flex-md-row mt-3 mt-md-4">
                    <a href="{{route('contact')}}" class="text-dark-r me-2 mt-0 mt-md-2 mb-2 ms-2 ms-md-0">
                        {{__('Contact page')}}
                    </a>
                    @foreach(GenericHelper::getFooterPublicPages() as $page)
                        @include('elements.public-page-link', ['page' => $page, 'classes' => 'text-dark-r m-2'])
                    @endforeach
                </div>
                <hr>
            </div>
        </div>

        <div class="">
            <div class="copyRightInfo d-flex flex-column-reverse flex-md-row d-md-flex justify-content-md-between">
                <div class="d-flex align-items-center justify-content-center mt-3 mt-md-0">
                    <p class="mb-0">{{ GenericHelper::getFooterCopyright() }}</p>
                </div>
                <div class="d-flex align-items-center justify-content-center gap-3">
                    @include('elements.footer.dark-mode-switcher')
                    @include('elements.footer.language-switcher')
                </div>
            </div>
        </div>

    </div>
</footer>
