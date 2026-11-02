<form action="{{ route('search.get')}}" class="search-box-wrapper w-100 {{isset($formClasses) ? $formClasses : ''}}" method="GET">
    <div class="input-group">
        <input type="text" class="form-control shadow-none px-3 py-2 {{isset($inputClasses) ? $inputClasses : ''}}" aria-label="Text input with dropdown button" placeholder="{{__("Search")}}" name="query" value="{{isset($searchTerm) && $searchTerm ? $searchTerm : ''}}">
        <span class="input-group-text">
            <span class="h-pill h-pill-primary rounded file-upload-button" onclick="submitSearch();">
                @include('elements.icon',['icon'=>'search'])
            </span>
        </span>
    </div>
    <input type="hidden" name="filter" value="{{isset($activeFilter) && $activeFilter !== false ? $activeFilter : (getSetting('feed.default_search_widget_filter') ? getSetting('feed.default_search_widget_filter') : 'top')}}" />

    @if(isset($searchFilters['gender']) && $searchFilters['gender'])
        <input type="hidden" name="gender" value="{{$searchFilters['gender']}}" />
    @endif

    @if(isset($searchFilters['min_age']) && $searchFilters['min_age'])
        <input type="hidden" name="min_age" value="{{$searchFilters['min_age']}}" />
    @endif

    @if(isset($searchFilters['max_age']) && $searchFilters['max_age'])
        <input type="hidden" name="max_age" value="{{$searchFilters['max_age']}}" />
    @endif

    @if(isset($searchFilters['location']) && $searchFilters['location'])
        <input type="hidden" name="location" value="{{$searchFilters['location']}}" />
    @endif

</form>
