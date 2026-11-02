<div class="admin-attachment-table-preview" title="{{ __(':type attachment', ['type' => ucfirst($media['type'])]) }}">
    @if (filled($media['thumbnail']))
        <img src="{{ $media['thumbnail'] }}" alt="" loading="lazy">
        @if ($media['type'] === 'video')
            <span class="admin-attachment-table-preview__overlay" aria-hidden="true">
                <x-filament::icon icon="heroicon-s-play" />
            </span>
        @endif
    @else
        <x-filament::icon :icon="$media['icon']" />
    @endif
    <span class="fi-sr-only">{{ __(':type attachment', ['type' => ucfirst($media['type'])]) }}</span>
</div>
