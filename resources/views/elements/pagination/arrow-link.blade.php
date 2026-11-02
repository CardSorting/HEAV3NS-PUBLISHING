@php
    $label = $previous ? __('Previous') : __('Next');
    $icon = $previous ? 'chevron-back-outline' : 'chevron-forward-outline';
@endphp
@if($url)
    <a class="app-pagination-control" href="{{ $url }}" rel="{{ $previous ? 'prev' : 'next' }}" title="{{ $label }}" aria-label="{{ $label }}">
        @include('elements.icon', ['icon' => $icon, 'variant' => 'small', 'classes' => 'app-pagination-arrow'])
    </a>
@else
    <span class="app-pagination-control" title="{{ $label }}" aria-label="{{ $label }}" aria-disabled="true">
        @include('elements.icon', ['icon' => $icon, 'variant' => 'small', 'classes' => 'app-pagination-arrow'])
    </span>
@endif
