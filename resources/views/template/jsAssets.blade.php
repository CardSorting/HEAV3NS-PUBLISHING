{{-- Global JS Assets --}}
{{-- TODO: Only include PWA and WebPush if settings enabled; same with cookies one --}}
{!!
    Minify::javascript(
        array_merge([
        '/libs/jquery/dist/jquery.min.js',
        '/libs/bootstrap/dist/js/bootstrap.bundle.min.js',
        '/js/plugins/toasts.js',
        '/libs/xss/dist/xss.min.js',
        '/libs/pusher-js-auth/lib/pusher-auth.js',
        '/js/Websockets.js',
    ],
    (isset($additionalJs) ? $additionalJs : []),
    GenericHelper::getGlobalAdditionalJS(),
    ['/js/app.js']
    ))->withFullUrl()
!!}

{{-- Page specific JS --}}
@yield('scripts')

<script type="module" src="{{asset('/libs/ionicons/dist/ionicons/ionicons.esm.js')}}"></script>

@if(getSetting('streams.streaming_driver') === 'livekit')
    <script src="https://cdn.jsdelivr.net/npm/livekit-client@2.9.1/dist/livekit-client.umd.min.js"></script>
@endif

@if(getSetting('site.custom_code_js'))
    {!! getSetting('site.custom_code_js') !!}
@endif

@include('elements.translations')
