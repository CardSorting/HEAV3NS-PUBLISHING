<div class="modal fade" tabindex="-1" role="dialog" id="messageModal">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-header d-flex align-items-center">
                <h5 class="modal-title mb-0 flex-grow-1" id="modal-title-default">
                    {{ __('New message') }}
                </h5>

                <button type="button" class="btn-close ms-1" data-bs-dismiss="modal" aria-label="{{__('Close')}}"></button>
            </div>

            <div class="modal-body">
                <div class="new-message-has-contacts">
                    <form id="userMessageForm" role="form" autocomplete="off">
                        <div class="mfv-errorBox"></div>
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">

                        {{-- Works on BOTH profile + feed --}}
                        <input
                            type="hidden"
                            name="receiverID"
                            id="receiverID"
                            value="{{ isset($user) ? $user->id : '' }}"
                        >

                        {{-- For the context line --}}
                        <input
                            type="hidden"
                            id="receiverUsername"
                            value="{{ isset($user) ? $user->username : '' }}"
                        >

                        {{-- Story context --}}
                        <input type="hidden" id="storyID" value="">

                        {{-- Context line (always present, JS controls content) --}}
                        <div class="mb-2">
                            <span id="dmContextText" class="fw-semibold"></span>
                            <a href="#" id="dmBackLink" class="fw-semibold d-none ms-1">
                                {{ __('Back') }}
                            </a>
                        </div>

                        <div id="message-modal-gif-composer" class="focused mb-0" data-gif-composer data-gif-surface="messenger">
                            <div data-gif-preview-slot></div>
                            <div class="input-holder position-relative">
                                <textarea class="form-control pe-5"
                                          name="message"
                                          placeholder="{{__('Your message')}}"
                                          id="messageText"></textarea>
                                <div class="position-absolute top-0 end-0 h-100 d-flex align-items-center pe-2" data-gif-toolbar-slot></div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="new-message-no-contacts small text-muted mt-2">
                    {{ __("Subscribe to a creator or follow a free profile to message.") }}
                </div>
            </div>

            <div class="modal-footer">
                <div class="new-message-no-contacts">
                    <button type="button" class="btn btn-white mb-0" data-bs-dismiss="modal">
                        {{ __('Close') }}
                    </button>
                </div>

                <div class="new-message-has-contacts">
                    <button type="button"
                            class="btn btn-primary me-0 new-conversation-label mb-0">
                        {{ __('Send') }}
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>
