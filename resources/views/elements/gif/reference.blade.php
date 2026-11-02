@if(!empty($gif))
    @if(!empty($admin))
        @assets
            <link rel="stylesheet" href="{{ \App\Providers\GenericHelperServiceProvider::getVersionedAsset('css/components/gif-media.css') }}">
            <script defer src="{{ \App\Providers\GenericHelperServiceProvider::getVersionedAsset('js/components/gif-media.js') }}"></script>
        @endassets
    @endif
    @include('elements.gif.config', ['surface' => $surface])
    <div class="gif-reference" data-gif-reference="{{ json_encode($gif) }}" data-gif-surface="{{ $surface }}">
        <span class="gif-status">{{ __('Loading GIF…') }}</span>
    </div>
@endif
