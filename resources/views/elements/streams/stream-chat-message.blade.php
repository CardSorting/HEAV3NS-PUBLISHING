<div class="d-flex {{$streamOwnerId == Auth::user()->id ? '' : 'mb-1'}} stream-chat-message align-items-center {{isset($message) ? ' ' : 'stream-chat-message-template'}} {{$streamOwnerId == Auth::user()->id ? 'isOwner' : ''}} justify-content-between" data-commentid="{{$message->id}}">
    <div>
        <span class="chat-message-user">
            <span>
                <span class="fw-bold {{$streamOwnerId == $message->user->id ? 'text-success' : 'text-orange'}}">{{isset($message) ? $message->user->username : ''}}</span>:
            </span>
        </span>
        <span class="chat-message-content">{{isset($message) ? $message->message : ''}}</span>
    </div>
    <div class="chat-message-action me-1 d-none p-1">
        <button type="button" class="ms-1 h-pill h-pill-primary rounded border-0" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__("Delete")}}" aria-label="{{__("Delete")}}" onclick="Stream.deleteComment({{$message->id}})">
            @include('elements.icon',['icon'=>'trash-outline'])
        </button>
    </div>
</div>
