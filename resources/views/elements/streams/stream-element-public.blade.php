<div class="px-2 list-item">
     <span class="list-link d-flex flex-column pt-2 pb-2 rounded {{--pointer-cursor--}}">
         <div class="d-flex flex-row-no-rtl justify-content-between">
             <div class="text-truncate overflow-hidden">
                 <div class="d-flex align-items-center" >
                    <div class="me-2">
                         <img src="{{$stream->poster}}" class="rounded stream-cover-public" alt="{{$stream->name}}">
                     </div>
                 <div class="d-flex flex-column overflow-hidden text-truncate me-3">
                     <h6 class="mb-1 d-flex pe-2 text-truncate">
                         <span class="text-truncate d-flex align-items-center">
                             @if($showLiveIndicators && $stream->status == 'in-progress')
                            <div>
                                 <div class="blob red me-3"></div>
                            </div>
                             @endif
                             @if($stream->status == 'in-progress')
                                 <a href="{{route('public.stream.get',['streamID'=>$stream->id,'slug'=>$stream->slug])}}" class="text-dark-r">{{$stream->name}}</a>
                             @else
                                 @if($stream->settings && $stream->settings['dvr'] && $stream->vod_link)
                                     <a href="{{route('public.vod.get',['streamID'=>$stream->id,'slug'=>$stream->slug])}}" class="text-dark-r">{{$stream->name}}</a>
                                 @else
                                     {{$stream->name}}
                                 @endif
                             @endif
                         </span>
                     </h6>
                     <small class="text-muted text-truncate overflow-hidden d-flex">
                         <span class="text-truncate">{{__("Started streaming")}} {{$stream->created_at->diffForHumans(null,false,true)}}@if($showUsername), by <a class="text-muted" href="{{route('profile',['username'=>$stream->user->username])}}"><span>@</span>{{$stream->user->username}}</a> @endif</span></small>
                     </div>
                 </div>
             </div>

             <div class="d-flex justify-content-between align-items-center pe-3">
                @if(($stream->status == 'ended' && $stream->settings && $stream->settings['dvr'] && $stream->vod_link) || $stream->status == 'in-progress')
                     @if($stream->price == 0)
                         <span class="badge text-bg-success stream-badge-label me-2">Free</span>
                     @else
                         <span class="badge text-bg-warning stream-badge-label me-2">🔒 PPV</span>
                     @endif

                     @if($stream->requires_subscription)
                         <span class="badge text-bg-warning stream-badge-label me-2">🔒 Sub</span>
                     @endif
                 @endif
                 @if(($stream->status == 'ended' && $stream->settings && $stream->settings['dvr'] && $stream->vod_link) || $stream->status == 'in-progress')
                     <a class="h-pill h-pill-accent rounded me-2" href="{{$stream->status == 'in-progress' ?  route('public.stream.get',['streamID'=>$stream->id,'slug'=>$stream->slug]) : route('public.vod.get',['streamID'=>$stream->id,'slug'=>$stream->slug])}}" title="{{__('View stream')}}" aria-label="{{__('View stream')}}">
                          @include('elements.icon',['icon'=>'eye-outline'])
                      </a>
                 @else
                     <span class="h-pill h-pill-accent rounded me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Stream VOD unavailable')}}">
                          @include('elements.icon',['icon'=>'eye-off-outline'])
                      </span>
                 @endif
             </div>
         </div>
     </span>
</div>
