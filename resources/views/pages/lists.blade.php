@extends('layouts.user-no-nav')
@section('page_title', __('Your lists'))

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
            <div class="pt-4 d-flex justify-content-between align-items-center px-3 pb-3 border-bottom">
                <div>
                    <h5 class="text-truncate fw-bold text-dark-r mb-0">{{__('Lists')}}</h5>
                </div>
                <button  class="btn btn-sm btn-outline-primary mb-0" onclick="Lists.showListEditDialog()">{{ __('Create') }}</button>
            </div>
            <div class="lists-wrapper mt-2">
                @if(count($lists))
                    @foreach($lists as $key => $list)
                        @include('elements.lists.list-box', ['list'=>$list, 'isLastItem' => (count($lists) == $key + 1)])
                    @endforeach
                @else
                    @include('elements.lists.lists-empty-state')
                @endif
            </div>
            @include('elements.feed.posts-loading-spinner')
        </div>
    </div>
    @include('elements.lists.list-update-dialog',['mode'=>'create'])
@stop
