<div class="stream-chat {{$stream->canWatchStream ? '' : 'mt-3'}}">
    @if($stream->canWatchStream)

        @include('elements.streams.stream-tips-wrapper')

        <div class="chat-content conversations-wrapper overflow-hidden pb-1 px-3 flex-fill pt-3">
            <div class="conversation-content pt-1 pb-1 px-2 flex-fill">
                @if($stream->messages->count())
                    @foreach($stream->messages as $message)
                        @include('elements.streams.stream-chat-message',['message'=>$message, 'streamOwnerId' => $stream->user_id])
                    @endforeach
                @endif
                <div
                    class="d-{{$stream->messages->count() ? 'none' : 'flex'}} h-100 align-items-center justify-content-center no-chat-comments-label">
                    @if($stream->status == 'in-progress')
                        @include('elements.streams.stream-chat-empty-state', [
                            'icon' => 'chatbubbles-outline',
                            'title' => __('No chat messages yet'),
                            'copy' => __('Send the first message.'),
                            'classes' => 'h-100',
                        ])
                    @else
                        @include('elements.streams.stream-chat-empty-state', [
                            'icon' => 'time-outline',
                            'title' => __('Stream ended'),
                            'copy' => __('New messages can no longer be sent.'),
                            'classes' => 'h-100',
                        ])
                    @endif

                </div>
            </div>
        </div>

        @if(!isset($streamEnded))
            <div class="conversation-writeup pt-1 pb-1 d-flex align-items-center mb-1">
                <form class="message-form w-100 ps-3">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="receiverID" id="receiverID" value="">
                    <textarea name="message" class="form-control messageBoxInput"
                              placeholder="{{__('Write a message..')}}" onkeyup="textAreaAdjust(this)"></textarea>
                </form>
                <div class="messenger-buttons-wrapper d-flex">
                    <button
                        type="button"
                        class="btn btn-outline-primary btn-rounded-icon messenger-button send-message ms-3 me-4 to-tooltip"
                        onClick="Stream.sendMessage({{$stream->id}})" data-bs-placement="top"
                        title="{{__('Send message')}}">
                        <div class="d-flex justify-content-center align-items-center">
                            @include('elements.icon',['icon'=>'paper-plane','variant'=>''])
                        </div>
                    </button>
                </div>
            </div>
        @endif

    @else
        <div class="stream-chat-no-message">
            @include('elements.streams.stream-chat-empty-state', [
                'icon' => 'lock-closed-outline',
                'title' => __('Chat is locked'),
                'copy' => __('Unlock the stream to view its messages.'),
                'classes' => 'h-100',
            ])
        </div>
    @endif
</div>
