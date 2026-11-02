@php($filterPrefix = 'vault-'.($archiveFilter ? 'library' : 'picker'))
<div class="vault-toolbar">
    <div class="vault-library-summary text-muted small">
        <span class="vault-results-count" aria-live="polite">{{ __('Media library') }}</span>
    </div>
    @if(!$archiveFilter)
        <div class="vault-filters vault-filters-inline" id="{{ $filterPrefix }}-filters">
            @include('elements.vault.filter-fields', ['inline' => true])
        </div>
    @else
        <div class="dropdown dropstart app-filter-dropdown vault-filter-menu">
            <button type="button" id="{{ $filterPrefix }}-filter-toggle" class="app-filter-toggle vault-filter-toggle h-pill h-pill-primary rounded-circle"
                    data-bs-toggle="dropdown"
                    data-bs-boundary="viewport"
                    data-bs-offset="0,8"
                    aria-controls="{{ $filterPrefix }}-filters" aria-expanded="false" aria-label="{{ __('Filters') }}" title="{{ __('Filters') }}">
                @include('elements.icon', ['icon' => 'options-outline', 'variant' => 'medium'])
                <span class="app-filter-count vault-filter-count d-none" aria-hidden="true"></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end app-filter-panel vault-filter-panel" id="{{ $filterPrefix }}-filters" aria-labelledby="{{ $filterPrefix }}-filter-toggle">
                <div class="vault-filters">
                    @include('elements.vault.filter-fields', ['inline' => false])
                    <div class="vault-filter-actions">
                        <button type="button" class="vault-text-button vault-filters-reset" disabled>{{ __('Reset') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
