<div class="user-search-box-item mb-4">
    <div class="d-flex flex-nowrap">
        <div class="col-auto ps-3 pe-0">
            <img src="{{$user->avatar}}" class="avatar rounded-circle shadow"/>
        </div>
        <div class="col px-3">
            <div class="d-flex justify-content-between align-items-center gap-1 gap-lg-2">
                <div class="text-truncate user-search-box-info">
                    <div class="m-0 h6 d-flex align-items-center">
                        <a href="{{route('profile',['username'=>$user->username])}}" class="fw-bold text-dark-r text-truncate">
                            {{$user->name}}
                        </a>
                        @if(GenericHelper::isUserVerified($user))
                            <span class="d-inline-flex align-items-center flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Verified user')}}">
                            @include('elements.icon',['icon'=>'verified','centered'=>true,'classes'=>'ms-1'])
                    </span>
                        @endif
                        @include('elements.user-staff-badge', ['user' => $user])
                    </div>
                    <div class="m-0 text-truncate small"><a href="{{route('profile',['username'=>$user->username])}}" class="text-muted">&commat;{{$user->username}}</a></div>
                </div>
                <div class="d-flex align-items-center flex-shrink-0 text-nowrap">
                    @if(GenericHelper::shouldRenderExplorePeopleFollowButton($user))
                        @if(Auth::check())
                            <button
                                type="button"
                                class="btn btn-round btn-outline-primary btn-sm px-2 px-lg-3 mb-0 search-follow-button"
                                aria-label="{{GenericHelper::getExplorePeopleFollowButtonState($user)['title']}}"
                                data-user-id="{{$user->id}}"
                                data-follow-action="{{GenericHelper::getExplorePeopleFollowButtonState($user)['action']}}"
                                data-follow-title="{{GenericHelper::getExplorePeopleFollowButtonState($user)['follow_title']}}"
                                data-unfollow-title="{{GenericHelper::getExplorePeopleFollowButtonState($user)['unfollow_title']}}"
                                data-follow-class="btn-outline-primary"
                                data-unfollow-class="btn-outline-primary"
                                onclick="Lists.toggleSearchFollow('{{$user->id}}', this)"
                            >{{GenericHelper::getExplorePeopleFollowButtonState($user)['title']}}</button>
                        @else
                            <button
                                type="button"
                                class="btn btn-round btn-outline-primary btn-sm px-2 px-lg-3 mb-0"
                                aria-label="{{__('Follow')}}"
                                onclick="bootstrap.Modal.getOrCreateInstance(document.getElementById('login-dialog')).show()"
                            >{{__('Follow')}}</button>
                        @endif
                    @endif
                    <a
                        role="button"
                        class="btn btn-round btn-outline-primary btn-sm px-2 px-lg-3 mb-0 {{GenericHelper::shouldRenderExplorePeopleFollowButton($user) ? 'ms-2' : ''}}"
                        href="{{route('profile',['username'=>$user->username])}}"
                        aria-label="{{__('View profile')}}"
                    >{{__("View")}}</a>
                </div>
            </div>

            <div class="mt-1 description-content line-clamp-3">
                @if($user->bio)
                    @if(getSetting('profiles.allow_profile_bio_markdown'))
                        {!!  GenericHelper::parseProfileMarkdownBio($user->bio) !!}
                    @else
                        {!!GenericHelper::parseSafeHTML($user->bio)!!}
                    @endif
                @else
                    {{__('No description available.')}}
                @endif
            </div>

        </div>
    </div>
</div>
