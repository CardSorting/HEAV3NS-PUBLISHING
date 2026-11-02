<div class="modal fade vault-usage-dialog" id="vault-usage-dialog" tabindex="-1" role="dialog" aria-labelledby="vault-usage-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="vault-usage-title">{{ __('Where this media is used') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="vault-usage-description text-muted small">{{ __('Archiving hides media from new selection. Existing posts, messages and enabled automations continue to use it.') }}</p>
                <ul class="vault-usage-list list-unstyled mb-0"></ul>
                <button type="button" class="btn btn-sm btn-outline-primary vault-usage-more d-none">{{ __('Load more') }}</button>
            </div>
        </div>
    </div>
</div>
