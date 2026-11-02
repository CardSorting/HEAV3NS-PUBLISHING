<div class="{{isset($post->attachments[0]) && AttachmentHelper::hasBlurredPreview($post->attachments[0]) ? 'position-absolute bottom-0' : ''}} w-100 py-3 px-2 px-md-3">
    <div class="card ppv-info-bg rounded-2 border-0">
        <div class="card-body p-3 small">
            {{--  Post attachments info --}}

            <div class="row align-items-center mb-1 pb-2">

                <div class="col-8 col-md-4">
                    <div class="post-locked-counts d-flex flex-wrap gap-2">
                        @foreach(PostsHelper::getAttachmentsTypesCount($post->attachments) as $attachmenttype => $count)
                            @continue(!$count)
                            @switch($attachmenttype)
                                @case('image')
                                    <div class="d-flex justify-content-center align-items-center">
                                        @include('elements.icon',['icon'=>'images-outline','centered' => false,'variant'=>'small', 'classes' => 'me-1']) {{$count}}
                                    </div>
                                    @break
                                @case('video')
                                    <div class="d-flex justify-content-center align-items-center">
                                        @include('elements.icon',['icon'=>'videocam-outline','centered' => false,'variant'=>'small', 'classes' => 'me-1']) {{$count}}
                                    </div>
                                    @break
                                @case('audio')
                                    <div class="d-flex justify-content-center align-items-center">
                                        @include('elements.icon',['icon'=>'musical-notes-outline','centered' => false,'variant'=>'small', 'classes' => 'me-1']) {{$count}}
                                    </div>
                                    @break
                                @case('file')
                                    <div class="d-flex justify-content-center align-items-center">
                                        @include('elements.icon',['icon'=>'document-text-outline','centered' => false,'variant'=>'small', 'classes' => 'me-1']) {{$count}}
                                    </div>
                                    @break
                            @endswitch
                        @endforeach
                        @if(PostsHelper::hasNoMedia($post->attachments))
                            <div class="d-flex justify-content-center align-items-center me-2">
                                @include('elements.icon',['icon'=>'chatbox-ellipses-outline','centered' => false,'variant'=>'small', 'classes' => 'me-1']) {{strlen($post->text)}}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-md-4 d-none d-md-block">
                    <div class="d-none d-md-block">
                        <div class="text-center align-items-center justify-content-center">
                            <div class="{{isset($post->attachments[0]) && AttachmentHelper::hasBlurredPreview($post->attachments[0]) ? 'text-white-50' : 'text-muted'}} small ">
                                {{__('Pay to unlock')}}
                            </div>
                        </div>
                    </div>
                </div>


                <div class="col-4">
                    <div class="d-flex justify-content-end align-items-center text-nowrap">
                        @include('elements.icon',['icon'=>'lock-closed-outline','centered' => false,'variant'=>'small', 'classes' => 'me-1']) {{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($post->price)}}
                    </div>
                </div>

            </div>
            {{--  Sub button --}}
            <div class="">
                <button class=" btn btn-primary w-100 to-tooltip {{(!GenericHelper::creatorCanEarnMoney($post->user) || ($type == 'subscription')) ? 'disabled' : ''}} mb-0"
                        @if(Auth::check())
                            @if(!GenericHelper::creatorCanEarnMoney($post->user) || ($type == 'subscription'))
                                data-bs-placement="top"
                        @if(($type == 'subscription'))
                            title="{{__('A subscription is required.')}}"
                        @else
                            title="{{__('This creator cannot earn money yet')}}"
                        @endif
                        @else
                            data-bs-toggle="modal"
                        data-bs-target="#checkout-center"
                        data-type="post-unlock"
                        data-recipient-id="{{$post->user->id}}"
                        data-amount="{{$post->price}}"
                        data-first-name="{{Auth::user()->first_name}}"
                        data-last-name="{{Auth::user()->last_name}}"
                        data-billing-address="{{Auth::user()->billing_address}}"
                        data-country="{{Auth::user()->country}}"
                        data-city="{{Auth::user()->city}}"
                        data-state="{{Auth::user()->state}}"
                        data-postcode="{{Auth::user()->postcode}}"
                        data-available-credit="{{Auth::user()->wallet->total}}"
                        data-username="{{$post->user->username}}"
                        data-name="{{$post->user->name}}"
                        data-avatar="{{$post->user->avatar}}"
                        data-post-id="{{$post->id}}"
                        @endif
                        @else
                            data-bs-placement="top"
                        title="{{__('You need to login first')}}."
                    @endif
                >{{__('Unlock post for')}} {{\App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount($post->price)}}</button>
            </div>

        </div>
    </div>
</div>
