@if($requiresSubscription)
    @include('elements.checkout.subscribe-button-30', ['compactSubscribeButton' => true])
@elseif(Auth::check())
    <button type="button" class="btn btn-round btn-sm btn-primary w-100 mb-0 flex-shrink-0 text-wrap manage-follow-button" onclick="Lists.manageFollowsAction('{{$user->id}}')">
        <span class="manage-follows-text">{{__('Follow')}}</span>
    </button>
@else
    <button type="button" class="btn btn-round btn-sm btn-primary w-100 mb-0 flex-shrink-0 text-wrap" data-bs-toggle="modal" data-bs-target="#login-dialog">
        {{__('Follow')}}
    </button>
@endif
