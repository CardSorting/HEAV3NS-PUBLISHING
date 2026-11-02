<div class="admin-attachment-modal-preview">
    <div class="admin-attachment-modal-preview__media">
        @switch($media['type'])
            @case('image')
                <img src="{{ $media['path'] }}" alt="{{ $media['name'] }}">
                @break
            @case('video')
                <video src="{{ $media['path'] }}#t=0.001"
                    @if(filled($media['thumbnail'])) poster="{{ $media['thumbnail'] }}" @endif
                    controls controlsList="nodownload" preload="metadata"></video>
                @break
            @case('audio')
                <div class="admin-attachment-modal-preview__audio">
                    <x-filament::icon icon="heroicon-o-speaker-wave" />
                    <audio src="{{ $media['path'] }}" controls controlsList="nodownload" preload="metadata"></audio>
                </div>
                @break
            @default
                <div class="admin-attachment-modal-preview__file">
                    <x-filament::icon icon="heroicon-o-document-text" />
                    <strong class="admin-file-attachment-name">{{ $media['name'] }}</strong>
                    <span>{{ $media['format'] }}@if($media['size']) · {{ $media['size'] }}@endif</span>
                </div>
        @endswitch
    </div>
    <div class="admin-attachment-modal-preview__footer">
        <span>{{ ucfirst($media['type']) }} · {{ $media['name'] }}</span>
        @if (filled($media['path']))
            <x-filament::button tag="a" :href="$media['path']" target="_blank" rel="noopener noreferrer"
                icon="heroicon-o-arrow-top-right-on-square" color="gray" size="sm">
                {{ $media['type'] === 'file' ? __('Download file') : __('Open original') }}
            </x-filament::button>
        @endif
    </div>
</div>
