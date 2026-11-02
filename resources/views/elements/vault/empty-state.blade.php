<div class="vault-empty-state d-none" role="status" aria-live="polite">
    @component('elements.empty-state', [
        'illustration' => asset('/img/no-content-available.svg'),
        'title' => __('No media yet'),
        'copy' => __('Upload media to get started.'),
    ])
        @slot('emptyStateActions')
            <button type="button" class="btn btn-sm btn-outline-primary vault-filters-reset mb-0" disabled>{{ __('Clear filters') }}</button>
        @endslot
    @endcomponent
</div>
