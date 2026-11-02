<div class="mt-3 px-2">
    <div class="lists-wrapper border rounded mt-2">
        @if(!$votedAnswer)
            @foreach($post->poll->answers as $answer)
                <div class="list-item p-1 border-bottom small">
                    <a href="javscript:void(0)" class="list-link d-flex flex-column pt-2 pb-2 ps-3 rounded" onclick="Post.voteForPoll({{$post->poll->id}}, {{$answer->id}})">
                        {{$answer->answer}}
                    </a>
                </div>
            @endforeach
        @else
            @foreach($post->poll->answers as $answer)
                @foreach($pollResults['answers'] as $answerResult)
                    @if($answer->id === $answerResult['id'])
                        <div class="list-item border-bottom small position-relative py-2 px-3">
                            <div>
                                <!-- Background bar (blue) -->
                                <div
                                    class="bg-secondary-neutral position-absolute poll-bar"
                                    data-width="{{ round($answerResult['percentage']) - ($answerResult['percentage'] ? 2 : 0) }}%"
                                >
                                </div>
                                <!-- Content overlay -->
                                <div class="position-relative d-flex align-items-center justify-content-between h-100 py-1 ps-1 {{ $votedAnswer === $answer->id ? 'fw-bold' : '' }}">
                                    <div>
                                        {{ $answer->answer }}
                                    </div>
                                    <div>
                                        ({{ round($answerResult['percentage']) }}%)
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            @endforeach
        @endif
        <div class="ps-3 py-2 ">
            <div class="d-flex align-items-center small">
                @include('elements.icon',['icon'=>'stats-chart-outline','variant'=>'smaller','centered'=>true, 'classes' => 'me-1'])
                {{__("Poll")}} • {{$pollResults['totalVotes']}} {{trans_choice("Votes", $pollResults['totalVotes'])}}
            </div>
        </div>
    </div>
</div>
