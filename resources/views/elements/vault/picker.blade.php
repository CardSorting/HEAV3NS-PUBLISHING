@if(AttachmentHelper::isVaultEnabled())
<div class="modal fade" id="vault-picker-dialog" tabindex="-1" role="dialog" aria-labelledby="vault-picker-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content" data-vault-browser data-vault-mode="picker">
            <div class="modal-header">
                <h5 class="modal-title" id="vault-picker-title">{{ __('Select from Vault') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                @include('elements.vault.filters', ['archiveFilter' => false])
                <div class="vault-message text-muted d-none" role="status" aria-live="polite"></div>
                <button type="button" class="btn btn-sm btn-outline-primary vault-reload d-none">{{ __('Retry') }}</button>
                @include('elements.vault.empty-state')
                <div class="vault-grid"></div>
            </div>
            <div class="modal-footer vault-picker-footer">
                <span class="vault-selection-count small text-muted" role="status" aria-live="polite" aria-atomic="true"></span>
                @include('elements.vault.pagination', ['vaultPickerPagination' => true])
                <button type="button" class="btn btn-primary vault-picker-done mb-0" data-bs-dismiss="modal">{{ __('Done') }}<span class="vault-done-count"></span></button>
            </div>
        </div>
    </div>
</div>
@endif
@unless(request()->routeIs('my.messenger.get'))
    @include('elements.photoswipe-container')
@endunless
