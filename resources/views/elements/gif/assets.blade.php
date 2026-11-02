@foreach($surfaces as $surface)
    @if($surface !== 'messenger' || auth()->check())
        @include('elements.gif.config', ['surface' => $surface])
    @endif
@endforeach
{!! Minify::javascript(['/js/components/gif-media.js', '/js/components/gif-picker.js'])->withFullUrl() !!}
