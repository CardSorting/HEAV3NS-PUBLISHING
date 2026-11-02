@php
    $assetPath = $asset ?? $attachment->path ?? '';
    $fileExtension = strtolower(pathinfo($assetPath, PATHINFO_EXTENSION));
    $mediaType = AttachmentHelper::getAttachmentType($fileExtension);
    $assetUrl = $assetPath ? Storage::url($assetPath) : null;
    $documentLabel = __('Document :number', ['number' => $documentNumber ?? 1]);
@endphp

@if ($assetUrl)
    <div class="admin-document-card">
        <div class="admin-document-media">
            @switch($mediaType)
            @case('document')
                <a href="{{ $assetUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('View :document', ['document' => $documentLabel]) }}">
                    <x-filament::icon
                        :icon="in_array($fileExtension, ['xls', 'xlsx'], true) ? 'heroicon-o-table-cells' : 'heroicon-o-document-text'"
                        class="admin-document-file-icon"
                    />
                </a>
                @break
            @case('image')
                <a href="{{ $assetUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('View :document', ['document' => $documentLabel]) }}">
                    <img src="{{ $assetUrl }}" alt="{{ $documentLabel }}" loading="lazy" />
                </a>
                @break
            @case('video')
                <video src="{{ $assetUrl }}#t=0.001" controls controlsList="nodownload" preload="metadata" aria-label="{{ $documentLabel }}"></video>
                @break
            @case('audio')
                <audio src="{{ $assetUrl }}" controls controlsList="nodownload" preload="metadata" aria-label="{{ $documentLabel }}"></audio>
                @break
            @endswitch
        </div>
        <div class="admin-document-card-details">
            <div class="admin-document-card-heading">
                <span>{{ $documentLabel }}</span>
                <span class="admin-document-file-type">{{ strtoupper($fileExtension) }}</span>
            </div>
            <x-filament::link
                :href="$assetUrl"
                target="_blank"
                rel="noopener noreferrer"
                size="sm"
                :aria-label="__('View :document', ['document' => $documentLabel])"
            >
                {{ __('View document') }}
            </x-filament::link>
        </div>
    </div>
@endif
