@if($stream->streamTips)
    <div class="stream-tips px-3 pt-3">
        <div class="px-3">
            <div class="row">
                @foreach($stream->streamTips->reverse()->slice(0,3) as $tip)
                    @include('elements.streams.stream-tip-box', ['tip' => $tip])
                @endforeach
            </div>
        </div>
    </div>
@endif
