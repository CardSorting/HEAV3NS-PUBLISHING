<div class="list-item">
     <span class="list-link d-flex flex-column pt-2 pb-2 rounded">
         <div class="d-flex flex-row-no-rtl justify-content-between">
             <div class="text-truncate overflow-hidden">

                 <div class="d-flex align-items-center" >
                    <div class="me-2">
                         <img src="{{$stream->poster}}" class="rounded stream-cover {{$isLive ? 'active-stream-poster' : ''}}" alt="{{$stream->name}}">
                     </div>
                     <div class="d-flex flex-column overflow-hidden text-truncate me-3">
                         <h6 class="mb-1 d-flex pe-2 {{$isLive ? 'active-stream-name' : ''}} text-truncate">
                             <span class="text-truncate">
                                 @if($isLive)
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
                         <small class="text-muted">{{__("Created at")}}: {{$stream->created_at_short}}  @if(!$isLive)• {{__('Length')}}: {{round($stream->duration)}} {{trans_choice('minute',round($stream->duration))}}.@endif</small>
                     </div>
                 </div>

             </div>
             <div class="d-flex justify-content-between align-items-center pe-3">

                 @if($isLive && GenericHelper::canPublishContent())
                     @if($stream->driver === \App\Model\Stream::PUSHR_DRIVER)
                         <button type="button" class="h-pill h-pill-accent rounded me-2 show-stream-details-label border-0" onclick="Streams.showStreamDetailsDialog({{$stream->id}},'{{$stream->rtmp_server}}','{{$stream->rtmp_key}}')" title="{{__('Stream details')}}" aria-label="{{__('Stream details')}}">
                            @include('elements.icon',['icon'=>'server-outline'])
                         </button>
                     @endif
                     @if($stream->driver === \App\Model\Stream::LIVEKIT_DRIVER)
                         <a class="h-pill h-pill-accent rounded me-2 show-stream-details-label" href="{{route('my.streams.livekit.broadcast')}}" title="{{__('Open broadcaster')}}" aria-label="{{__('Open broadcaster')}}">
                            @include('elements.icon',['icon'=>'videocam-outline'])
                         </a>

                     @endif
                 @endif

                 @if($isLive)
                     <button type="button" class="h-pill h-pill-accent rounded me-2 border-0" onclick="Streams.showStreamEditDialog('edit',{{$stream->id}})" title="{{__('Edit stream')}}" aria-label="{{__('Edit stream')}}">
                         @include('elements.icon',['icon'=>'create-outline'])
                     </button>
                 @endif

                 @if($isLive)
                     <a class="h-pill h-pill-accent rounded me-2" href="{{route('public.stream.get',['streamID'=>$stream->id,'slug'=>$stream->slug])}}" title="{{__('View stream')}}" aria-label="{{__('View stream')}}">
                          @include('elements.icon',['icon'=>'eye-outline'])
                      </a>
                 @else
                     @if(($stream->status == 'ended' && $stream->settings && $stream->settings['dvr'] && $stream->vod_link))
                         <a class="h-pill h-pill-accent rounded me-2" href="{{route('public.vod.get',['streamID'=>$stream->id,'slug'=>$stream->slug])}}" title="{{__('View stream')}}" aria-label="{{__('View stream')}}">
                                  @include('elements.icon',['icon'=>'eye-outline'])
                              </a>
                     @else
                         <span class="h-pill h-pill-accent rounded me-2" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__('Stream VOD unavailable')}}">
                          @include('elements.icon',['icon'=>'eye-off-outline'])
                      </span>
                     @endif
                 @endif

                 @if($isLive)
                     <button type="button" class="h-pill h-pill-accent rounded border-0" onclick="Streams.showStreamStopDialog({{$stream->id}})" title="{{__('End stream')}}" aria-label="{{__('End stream')}}">
                          @include('elements.icon',['icon'=>'stop-circle-outline'])
                      </button>
                 @else
                     <button type="button" class="h-pill h-pill-accent rounded border-0" onclick="Streams.showStreamDeleteDialog({{$stream->id}})" title="{{__('Delete stream')}}" aria-label="{{__('Delete stream')}}">
                         @include('elements.icon',['icon'=>'close-outline'])
                     </button>
                 @endif

             </div>
         </div>
     </span>
</div>
