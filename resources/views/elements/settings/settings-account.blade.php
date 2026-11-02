@php
    $pendingEmailChange = $pendingEmailChange ?? null;
    $emailChangePanelOpen = $errors->has('new_email')
        || $errors->has('email_change_password')
        || $errors->has('email_change');
@endphp

@if(!Auth::user()->email_verified_at) @include('elements.resend-verification-email-box') @endif

@if(session('success'))
    <div class="alert alert-dismissible alert-success text-white fw-bold mt-2" role="alert">
        {{session('success')}}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{__('Close')}}"></button>
    </div>
@endif

@if($errors->has('email_change'))
    <div class="alert alert-danger text-white fw-bold mt-2" role="alert">
        {{$errors->first('email_change')}}
    </div>
@endif

<form method="POST" action="{{route('my.settings.account.save')}}">
    @csrf

    <div class="mb-3">
        <label for="current_password">{{__('Current password')}}</label>
        @include('elements.password-field', [
            'id' => 'current_password',
            'name' => 'password',
            'errorName' => 'password',
            'autocomplete' => 'current-password',
        ])
        @if($errors->has('password'))
            <span class="invalid-feedback d-block" role="alert">
                <strong>{{$errors->first('password')}}</strong>
            </span>
        @endif
    </div>

    <div class="mb-3">
        <label for="new_password">{{__('New password')}}</label>
        @include('elements.password-field', [
            'id' => 'new_password',
            'name' => 'new_password',
            'errorName' => 'new_password',
            'autocomplete' => 'new-password',
        ])
        @if($errors->has('new_password'))
            <span class="invalid-feedback d-block" role="alert">
                <strong>{{$errors->first('new_password')}}</strong>
            </span>
        @endif
    </div>

    <div class="mb-3">
        <label for="confirm_password">{{__('Confirm password')}}</label>
        @include('elements.password-field', [
            'id' => 'confirm_password',
            'name' => 'confirm_password',
            'errorName' => 'confirm_password',
            'autocomplete' => 'new-password',
        ])
        @if($errors->has('confirm_password'))
            <span class="invalid-feedback d-block" role="alert">
                <strong>{{$errors->first('confirm_password')}}</strong>
            </span>
        @endif
    </div>
    <button class="btn btn-primary w-100 rounded me-0" type="submit">{{__('Save')}}</button>

</form>

@if($emailChangeEnabled ?? false)
    <hr class="my-4">

    <div class="account-email-panel"
         data-theme-light-class="account-email-panel-light"
         data-theme-dark-class="account-email-panel-dark">
        <button
            type="button"
            class="account-email-panel-header"
            data-bs-toggle="collapse"
            data-bs-target="#account-email-change-options"
            aria-expanded="{{($emailChangePanelOpen || $pendingEmailChange) ? 'true' : 'false'}}"
            aria-controls="account-email-change-options"
        >
            <span class="account-email-panel-copy">
                <span class="fw-bold d-block">{{$pendingEmailChange ? __('Email change pending') : __('Change email')}}</span>
                <span class="text-muted small d-block">
                    {{$pendingEmailChange
                        ? __('Verify :email to finish updating your account email.', ['email' => $pendingEmailChange->new_email])
                        : __('View your current email or request a verified change.')}}
                </span>
            </span>
            <span class="account-email-panel-caret" aria-hidden="true">
                @include('elements.icon',['icon'=>'chevron-down-outline','centered'=>false])
            </span>
        </button>

        <div id="account-email-change-options" class="collapse {{($emailChangePanelOpen || $pendingEmailChange) ? 'show' : ''}}">
            <div class="account-email-panel-body">
                <div class="mb-3">
                    <label for="account_email">{{__('Account email')}}</label>
                    <input class="form-control" id="account_email" value="{{Auth::user()->email}}" readonly>
                    <small class="form-text text-muted">{{__('Use an email address you can access. Changes require verification.')}}</small>
                </div>

                @if($pendingEmailChange)
                    <div class="account-email-status mb-3" data-theme-dark-class="account-email-status-dark" role="status">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="account-email-status-copy">
                                <div class="account-email-status-title">{{__('Email change pending')}}</div>
                                <div class="account-email-status-text">{{__('Verify :email to finish updating your account email.', ['email' => $pendingEmailChange->new_email])}}</div>
                                @if($pendingEmailChange->expires_at)
                                    <div class="account-email-status-text">{{__('Link expires: :date.', ['date' => $pendingEmailChange->expires_at->toDayDateTimeString()])}}</div>
                                @endif
                            </div>
                            <span class="account-email-status-badge">{{__('Pending')}}</span>
                        </div>

                        <div class="account-email-status-actions mt-3">
                            <form method="POST" action="{{route('my.settings.account.email.resend')}}" class="mb-0">
                                @csrf
                                <button class="btn btn-outline-primary btn-sm rounded mb-0" type="submit">{{__('Resend email')}}</button>
                            </form>

                            <form method="POST" action="{{route('my.settings.account.email.cancel')}}" class="mb-0">
                                @csrf
                                <button class="btn btn-outline-secondary btn-sm rounded mb-0" type="submit">{{__('Cancel change')}}</button>
                            </form>
                        </div>
                    </div>
                @else
                    <form method="POST" action="{{route('my.settings.account.email.store')}}" class="mb-0">
                        @csrf

                        <div class="mb-3">
                            <label for="new_email">{{__('New email')}}</label>
                            <input
                                type="email"
                                class="form-control {{$errors->has('new_email') ? 'is-invalid' : ''}}"
                                id="new_email"
                                name="new_email"
                                value="{{old('new_email')}}"
                                autocomplete="email"
                            >
                            @if($errors->has('new_email'))
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{$errors->first('new_email')}}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="email_change_password">{{__('Current password')}}</label>
                            @include('elements.password-field', [
                                'id' => 'email_change_password',
                                'name' => 'email_change_password',
                                'errorName' => 'email_change_password',
                                'autocomplete' => 'current-password',
                            ])
                            @if($errors->has('email_change_password'))
                                <span class="invalid-feedback d-block" role="alert">
                                    <strong>{{$errors->first('email_change_password')}}</strong>
                                </span>
                            @endif
                        </div>

                        <button class="btn btn-primary w-100 rounded me-0" type="submit">{{__('Send verification email')}}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endif

@if($accountDeletionEnabled ?? false)
    @php
        $accountDeletionPanelOpen = ($accountDeletionRequest ?? null)
            || $errors->has('delete_request')
            || $errors->has('reason')
            || $errors->has('delete_password')
            || $errors->has('delete_confirmation');
        $accountDeletionMode = $accountDeletionMode ?? \App\Model\UserDeletionRequest::MODE_MANUAL_REVIEW_WITH_COOLDOWN;
        $accountDeletionManualApprovalOnly = $accountDeletionMode === \App\Model\UserDeletionRequest::MODE_MANUAL_REVIEW;
        $accountDeletionRequiresAdminReview = in_array($accountDeletionMode, [
            \App\Model\UserDeletionRequest::MODE_MANUAL_REVIEW,
            \App\Model\UserDeletionRequest::MODE_MANUAL_REVIEW_WITH_COOLDOWN,
        ], true);
        $accountDeletionReviewText = $accountDeletionManualApprovalOnly
            ? __('An admin reviews this before deletion.')
            : __('Admin review and cooldown apply before deletion.');
    @endphp

    <hr class="my-4">

    <div class="account-danger-panel"
         data-theme-light-class="account-danger-panel-light"
         data-theme-dark-class="account-danger-panel-dark">
        <button
            type="button"
            class="account-danger-panel-header"
            data-bs-toggle="collapse"
            data-bs-target="#account-delete-options"
            aria-expanded="{{$accountDeletionPanelOpen ? 'true' : 'false'}}"
            aria-controls="account-delete-options"
        >
            <span class="account-danger-panel-copy">
                <span class="fw-bold text-danger d-block">{{__('Danger zone')}}</span>
                <span class="text-muted small d-block">{{__('Request permanent account deletion. Approval may be required.')}}</span>
            </span>
            <span class="account-danger-panel-caret" aria-hidden="true">
                @include('elements.icon',['icon'=>'chevron-down-outline','centered'=>false])
            </span>
        </button>

        <div id="account-delete-options" class="collapse {{$accountDeletionPanelOpen ? 'show' : ''}}">
            <div class="account-danger-panel-body">
                @if($errors->has('delete_request'))
                    <div class="alert alert-danger text-white fw-bold mb-3" role="alert">
                        {{$errors->first('delete_request')}}
                    </div>
                @endif

                @if($accountDeletionRequest ?? null)
                    @php
                        $accountDeletionStatus = \App\Model\UserDeletionRequest::statusLabels()[$accountDeletionRequest->status]
                            ?? __(ucfirst(str_replace(['-', '_'], ' ', $accountDeletionRequest->status)));
                        $accountDeletionRequestRequiresAdminReview = in_array($accountDeletionRequest->mode, [
                            \App\Model\UserDeletionRequest::MODE_MANUAL_REVIEW,
                            \App\Model\UserDeletionRequest::MODE_MANUAL_REVIEW_WITH_COOLDOWN,
                        ], true);
                        $accountDeletionStatusTone = match ($accountDeletionRequest->status) {
                            \App\Model\UserDeletionRequest::STATUS_APPROVED => 'success',
                            \App\Model\UserDeletionRequest::STATUS_BLOCKED => 'danger',
                            default => 'info',
                        };

                        if ($accountDeletionRequest->status === \App\Model\UserDeletionRequest::STATUS_BLOCKED) {
                            $accountDeletionNoticeTitle = __('Deletion request needs attention');
                            $accountDeletionNoticeText = __('Please review the pending account items before deletion can continue.');
                        } elseif ($accountDeletionRequest->status === \App\Model\UserDeletionRequest::STATUS_APPROVED) {
                            $accountDeletionNoticeTitle = __('Deletion request approved');
                            $accountDeletionNoticeText = __('Deletion can run after the cooldown period.');
                        } elseif ($accountDeletionRequestRequiresAdminReview) {
                            $accountDeletionNoticeTitle = __('Deletion request pending review');
                            $accountDeletionNoticeText = __('An admin will review it before anything changes.');
                        } else {
                            $accountDeletionNoticeTitle = __('Deletion request scheduled');
                            $accountDeletionNoticeText = __('Deletion can run after the cooldown period.');
                        }
                    @endphp

                    <div class="account-delete-status account-delete-status-{{$accountDeletionStatusTone}} mb-3" role="status">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="account-delete-status-copy">
                                <div class="account-delete-status-title">{{$accountDeletionNoticeTitle}}</div>
                                <div class="account-delete-status-text">{{$accountDeletionNoticeText}}</div>
                            </div>
                            <span class="account-delete-status-badge">{{$accountDeletionStatus}}</span>
                        </div>
                        @if($accountDeletionRequest->eligible_for_deletion_at)
                            <div class="account-delete-status-date">{{__('Earliest deletion date: :date.', ['date' => $accountDeletionRequest->eligible_for_deletion_at->toDayDateTimeString()])}}</div>
                        @endif
                    </div>

                    @if($accountDeletionUsersMayCancel ?? false)
                        <form method="POST" action="{{route('my.settings.account.delete-request.cancel')}}" class="mb-0">
                            @csrf
                            <button class="btn btn-outline-secondary w-100 rounded me-0" type="submit">{{__('Cancel deletion request')}}</button>
                        </form>
                    @endif
                @else
                    @if(!empty($accountDeletionBlockingReasons))
                        <div class="account-delete-requirements mb-0" role="status">
                            <div class="account-delete-requirements-title">{{__('Before deletion can continue')}}</div>
                            <div class="account-delete-requirements-text">{{__('Please resolve these account items first.')}}</div>
                            <ul class="account-delete-requirements-list">
                                @foreach($accountDeletionBlockingReasons as $blockingReason)
                                    <li>
                                        <span class="account-delete-requirement-dot" aria-hidden="true"></span>
                                        <span>{{$blockingReason}}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <form method="POST" action="{{route('my.settings.account.delete-request')}}" class="mb-0">
                            @csrf

                            @if($accountDeletionRequiresAdminReview)
                                <div class="account-delete-review-note mb-3" role="status">
                                    <span class="account-delete-review-icon" aria-hidden="true">
                                        @include('elements.icon',['icon'=>'shield-checkmark-outline','centered'=>false])
                                    </span>
                                    <span class="account-delete-review-copy">
                                        <span class="account-delete-review-title">{{__('Admin review required')}}</span>
                                        <span class="account-delete-review-text">{{$accountDeletionReviewText}}</span>
                                    </span>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label for="delete_reason">{{__('Reason')}}</label>
                                <textarea id="delete_reason" name="reason" class="form-control" rows="3" maxlength="2000" placeholder="{{__('Optional')}}">{{old('reason')}}</textarea>
                                @if($errors->has('reason'))
                                    <span class="invalid-feedback d-block" role="alert">
                                        <strong>{{$errors->first('reason')}}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label for="delete_password">{{__('Current password')}}</label>
                                @include('elements.password-field', [
                                    'id' => 'delete_password',
                                    'name' => 'delete_password',
                                    'errorName' => 'delete_password',
                                    'autocomplete' => 'current-password',
                                ])
                                @if($errors->has('delete_password'))
                                    <span class="invalid-feedback d-block" role="alert">
                                        <strong>{{$errors->first('delete_password')}}</strong>
                                    </span>
                                @endif
                            </div>

                            <div class="account-delete-confirm mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="delete_confirmation" name="delete_confirmation" value="1">
                                    <label class="form-check-label" for="delete_confirmation">
                                        <span class="account-delete-confirm-title">{{__('Confirm this request')}}</span>
                                        <span class="account-delete-confirm-text">{{__('I understand that an approved request can permanently delete my account and content.')}}</span>
                                    </label>
                                </div>
                                @if($errors->has('delete_confirmation'))
                                    <span class="invalid-feedback d-block" role="alert">
                                        <strong>{{$errors->first('delete_confirmation')}}</strong>
                                    </span>
                                @endif
                            </div>

                            <button class="btn btn-danger w-100 rounded me-0" type="submit">{{__('Request account deletion')}}</button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endif
