@php
    $youtubeEmbedsEnabled = $canCompose && getSetting('feed.youtube_embeds_enabled', false);
    $youtubeAllowedHosts = $youtubeEmbedsEnabled ? app(\App\Services\YouTubeEmbed::class)->allowedHosts() : [];
@endphp
<div class="post-composer" data-mode="{{ $isEditing ? 'edit' : 'create' }}" data-server-time="{{ now()->format('Y-m-d\TH:i:s') }}" data-timezone="{{ config('app.timezone') }}" data-enable-youtube-embeds="{{ $youtubeEmbedsEnabled ? 'true' : 'false' }}" @if($youtubeEmbedsEnabled) data-youtube-allowed-hosts="{{ json_encode($youtubeAllowedHosts) }}" @endif>
    <div class="post-composer-caption">
        <div class="post-composer-caption-header">
            <label for="dropzone-uploader" class="mb-0">{{ __('Post text') }}</label>
            @if(getSetting('ai.text_enabled'))
                <button type="button" class="form-action-link ai-suggest-link {{ $canCompose ? '' : 'disabled' }}" {{ $canCompose ? '' : 'disabled' }} data-ai-type="post" data-ai-target="#dropzone-uploader" data-loading-text="{{ __('Generating') }}">
                    {{ __('Suggest text') }}
                </button>
            @endif
        </div>
        <div class="hl-textarea-wrap">
            <div class="hl-backdrop" aria-hidden="true"><div class="hl-highlights"></div></div>
            <textarea id="dropzone-uploader" name="input-text" class="form-control border dropzone w-100 hl-textarea" rows="3" spellcheck="false" placeholder="{{ __('Write a new post, drag and drop files to add attachments.') }}">{{ isset($post) ? $post->text : '' }}</textarea>
            <span class="invalid-feedback" role="alert"><strong class="post-invalid-feedback">{{ __('text_min_if_no_media', ['min' => (int)getSetting('feed.min_post_description')]) }}</strong></span>
        </div>
    </div>
    @if($youtubeEmbedsEnabled)
        <div class="post-composer-youtube-preview border rounded p-3 mt-3" role="status" aria-live="polite" hidden>
            <div class="d-flex align-items-center gap-3">
                <ion-icon name="logo-youtube" class="fs-3 flex-shrink-0" aria-hidden="true"></ion-icon>
                <div>
                    <div class="post-composer-youtube-title fw-semibold">{{ __('YouTube video') }}</div>
                    <div class="small text-body-secondary">{{ __('Added to the post gallery.') }}</div>
                </div>
            </div>
        </div>
    @endif
    <div class="post-composer-media">
        <div class="post-composer-media-count small text-muted" role="status" aria-live="polite" hidden></div>
        <div class="dropzone-previews dropzone post-composer-grid" aria-label="{{ __('Attached media') }}"></div>
        <div class="post-media-order-status visually-hidden" role="status" aria-live="polite"></div>
        <div id="post-media-reorder-help" class="visually-hidden">{{ __('Use the arrow keys to move this attachment.') }}</div>
        <p class="post-composer-file-order small text-body-secondary mt-2 mb-0" hidden>{{ __('Files appear below the media gallery. Reordering keeps the order within each group.') }}</p>
    </div>
    @include('elements.post-create-actions')
</div>
