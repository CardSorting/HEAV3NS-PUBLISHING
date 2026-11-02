@extends('layouts.user-no-nav')
@section('page_title', $list->name)

@section('styles')
    {!!
        Minify::stylesheet([
            '/css/pages/lists.css'
         ])->withFullUrl()
    !!}
@stop

@section('scripts')
    {!!
        Minify::javascript([
            '/js/ListsPaginator.js',
            '/js/pages/lists.js'
         ])->withFullUrl()
    !!}
@stop

@section('content')
    <div class="d-flex flex-wrap">
        <div class="min-vh-100 border-end col-12 pe-md-0 px-0">
            <div class="pt-4 ps-4 px-3 d-flex justify-content-between align-items-center pb-3 border-bottom">
                <h5 class="mb-0 text-truncate fw-bold text-dark-r">{{__($list->name)}}</h5>
                <div class="d-flex align-items-center flex-shrink-0 me-2">
                    <a title="{{__('Search list members')}}" class="h-pill h-pill-primary pointer-cursor list-members-search-toggle {{request()->filled('query') ? 'active' : ''}} rounded-circle d-flex align-items-center justify-content-center mb-0 me-2" data-bs-toggle="tooltip" data-bs-placement="bottom">
                        @include('elements.icon',['icon'=>'search-outline','variant'=>'mediumish'])
                    </a>
                    @if($list->isManageable)
                        <div class="dropdown dropstart">
                            <a class="btn dropdown-toggle ellipsis-dropdown-toggle mb-0" data-bs-toggle="dropdown" href="#" role="button" aria-label="{{__('List actions')}}" aria-haspopup="true" aria-expanded="false">
                                @include('elements.icon',['icon'=>'ellipsis-horizontal-outline'])
                            </a>
                            <div class="dropdown-menu">
                                <!-- Dropdown menu links -->
                                <a class="dropdown-item" href="javascript:void(0);" onclick="Lists.showListEditDialog('edit')">{{__('Rename list')}}</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" onclick="Lists.showListClearConfirmation()">{{__('Clear list')}}</a>
                                <a class="dropdown-item" href="javascript:void(0);" onclick="Lists.showListDeleteConfirmation()">{{__('Delete list')}}</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            <div class="list-members-search-wrapper {{request()->filled('query') ? '' : 'd-none'}} px-3 py-2 border-bottom">
                <div class="list-members-search-field d-flex align-items-center">
                    <span class="list-members-search-icon text-muted d-flex align-items-center">
                        @include('elements.icon',['icon'=>'search-outline','variant'=>'small'])
                    </span>
                    <input type="search" class="form-control list-members-search-input border-0 shadow-none" placeholder="{{__('Search list members')}}" autocomplete="off" value="{{request()->get('query')}}">
                    <button type="button" class="btn btn-link list-members-search-clear {{request()->filled('query') ? '' : 'd-none'}} p-0 text-muted mb-0" aria-label="{{__('Clear search')}}">
                        @include('elements.icon',['icon'=>'close-outline','variant'=>'small'])
                    </button>
                </div>
            </div>
            <div class="mx-4 pt-2">
                <div class="list-wrapper">
                    <template id="list-members-empty-default-template">
                        @include('elements.lists.list-members-empty-state', $listEmptyState)
                    </template>
                    <template id="list-members-empty-search-template">
                        @include('elements.lists.list-members-empty-state', $listSearchEmptyState)
                    </template>
                    <div class="row list-members-row {{count($members) ? '' : 'd-none'}}">
                        @php(app(\App\Services\UserStaffBadge::class)->preload($members))
                        @foreach($members as $member)
                            @include('elements.lists.list-member-card', ['member' => $member, 'list' => $list])
                        @endforeach
                    </div>
                    <div class="list-members-empty-state {{count($members) ? 'd-none' : ''}}"
                         data-empty-default-template="#list-members-empty-default-template"
                         data-empty-search-template="#list-members-empty-search-template">
                        @if(request()->filled('query'))
                            @include('elements.lists.list-members-empty-state', $listSearchEmptyState)
                        @else
                            @include('elements.lists.list-members-empty-state', $listEmptyState)
                        @endif
                    </div>
                </div>
                @include('elements.feed.posts-loading-spinner')

            </div>

        </div>
    </div>
    @include('elements.lists.list-update-dialog',['mode'=>'edit'])
    @include('elements.lists.list-delete-dialog')
    @include('elements.lists.list-member-delete-dialog')
    @include('elements.lists.list-clear-dialog')
@stop
