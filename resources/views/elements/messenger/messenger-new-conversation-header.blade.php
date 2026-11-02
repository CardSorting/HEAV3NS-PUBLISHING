<div class="new-conversation-header d-none">
    <div class="details-holder border-bottom px-3 py-3">
        <div class="d-flex justify-content-between px-1">
            <div class="flex-grow-1 ps-0 d-flex">
                <form id="userMessageForm" role="form" autocomplete="off" class="w-100">
                    <div class="mfv-errorBox"></div>
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    @if(!isset($user))
                        <div class="input-holder">
                            <select id="select-repo" name="receiverID" class="repositories form-select form-select-sm" multiple="multiple" placeholder="{{__('To...')}}"></select>
                        </div>
                    @else
                        <input type="hidden" name="receiverID" value="{{$user->id}}">
                    @endif
                </form>

            </div>
            <div class="pe-0 d-flex justify-content-end ps-3 pe-2">
                <div class="d-flex justify-content-center align-items-center">
                    <a class="h-pill h-pill-primary pointer-cursor new-conversation-toggle-all rounded-circle d-flex align-items-center justify-content-center mb-0 me-2" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{__('Select all')}}">
                        @include('elements.icon',['icon'=>'checkmark-done-outline','variant'=>'mediumish'])
                    </a>
                    <a class="h-pill h-pill-primary pointer-cursor new-conversation-close rounded-circle d-flex align-items-center justify-content-center mb-0" data-bs-toggle="tooltip" data-bs-placement="bottom" title="{{__('Close')}}">
                        @include('elements.icon',['icon'=>'close-outline','variant'=>'mediumish'])
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
