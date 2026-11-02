<div class="messenger-pins-bar d-none flex-shrink-0 border-bottom bg-body-tertiary px-3 py-2">
    <div class="d-flex align-items-center gap-2">
        @include('elements.icon', ['icon' => 'pin-outline', 'variant' => 'small', 'classes' => 'text-body-secondary flex-shrink-0'])
        <button type="button" class="messenger-pin-preview border-0 bg-transparent text-body text-start text-truncate flex-fill overflow-hidden p-0 small"></button>
        <button type="button" class="messenger-pins-view-all border-0 bg-transparent text-primary text-nowrap small p-0 flex-shrink-0">
            <span class="messenger-pins-view-all-label">{{__('View all')}}</span>
            <span class="messenger-pins-count text-body-secondary ms-1"></span>
        </button>
    </div>
</div>
<div class="messenger-pins-history d-none flex-shrink-0 border-bottom px-3 py-1 text-center">
    <button type="button" class="messenger-pins-latest btn btn-link btn-sm mb-0">{{__('Return to latest')}}</button>
</div>
