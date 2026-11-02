<div class="suggestion-box {{isset($classes) ? $classes : ''}} card text-white border-0 {{$cardRadius}}" data-memberuserid="{{$profile->id}}">
    <div style="background: url({{$profile->cover}});" class="card-img suggestion-header-bg {{$cardRadius}}"></div>
    <div class="card-img-overlay p-0">
        <div class="h-100 w-100 p-0 m-0 position-absolute z-index-0">
            <div class="h-50">
            </div>
            <div class="h-50 w-100 half-bg d-flex {{str_replace('rounded-', 'rounded-bottom-', $cardRadius)}}"></div>
        </div>
        <div class="card-text w-100 h-100 d-flex">

            <div class="d-flex align-items-center justify-content-center ps-3 pe-2 z-index-3">
                <img src="{{$profile->avatar}}" class="avatar rounded-circle" alt="{{$profile->username}}{{__("'s avatar")}}" />
            </div>

            <div class="w-100 z-index-3 text-truncate">
                <div class="h-50 d-flex flex-row-reverse pe-1">
                    @if(isset($isListMode) && ($isListManageable))
                        <span class="h-pill h-pill-media h-pill-media--light rounded mt-1 suggestion-card-btn" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{__('Delete')}}" onclick="Lists.showListMemberRemoveModal({{$profile->id}})">
                            @include('elements.icon',['icon'=>'trash-outline','variant'=>'medium'])
                        </span>
                    @endif
                </div>
                <div class="h-50 w-100 z-index-3 d-flex flex-column mt-1 text-truncate pe-2">
                    <div class="m-0 h6"><a href="{{route('profile',['username'=>$profile->username])}}" class="text-white d-flex align-items-center">
                        <span class="text-truncate">{{$profile->name}}</span>
                        @if(GenericHelper::isUserVerified($profile))
                            <span class="d-inline-flex align-items-center flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Verified user')}}">
                                @include('elements.icon',['icon'=>'verified','centered'=>true,'classes'=>'ms-1'])
                            </span>
                        @endif
                        @include('elements.user-staff-badge', ['user' => $profile, 'badgeClasses' => 'text-white'])
                        </a>
                    </div>
                    <div class="m-0 text-truncate small"><span>@</span><a href="{{route('profile',['username'=>$profile->username])}}" class="text-white">{{$profile->username}}</a></div>
                </div>
            </div>

        </div>
    </div>
</div>
