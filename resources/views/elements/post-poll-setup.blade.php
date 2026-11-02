<div class="modal fade" tabindex="-1" role="dialog" id="post-set-poll-dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{__('Create a poll')}}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>{{__('Add a few questions to your poll.')}}</p>

                <div class="poll-questions-wrapper">
                    @if(isset($post) && $post->poll)
                        @foreach($post->poll->answers as $answer)
                            <div class="poll-answer mb-3">
                                <div class="d-flex align-items-center">
                                    <input class="form-control" id="{{$answer->id}}" name="questions" placeholder="{{__("Enter a poll question")}}" value="{{$answer->answer}}">
                                    @if($loop->index > 1)
                                        <div class="ms-1 h-pill h-pill-primary rounded react-button w-32 d-flex align-items-center" data-bs-toggle="tooltip" data-bs-placement="top" title="{{__("Cancel")}}" onclick="PostCreate.deletePollAnswer(this)">
                                            <ion-icon name="close-outline"></ion-icon>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="poll-answer mb-3">
                            <div class="d-flex align-items-center">
                                <input class="form-control" name="questions" placeholder="{{__("Enter a poll question")}}">
                            </div>
                        </div>

                        <div class="poll-answer mb-3">
                            <div class="d-flex align-items-center">
                                <input class="form-control" name="questions" placeholder="{{__("Enter a poll question")}}">
                            </div>
                        </div>
                    @endif
                </div>

                <a class="d-flex align-items-center" href="javascript:void(0);" onclick="PostCreate.appendNewPollQuestion();">
                    @include('elements.icon',['icon'=>'add-outline','variant'=>'', 'classes' => 'me-1'])
                    {{__("Add another option")}}
                </a>

            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-white"   onclick="PostCreate.clearPoll()">{{__('Clear')}}</button>
                <button type="button" class="btn btn-primary" onclick="PostCreate.savePoll()">{{__('Save')}}</button>
            </div>
        </div>
    </div>
</div>
