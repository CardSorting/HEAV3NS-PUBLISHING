@php
    $filterFields = [
        'status' => ['label' => __('Status'), 'default' => __('Any status'), 'options' => $paymentStatusOptions, 'value' => $paymentFilters['status']],
        'transactionType' => ['label' => __('Type'), 'default' => __('Any type'), 'options' => $paymentTypeOptions, 'value' => $paymentFilters['type']],
        'direction' => ['label' => __('Direction'), 'default' => __('All directions'), 'options' => $paymentDirectionOptions, 'value' => $paymentFilters['direction']],
        'sort' => ['label' => __('Sort by'), 'default' => null, 'options' => $paymentSortOptions, 'value' => $paymentFilters['sort']],
    ];
@endphp
<div class="dropdown app-filter-dropdown settings-payments-filter-menu m-0">
    <button type="button" id="payments-filter-toggle" class="app-filter-toggle settings-payments-filter-toggle {{ $hasActivePaymentFilters ? 'active' : '' }}"
            data-bs-toggle="dropdown"
            data-bs-boundary="viewport"
            data-bs-offset="0,8"
            aria-controls="payments-filters" aria-expanded="false" aria-label="{{ __('Filters').($activePaymentFilterCount ? ' ('.$activePaymentFilterCount.')' : '') }}" title="{{ __('Filters') }}">
        @include('elements.icon', ['icon' => 'options-outline', 'variant' => 'small'])
        @if($activePaymentFilterCount)
            <span class="app-filter-count settings-payments-filter-count" aria-hidden="true">{{ $activePaymentFilterCount }}</span>
        @endif
    </button>
    <div class="dropdown-menu dropdown-menu-end app-filter-panel" id="payments-filters" aria-labelledby="payments-filter-toggle">
        <form class="settings-payments-filter-form" method="GET" action="{{ route('my.settings', ['type' => 'payments']) }}">
            @foreach($filterFields as $name => $field)
                <div class="settings-payments-filter-field">
                    <label for="payments-filter-{{ $name }}">{{ $field['label'] }}</label>
                    <select id="payments-filter-{{ $name }}" class="form-select form-select-sm" name="{{ $name }}">
                        @if($field['default'] !== null)
                            <option value="all" {{ $field['value'] === 'all' ? 'selected' : '' }}>{{ $field['default'] }}</option>
                        @endif
                        @foreach($field['options'] as $value => $label)
                            <option value="{{ $value }}" {{ $field['value'] === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="settings-payments-filter-actions">
                @if($hasActivePaymentFilters)
                    <a class="btn btn-sm btn-outline-secondary mb-0" href="{{ route('my.settings', ['type' => 'payments']) }}">{{ __('Reset') }}</a>
                @endif
                <button class="btn btn-sm btn-primary mb-0" type="submit">{{ __('Apply filters') }}</button>
            </div>
        </form>
    </div>
</div>
