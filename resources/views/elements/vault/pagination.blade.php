<nav class="vault-pagination d-none" aria-label="{{ __('Pagination') }}">
    <div class="app-pagination app-pagination--responsive {{ ($vaultPickerPagination ?? false) ? 'app-pagination--dialog' : '' }} vault-page-controls">
        <button type="button" class="app-pagination-control vault-previous" title="{{ __('Previous') }}" aria-label="{{ __('Previous') }}" disabled>
            @include('elements.icon', ['icon' => 'chevron-back-outline', 'variant' => 'small', 'classes' => 'app-pagination-arrow'])
        </button>
        <div class="app-pagination-pages vault-page-numbers"></div>
        <span class="app-pagination-status vault-page-label" aria-live="polite" aria-atomic="true">
            <span class="app-pagination-status-full vault-page-label-full"></span>
            <span class="app-pagination-status-short vault-page-label-short"></span>
        </span>
        <button type="button" class="app-pagination-control vault-next" title="{{ __('Next') }}" aria-label="{{ __('Next') }}" disabled>
            @include('elements.icon', ['icon' => 'chevron-forward-outline', 'variant' => 'small', 'classes' => 'app-pagination-arrow'])
        </button>
    </div>
</nav>
