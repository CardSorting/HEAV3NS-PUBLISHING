@if(getSetting('profiles.push_notifications_enabled'))
    <div class="card py-3 px-3 my-3" id="push-notifications-box">
        <div class="form-check form-switch">
            <input type="checkbox" class="form-check-input" id="notification_push_enabled">
            <label class="form-check-label" for="notification_push_enabled">{{ __('Push notifications') }}</label>
        </div>
        <div class="mt-1">
            <span class="text-sm text-muted">{{ __('Get push notifications to find out what’s going on when you’re not online.') }}</span>
        </div>
        <div class="mt-2 d-none" id="push-notifications-unsupported">
            <span class="text-sm text-muted">{{ __('Push notifications are not supported on this browser/device.') }}</span>
        </div>
    </div>
@endif

@if(getSetting('profiles.enable_toast_notification_setting'))
    <div class="card py-3 px-3 my-3" id="toast-notifications-box">
        <div class="form-check form-switch">
            <input
                type="checkbox"
                class="form-check-input notification-checkbox"
                id="notification_toast_enabled"
                name="notification_toast_enabled"
                {{ isset(Auth::user()->settings['notification_toast_enabled']) && Auth::user()->settings['notification_toast_enabled'] === 'true' ? 'checked' : '' }}
            >
            <label class="form-check-label" for="notification_toast_enabled">
                {{ __('Toast notifications') }}
            </label>
        </div>

        <div class="mt-1">
            <span class="text-sm text-muted">
                {{ __('Show in-app toast notifications while you are browsing the site.') }}
            </span>
        </div>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <h6 class="mb-0">{{ __('Email notifications') }}</h6>
        <div class="mt-1 mb-3">
            <span class="text-sm text-muted">{{ __('Choose which events can send you an email.') }}</span>
        </div>
        <form>

            @if(getSetting('profiles.enable_new_post_notification_setting'))
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input notification-checkbox" id="notification_email_new_post_created" name="notification_email_new_post_created"
                            {{isset(Auth::user()->settings['notification_email_new_post_created']) ? (Auth::user()->settings['notification_email_new_post_created'] == 'true' ? 'checked' : '') : false}}>
                        <label class="form-check-label" for="notification_email_new_post_created">{{__('New content has been posted')}}</label>
                    </div>
                </div>
            @endif

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input notification-checkbox" id="notification_email_new_sub" name="notification_email_new_sub"
                        {{isset(Auth::user()->settings['notification_email_new_sub']) ? (Auth::user()->settings['notification_email_new_sub'] == 'true' ? 'checked' : '') : false}}>
                    <label class="form-check-label" for="notification_email_new_sub">{{__('New subscription registered')}}</label>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input notification-checkbox" id="notification_email_new_tip" name="notification_email_new_tip"
                        {{isset(Auth::user()->settings['notification_email_new_tip']) ? (Auth::user()->settings['notification_email_new_tip'] == 'true' ? 'checked' : '') : false}}>
                    <label class="form-check-label" for="notification_email_new_tip">{{__('Received a tip')}}</label>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input notification-checkbox" id="notification_email_new_ppv_unlock" name="notification_email_new_ppv_unlock"
                        {{isset(Auth::user()->settings['notification_email_new_ppv_unlock']) ? (Auth::user()->settings['notification_email_new_ppv_unlock'] == 'true' ? 'checked' : '') : false}}>
                    <label class="form-check-label" for="notification_email_new_ppv_unlock">{{__('Your PPV content has been unlocked')}}</label>
                </div>
            </div>


            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input notification-checkbox" id="notification_email_new_message" name="notification_email_new_message"
                        {{isset(Auth::user()->settings['notification_email_new_message']) ? (Auth::user()->settings['notification_email_new_message'] == 'true' ? 'checked' : '') : false}}>
                    <label class="form-check-label" for="notification_email_new_message">{{__('New message received')}}</label>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input notification-checkbox" id="notification_email_new_comment" name="notification_email_new_comment"
                        {{isset(Auth::user()->settings['notification_email_new_comment']) ? (Auth::user()->settings['notification_email_new_comment'] == 'true' ? 'checked' : '') : false}}>
                    <label class="form-check-label" for="notification_email_new_comment">{{__('New comments and replies')}}</label>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input type="checkbox" class="form-check-input notification-checkbox" id="notification_email_expiring_subs" name="notification_email_expiring_subs"
                        {{isset(Auth::user()->settings['notification_email_expiring_subs']) ? (Auth::user()->settings['notification_email_expiring_subs'] == 'true' ? 'checked' : '') : false}}>
                    <label class="form-check-label" for="notification_email_expiring_subs">{{__('Expiring subscriptions')}}</label>
                </div>
            </div>

            @if(getSetting('streams.streaming_driver') !== 'none')
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input notification-checkbox" id="notification_email_creator_went_live" name="notification_email_creator_went_live"
                            {{isset(Auth::user()->settings['notification_email_creator_went_live']) ? (Auth::user()->settings['notification_email_creator_went_live'] == 'true' ? 'checked' : '') : false}}>
                        <label class="form-check-label" for="notification_email_creator_went_live">{{__('A user I am following went live')}}</label>
                    </div>
                </div>
            @endif

        </form>

    </div>
</div>
