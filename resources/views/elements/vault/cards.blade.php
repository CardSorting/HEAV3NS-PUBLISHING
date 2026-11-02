@foreach(\App\Services\Media\VaultAttachmentPresentation::cards($attachments, $payloads) as ['attachment' => $attachment, 'payload' => $payload, 'mediaLabel' => $mediaLabel, 'mediaIcon' => $mediaIcon, 'deleteReason' => $deleteReason])
    <article class="vault-card border" data-attachment-id="{{ $attachment->id }}" data-status="{{ $payload['transcoding_status'] }}" data-media-type="{{ $payload['type'] }}">
        @unless($picker)
            <div class="dropdown vault-card-menu">
                <button type="button" class="ellipsis-dropdown-toggle vault-text-button vault-options-button" data-bs-toggle="dropdown" aria-label="{{ __('Media options') }}" aria-haspopup="true" aria-expanded="false">
                    @include('elements.icon', ['icon' => 'ellipsis-horizontal', 'variant' => 'small'])
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <button type="button" class="dropdown-item vault-action" data-action="{{ $attachment->vault_archived_at ? 'restore' : 'archive' }}">{{ $attachment->vault_archived_at ? __('Restore') : __('Archive') }}</button>
                    <div class="dropdown-divider"></div>
                    <button type="button" class="dropdown-item vault-action {{ $deleteReason ? '' : 'text-danger' }}" data-action="delete" @if($deleteReason) disabled aria-disabled="true" aria-describedby="vault-delete-reason-{{ $attachment->id }}" @endif>{{ __('Delete permanently') }}</button>
                    @if($deleteReason)
                        <p class="vault-delete-hint small text-muted" id="vault-delete-reason-{{ $attachment->id }}">{{ $deleteReason }}</p>
                    @endif
                    @if($attachment->placements_count)
                        <button type="button" class="dropdown-item vault-usage small text-primary">{{ __('View uses') }}</button>
                    @endif
                </div>
            </div>
        @endunless
        <div class="vault-card-media">
            <div class="vault-media-content">
                @if($payload['type'] === 'file')
                    @include('elements.attachments.file-card', ['file' => $payload])
                @elseif($payload['transcoding_status'] === 'completed' && $payload['path'])
                    @include('elements.feed.post-box-media-wrapper', ['attachment' => $attachment, 'isGallery' => false])
                @else
                    <div class="vault-media-placeholder text-muted text-center p-3" role="status">
                        @include('elements.icon', ['icon' => $mediaIcon, 'variant' => 'large'])
                        <span class="small">{{ $payload['transcoding_message'] ?: __('Media unavailable') }}</span>
                    </div>
                @endif
            </div>
            <span class="vault-media-badge">
                @include('elements.icon', ['icon' => $mediaIcon, 'variant' => 'small'])
                <span>{{ $mediaLabel }}</span>
            </span>
        </div>
        <div class="vault-card-body">
            <div class="vault-card-meta small text-muted">
                <time datetime="{{ $attachment->created_at?->toIso8601String() }}">{{ $attachment->created_at?->format('M j, Y') }}</time>
                @unless($picker)
                    <button type="button" class="vault-text-button vault-usage" title="{{ __('View where this media is used') }}">{{ __('Uses') }}: {{ $attachment->placements_count }}</button>
                @endunless
            </div>
            @if($picker)
                <button type="button" class="btn btn-sm btn-outline-primary w-100 mb-0 vault-select" @if($payload['transcoding_status'] !== 'completed') disabled @endif>{{ __('Select') }}</button>
            @elseif($payload['can_retry_transcoding'])
                <button type="button" class="btn btn-sm btn-outline-primary vault-retry mb-0">{{ __('Retry') }}</button>
            @endif
        </div>
    </article>
@endforeach
