<div class="post-composer-footer">
    <div class="post-composer-settings" aria-live="polite" hidden>
        <button type="button" class="h-pill h-pill-primary post-composer-setting post-price-summary" {{ $canCompose ? '' : 'disabled' }} onclick="PostCreate.showSetPricePostDialog()" aria-haspopup="dialog" aria-controls="post-set-price-dialog" hidden>
            @include('elements.icon', ['icon' => 'logo-usd', 'variant' => 'medium', 'centered' => true])
            <span>{{ __('Price') }}: <span class="post-price-label"></span></span>
        </button>
        @if(getSetting('feed.allow_post_scheduling'))
            <button type="button" class="h-pill h-pill-primary post-composer-setting post-schedule-summary" {{ $canCompose ? '' : 'disabled' }} onclick="PostCreate.showPostScheduleDialog()" aria-haspopup="dialog" aria-controls="post-set-schedule-dialog" hidden>
                @include('elements.icon', ['icon' => 'time-outline', 'variant' => 'medium', 'centered' => true])
                <span></span>
            </button>
        @endif
        @if(getSetting('feed.allow_post_polls'))
            <button type="button" class="h-pill h-pill-primary post-composer-setting post-poll-summary" {{ $canCompose ? '' : 'disabled' }} onclick="PostCreate.showPollEditDialog()" aria-haspopup="dialog" aria-controls="post-set-poll-dialog" hidden>
                @include('elements.icon', ['icon' => 'stats-chart-outline', 'variant' => 'medium', 'centered' => true])
                <span>{{ __('Poll added') }}</span>
            </button>
        @endif
        @if(getSetting('profiles.enable_new_post_notification_setting'))
            <button type="button" class="h-pill h-pill-primary post-composer-setting post-notification-summary" {{ $canCompose ? '' : 'disabled' }} onclick="PostCreate.togglePostNotifications()" hidden>
                @include('elements.icon', ['icon' => 'notifications-outline', 'variant' => 'medium', 'centered' => true])
                <span>{{ __('Email followers') }}</span>
            </button>
        @endif
    </div>
    <div class="post-composer-toolbar">
        <div class="post-composer-add-actions">
            <button type="button" class="h-pill h-pill-primary post-composer-action file-upload-button {{ ($canUploadMedia ?? $canCompose) ? '' : 'disabled' }}" {{ ($canUploadMedia ?? $canCompose) ? '' : 'disabled' }}>
                @include('elements.icon', ['icon' => 'cloud-upload-outline', 'variant' => 'medium', 'centered' => true])
                <span>{{ __('Upload') }}</span>
            </button>
            @if($canCompose)
                @include('elements.vault.button', ['vaultButtonClass' => 'h-pill h-pill-primary post-composer-action vault-picker-toolbar'])
            @endif
            <button type="button" class="h-pill h-pill-primary post-composer-action post-price-button" {{ $canCompose ? '' : 'disabled' }} onclick="PostCreate.showSetPricePostDialog()" aria-label="{{ __('Set post price') }}" aria-haspopup="dialog" aria-controls="post-set-price-dialog">
                @include('elements.icon', ['icon' => 'logo-usd', 'variant' => 'medium', 'centered' => true])
                <span>{{ __('Price') }}</span>
            </button>
            @if(!$isEditing || getSetting('feed.allow_post_polls') || getSetting('feed.allow_post_scheduling') || getSetting('profiles.enable_new_post_notification_setting'))
                <div class="dropdown post-composer-more">
                    <button type="button" id="post-composer-more-button" class="h-pill h-pill-primary post-composer-action" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-controls="post-composer-more-menu" aria-haspopup="true">
                        @include('elements.icon', ['icon' => 'ellipsis-horizontal-outline', 'variant' => 'medium', 'centered' => true])
                        <span>{{ __('More') }}</span>
                    </button>
                    <div id="post-composer-more-menu" class="dropdown-menu" aria-labelledby="post-composer-more-button">
                        @if(getSetting('feed.allow_post_polls'))
                            <button type="button" class="dropdown-item post-poll-button" {{ $canCompose ? '' : 'disabled' }} onclick="PostCreate.showPollEditDialog()" aria-haspopup="dialog" aria-controls="post-set-poll-dialog" aria-describedby="post-poll-state">
                                @include('elements.icon', ['icon' => 'stats-chart-outline', 'variant' => 'medium', 'centered' => true])
                                <span>{{ __('Manage poll') }}</span>
                                <span id="post-poll-state" class="visually-hidden">{{ __('Not set') }}</span>
                                <span class="post-setting-check post-poll-check" hidden aria-hidden="true">@include('elements.icon', ['icon' => 'checkmark-outline', 'variant' => 'small', 'centered' => true])</span>
                            </button>
                        @endif
                        @if(getSetting('feed.allow_post_scheduling'))
                            <button type="button" class="dropdown-item post-schedule-button" {{ $canCompose ? '' : 'disabled' }} onclick="PostCreate.showPostScheduleDialog()" aria-haspopup="dialog" aria-controls="post-set-schedule-dialog" aria-describedby="post-schedule-state">
                                @include('elements.icon', ['icon' => 'alarm-outline', 'variant' => 'medium', 'centered' => true])
                                <span>{{ __('Scheduling') }}</span>
                                <span id="post-schedule-state" class="visually-hidden">{{ __('Not set') }}</span>
                                <span class="post-setting-check post-schedule-check" hidden aria-hidden="true">@include('elements.icon', ['icon' => 'checkmark-outline', 'variant' => 'small', 'centered' => true])</span>
                            </button>
                        @endif
                        @if(getSetting('profiles.enable_new_post_notification_setting'))
                            <button type="button" class="dropdown-item post-notification-button" {{ $canCompose ? '' : 'disabled' }} onclick="PostCreate.togglePostNotifications()" aria-pressed="false">
                                @include('elements.icon', ['icon' => 'notifications-outline', 'variant' => 'medium', 'centered' => true])
                                <span>{{ __('Email followers') }}</span>
                                <span class="post-setting-check post-notification-check" hidden aria-hidden="true">@include('elements.icon', ['icon' => 'checkmark-outline', 'variant' => 'small', 'centered' => true])</span>
                            </button>
                        @endif
                        @unless($isEditing)
                            @if(getSetting('feed.allow_post_polls') || getSetting('feed.allow_post_scheduling') || getSetting('profiles.enable_new_post_notification_setting'))<div class="dropdown-divider"></div>@endif
                            <button type="button" class="dropdown-item text-danger draft-clear-button">
                                @include('elements.icon', ['icon' => 'trash-outline', 'variant' => 'medium', 'centered' => true])
                                <span>{{ __('Clear draft') }}</span>
                            </button>
                        @endunless
                    </div>
                </div>
            @endif
        </div>
        <button type="button" class="btn btn-primary creator-submit-button post-create-button mb-0" {{ $canCompose ? '' : 'disabled' }}>{{ $isEditing ? __('Save changes') : __('Post') }}</button>
    </div>
</div>
