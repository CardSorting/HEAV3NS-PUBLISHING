<div class="stream-details d-flex justify-content-between">
    <div class="me-4 overflow-hidden">

        <div class="d-flex flex-row">
            <div class="d-flex justify-content-center">
                <img class="rounded-circle avatar" src="{{$stream->user->avatar}}" alt="{{$stream->user->username}}">
            </div>
            <div class="ps-3 w-100 d-flex align-items-center">
                <div>
                    <div class="d-flex flex-column overflow-hidden">
                        <div class="max-width-150 d-flex align-items-center h5 fw-bold">
                            <a href="{{route('profile',['username'=>$stream->user->username])}}" class="text-dark-r text-truncate">{{$stream->user->name}}</a>
                            @if(GenericHelper::isUserVerified($stream->user))
                                <span class="d-inline-flex align-items-center flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Verified user')}}">
                                @include('elements.icon',['icon'=>'verified','centered'=>true,'classes'=>'ms-1', 'variant' => 'small'])
                            </span>
                            @endif
                            @include('elements.user-staff-badge', ['user' => $stream->user, 'badgeVariant' => 'small'])
                        </div>

                    </div>
                    @if(!isset($streamEnded))
                        <span class=""><span class=""><span class="live-stream-users-count">0</span> {{__("Watching")}} </span>• {{__("Started streaming")}} {{\Carbon\Carbon::parse($stream->created_at)->diffForhumans()}}.</span>
                    @else
                        {{__('Stream ended :time time ago',['time'=>\Carbon\Carbon::parse($stream->ended_at)->diffForhumans()])}}
                    @endif
                </div>

            </div>
        </div>

    </div>
    @if(!isset($streamEnded))
        <div class="d-flex align-items-center">
            @if(isset($subLocked) && $stream->user->id !== Auth::user()->id)
                <div class="d-none d-sm-block">
                                <button type="button" class="p-pill p-0 ms-2 pointer-cursor to-tooltip stream-subscribe-button"
                                      aria-label="{{__('Subscribe')}}"
                                      @if(!\App\Providers\GenericHelperServiceProvider::creatorCanEarnMoney($stream->user))
                                          data-bs-placement="top"
                                      title="{{__('This creator cannot earn money yet')}}"
                                      @else
                                          data-bs-toggle="modal"
                                      data-bs-target="#checkout-center"
                                      data-type="one-month-subscription"
                                      data-recipient-id="{{$stream->user->id}}"
                                      data-amount="{{$stream->user->profile_access_price}}"
                                      data-first-name="{{Auth::user()->first_name}}"
                                      data-last-name="{{Auth::user()->last_name}}"
                                      data-billing-address="{{Auth::user()->billing_address}}"
                                      data-country="{{Auth::user()->country}}"
                                      data-city="{{Auth::user()->city}}"
                                      data-state="{{Auth::user()->state}}"
                                      data-postcode="{{Auth::user()->postcode}}"
                                      data-available-credit="{{Auth::user()->wallet->total}}"
                                      data-username="{{$stream->user->username}}"
                                      data-name="{{$stream->user->name}}"
                                      data-avatar="{{$stream->user->avatar}}"
                                      data-stream-id="{{$stream->id}}"
                                      @endif
                                >
                                 @include('elements.icon',['icon'=>'person-add-outline'])
                                </button>
                </div>
            @endif

            @if(isset($priceLocked) && $stream->user->id !== Auth::user()->id)
                <div class="d-none d-sm-block">
                                <button type="button" class="p-pill p-0 ms-2 pointer-cursor to-tooltip stream-unlock-button"
                                      aria-label="{{__('Unlock stream')}}"
                                      @if(!GenericHelper::creatorCanEarnMoney($stream->user))
                                          data-bs-placement="top"
                                      title="{{__('This creator cannot earn money yet')}}"
                                      @else
                                          data-bs-toggle="modal"
                                      data-bs-target="#checkout-center"
                                      data-type="stream-access"
                                      data-recipient-id="{{$stream->user->id ? $stream->user->id : ''}}"
                                      data-amount="{{$stream->price}}"
                                      data-first-name="{{Auth::user()->first_name}}"
                                      data-last-name="{{Auth::user()->last_name}}"
                                      data-billing-address="{{Auth::user()->billing_address}}"
                                      data-country="{{Auth::user()->country}}"
                                      data-city="{{Auth::user()->city}}"
                                      data-state="{{Auth::user()->state}}"
                                      data-postcode="{{Auth::user()->postcode}}"
                                      data-available-credit="{{Auth::user()->wallet->total}}"
                                      data-username="{{$stream->user->username}}"
                                      data-name="{{$stream->user->name}}"
                                      data-avatar="{{$stream->user->avatar}}"
                                      data-stream-id="{{$stream->id}}"
                                      @endif
                                >
                                 @include('elements.icon',['icon'=>'lock-open-outline'])
                                </button>
                </div>
            @endif

            @if($stream->canWatchStream && $stream->user->id !== Auth::user()->id)
                <div class="">
                                <button type="button" class="p-pill p-0 ms-2 pointer-cursor to-tooltip"
                                      aria-label="{{__('Send a tip')}}"
                                      @if(!GenericHelper::creatorCanEarnMoney($stream->user))
                                          data-bs-placement="top"
                                      title="{{__('This creator cannot earn money yet')}}"
                                      @else
                                          data-bs-placement="top"
                                      title="{{__('Send a tip')}}"
                                      data-bs-toggle="modal"
                                      data-bs-target="#checkout-center"
                                      data-type="tip"
                                      data-first-name="{{Auth::user()->first_name}}"
                                      data-last-name="{{Auth::user()->last_name}}"
                                      data-billing-address="{{Auth::user()->billing_address}}"
                                      data-country="{{Auth::user()->country}}"
                                      data-city="{{Auth::user()->city}}"
                                      data-state="{{Auth::user()->state}}"
                                      data-postcode="{{Auth::user()->postcode}}"
                                      data-available-credit="{{Auth::user()->wallet->total}}"
                                      data-username="{{$stream->user->username}}"
                                      data-name="{{$stream->user->name}}"
                                      data-avatar="{{$stream->user->avatar}}"
                                      data-recipient-id="{{$stream->user->id}}"
                                      data-stream-id="{{$stream->id}}"
                                    @endif
                                >
                                 @include('elements.icon',['icon'=>'cash-outline'])
                                </button>
                </div>

                <div class="dropdown dropstart">
                    <button type="button" class="dropdown-toggle ellipsis-dropdown-toggle ms-2 pointer-cursor to-tooltip" id="stream-actions-dropdown" data-bs-toggle="dropdown" aria-expanded="false" title="{{__("More")}}" aria-label="{{__("More")}}">
                        @include('elements.icon',['icon'=>'ellipsis-horizontal-outline'])
                    </button>

                    <div class="dropdown-menu" aria-labelledby="stream-actions-dropdown">
                        <button type="button" class="dropdown-item" onclick="Lists.showReportBox({{$stream->user->id}},null,null,{{$stream->id}});">{{__('Report')}}</button>
                    </div>
                </div>

            @endif

            @if(!$stream->isLivekitDriver())
                @if($stream->user->id === Auth::user()->id)
                    @if(GenericHelper::canPublishContent())
                    <div class="d-none d-sm-block">
                        <a class="p-pill ms-2 pointer-cursor to-tooltip" href="{{route('my.streams.get')}}?action=details" title="{{__("Stream details")}}" aria-label="{{__("Stream details")}}">
                            @include('elements.icon',['icon'=>'server-outline'])
                        </a>
                    </div>
                    @endif
                    <div class="d-none d-sm-block">
                        <a class="p-pill ms-2 pointer-cursor to-tooltip" href="{{route('my.streams.get')}}?action=edit" title="{{__("Edit stream")}}" aria-label="{{__("Edit stream")}}">
                            @include('elements.icon',['icon'=>'create-outline'])
                        </a>
                    </div>
                @endif
            @endif

        </div>
    @endif
</div>
