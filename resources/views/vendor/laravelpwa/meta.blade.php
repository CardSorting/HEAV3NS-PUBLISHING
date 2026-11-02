<!-- Web Application Manifest -->
<link rel="manifest" href="{{ route('laravelpwa.manifest') }}">

@php
    $isStandalone = ($config['display'] ?? null) === 'standalone';
    $icons = $config['icons'] ?? [];
    $splashes = $config['splash'] ?? [];
    $appleTouchIcon = $icons['512x512']['src'] ?? ($icons['192x192']['src'] ?? null);
@endphp

    <!-- Chrome / general theme color -->
<meta name="theme-color" content="{{ $config['theme_color'] ?? '#ffffff' }}">

<!-- Add to homescreen for Chrome on Android -->
<meta name="mobile-web-app-capable" content="{{ $isStandalone ? 'yes' : 'no' }}">
<meta name="application-name" content="{{ $config['short_name'] ?? ($config['name'] ?? '') }}">

<!-- Add to homescreen for Safari on iOS -->
<meta name="apple-mobile-web-app-capable" content="{{ $isStandalone ? 'yes' : 'no' }}">
<meta name="apple-mobile-web-app-title" content="{{ $config['short_name'] ?? ($config['name'] ?? '') }}">

@if(!empty($config['status_bar']))
    <meta name="apple-mobile-web-app-status-bar-style" content="{{ $config['status_bar'] }}">
@endif

@if($appleTouchIcon)
    <link rel="apple-touch-icon" href="{{ $appleTouchIcon }}">
@endif

@if(!empty($splashes['640x1136']))
    <link href="{{ $splashes['640x1136'] }}" media="(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['750x1334']))
    <link href="{{ $splashes['750x1334'] }}" media="(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1242x2208']))
    <link href="{{ $splashes['1242x2208'] }}" media="(device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1125x2436']))
    <link href="{{ $splashes['1125x2436'] }}" media="(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['828x1792']))
    <link href="{{ $splashes['828x1792'] }}" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1242x2688']))
    <link href="{{ $splashes['1242x2688'] }}" media="(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1170x2532']))
    <link href="{{ $splashes['1170x2532'] }}" media="(device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1284x2778']))
    <link href="{{ $splashes['1284x2778'] }}" media="(device-width: 428px) and (device-height: 926px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1179x2556']))
    <link href="{{ $splashes['1179x2556'] }}" media="(device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1290x2796']))
    <link href="{{ $splashes['1290x2796'] }}" media="(device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1206x2622']))
    <link href="{{ $splashes['1206x2622'] }}" media="(device-width: 402px) and (device-height: 874px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1260x2736']))
    <link href="{{ $splashes['1260x2736'] }}" media="(device-width: 420px) and (device-height: 912px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1320x2868']))
    <link href="{{ $splashes['1320x2868'] }}" media="(device-width: 440px) and (device-height: 956px) and (-webkit-device-pixel-ratio: 3)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1536x2048']))
    <link href="{{ $splashes['1536x2048'] }}" media="(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1668x2224']))
    <link href="{{ $splashes['1668x2224'] }}" media="(device-width: 834px) and (device-height: 1112px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['1668x2388']))
    <link href="{{ $splashes['1668x2388'] }}" media="(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
@endif

@if(!empty($splashes['2048x2732']))
    <link href="{{ $splashes['2048x2732'] }}" media="(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2)" rel="apple-touch-startup-image" />
@endif

<!-- Tile for Win8 -->
<meta name="msapplication-TileColor" content="{{ $config['background_color'] ?? '#ffffff' }}">
@if($appleTouchIcon)
    <meta name="msapplication-TileImage" content="{{ $appleTouchIcon }}">
@endif
