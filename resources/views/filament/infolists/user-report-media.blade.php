<x-filament-infolists::entry-wrapper :entry="$entry">
    <div class="admin-report-media-grid">
        @foreach (($getState() ?? []) as $index => $media)
            <article class="admin-report-media-card">
                <div class="admin-report-media-card__preview">
                    @switch($media['type'])
                        @case('image')
                            <a href="{{ $media['path'] }}" target="_blank" rel="noopener noreferrer">
                                <img src="{{ $media['path'] }}" alt="{{ $media['name'] }}" loading="lazy">
                            </a>
                            @break

                        @case('video')
                            <video
                                src="{{ $media['path'] }}#t=0.001"
                                @if(filled($media['thumbnail'])) poster="{{ $media['thumbnail'] }}" @endif
                                controls
                                controlsList="nodownload"
                                preload="metadata"
                            ></video>
                            @break

                        @case('audio')
                            <div class="admin-report-media-card__audio">
                                <x-filament::icon icon="heroicon-o-speaker-wave" />
                                <audio src="{{ $media['path'] }}" controls controlsList="nodownload" preload="metadata"></audio>
                            </div>
                            @break

                        @default
                            <div class="admin-report-media-card__file">
                                <x-filament::icon icon="heroicon-o-document-text" />
                                <span class="admin-file-attachment-name">{{ $media['name'] }}</span>
                                <span>{{ $media['format'] ?? '' }}@if(!empty($media['size'])) · {{ $media['size'] }}@endif</span>
                            </div>
                    @endswitch
                </div>

                <div class="admin-report-media-card__footer">
                    <div class="admin-report-media-card__identity">
                        <span class="admin-report-media-card__type">{{ ucfirst($media['type']) }} {{ $index + 1 }}</span>
                        <span class="admin-report-media-card__name" title="{{ $media['name'] }}">{{ $media['name'] }}</span>
                    </div>

                    @if (filled($media['path']))
                    <a
                        class="admin-report-media-card__open"
                        href="{{ $media['path'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        title="{{ $media['type'] === 'file' ? __('Download file') : __('Open original file') }}"
                        aria-label="{{ $media['type'] === 'file' ? __('Download file') : __('Open original file') }}"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" />
                    </a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>
</x-filament-infolists::entry-wrapper>
