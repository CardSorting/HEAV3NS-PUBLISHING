{{-- Bootstrap and generated brand variables stay outside Minify, in this order. --}}
@foreach(GenericHelper::getThemeStylesheets() as $id => $url)
    <link id="{{ $id }}" rel="stylesheet" href="{{ $url }}">
@endforeach
