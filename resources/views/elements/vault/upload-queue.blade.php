<section class="vault-upload-queue" aria-labelledby="vault-upload-title" hidden>
    <h6 id="vault-upload-title" class="mb-2">{{ __('Uploads') }}</h6>
    <div class="vault-upload-list" role="list" aria-label="{{ __('Uploads') }}"></div>
</section>
<div class="visually-hidden vault-upload-announcement" role="status" aria-live="polite" aria-atomic="true"></div>
<template id="vault-upload-row-template">
    <div class="vault-upload-row" role="listitem">
        <div class="vault-upload-details">
            <div class="vault-upload-filename" data-dz-name></div>
            <div class="vault-upload-meta small text-muted">
                <span data-dz-size></span>
                <span class="vault-upload-state">{{ __('Queued') }}</span>
            </div>
            <div class="vault-upload-progress" role="progressbar" aria-label="{{ __('Upload progress') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <span data-dz-uploadprogress></span>
            </div>
            <div class="vault-upload-error small text-danger" hidden></div>
        </div>
        <div class="vault-upload-actions">
            <button type="button" class="vault-text-button vault-upload-retry" hidden>{{ __('Retry') }}</button>
            <button type="button" class="vault-text-button vault-upload-remove">{{ __('Cancel') }}</button>
        </div>
    </div>
</template>
