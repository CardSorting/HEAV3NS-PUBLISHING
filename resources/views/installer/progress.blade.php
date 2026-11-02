@extends('layouts.install')
@section('page_title', __('Installing the script'))
@section('content')
    <div class="container-fluid installer-bg px-3">
        <div class="row g-0 d-flex justify-content-center align-items-center min-vh-100 py-4">
            <div class="col-12 col-md-8 col-lg-6 col-xl-4">
                <div class="d-flex justify-content-center pb-5">
                    <img class="brand-logo" src="{{ asset('/img/logo-black.png') }}">
                </div>

                <div
                    class="col card shadow-sm rounded-xl installer-progress"
                    data-advance-url="{{ route('installer.advance') }}"
                    data-installation-token="{{ $installationToken }}"
                    data-failed-step="{{ $installationState['failed_step'] ?? '' }}"
                    data-label-complete="{{ __('Complete') }}"
                    data-label-in-progress="{{ __('In progress') }}"
                    data-label-pending="{{ __('Pending') }}"
                    data-label-failed="{{ __('Failed') }}"
                    data-message-working="{{ __('Installation is in progress…') }}"
                    data-message-busy="{{ __('The current step is still running…') }}"
                    data-message-failed="{{ __('The previous installation attempt failed. Check the application logs and resolve the error before retrying this step.') }}"
                >
                    <div class="card-body p-4">
                        <h4 class="card-title mt-2 mb-1 fw-bold">{{ __('Installing the script') }}</h4>
                        <p class="text-sm text-muted">
                            {{ __('Please keep this page open. The installer can safely continue from the last completed step.') }}
                        </p>
                        <hr/>

                        @if(session('error'))
                            <div class="alert bg-danger-subtle text-danger-emphasis border border-danger-subtle text-break" role="alert">{{ session('error') }}</div>
                        @endif

                        <div class="list-group mb-3 installer-progress-steps">
                            @foreach($installationSteps as $step => $details)
                                @php
                                    $isCompleted = in_array($step, $installationState['completed'] ?? [], true);
                                    $isFailed = !$isCompleted && ($installationState['failed_step'] ?? null) === $step;
                                    $isCurrent = !$isCompleted && !$isFailed && ($installationState['next_step'] ?? null) === $step;
                                @endphp
                                <div
                                    class="list-group-item d-flex justify-content-between align-items-center gap-2"
                                    data-install-step="{{ $step }}"
                                    data-install-state="{{ $isCompleted ? 'complete' : ($isFailed ? 'failed' : ($isCurrent ? 'in-progress' : 'pending')) }}"
                                    @if($isCurrent) aria-current="step" @endif
                                >
                                    <div>
                                        <div class="fw-semibold">{{ $details['label'] }}</div>
                                        <div class="small text-body-secondary mt-1">{{ $details['description'] }}</div>
                                    </div>
                                    <span class="installer-step-status d-inline-flex align-items-center gap-1 flex-shrink-0 small fw-semibold {{ $isCompleted ? 'text-success-emphasis' : ($isFailed ? 'text-danger-emphasis' : ($isCurrent ? 'text-primary' : 'text-body-secondary')) }}" role="status">
                                        <span class="d-inline-flex align-items-center installer-step-complete {{ $isCompleted ? '' : 'd-none' }}" aria-hidden="true">
                                            @include('elements.icon', ['icon' => 'checkmark-circle-outline', 'variant' => 'small', 'centered' => false])
                                        </span>
                                        <span class="spinner-border spinner-border-sm installer-step-spinner {{ $isCurrent ? '' : 'd-none' }}" aria-hidden="true"></span>
                                        <span class="d-inline-flex align-items-center installer-step-failed {{ $isFailed ? '' : 'd-none' }}" aria-hidden="true">
                                            @include('elements.icon', ['icon' => 'close-circle-outline', 'variant' => 'small', 'centered' => false])
                                        </span>
                                        <span class="installer-step-label">{{ $isCompleted ? __('Complete') : ($isFailed ? __('Failed') : ($isCurrent ? __('In progress') : __('Pending'))) }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>

                        <div class="{{ empty($installationState['failed_step']) ? 'd-none' : '' }} installer-progress-error">
                            <div class="alert bg-danger-subtle text-danger-emphasis border border-danger-subtle mb-0" role="alert">
                                <div class="small text-break installer-progress-error-message">{{ empty($installationState['failed_step']) ? '' : __('The previous installation attempt failed. Check the application logs and resolve the error before retrying this step.') }}</div>
                            </div>
                            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary m-0 installer-progress-restart {{ empty($installationState['failed_step']) ? 'd-none' : '' }}" data-bs-toggle="collapse" data-bs-target="#installer-restart-confirmation" aria-expanded="false" aria-controls="installer-restart-confirmation">
                                    {{ __('Start over') }}
                                </button>
                                <form method="POST" action="{{ route('installer.cancel') }}" class="m-0 d-none installer-progress-change-details">
                                    @csrf
                                    <input type="hidden" name="installation_token" value="{{ $installationToken }}">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary m-0">
                                        {{ __('Change installation details') }}
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-primary m-0 installer-progress-retry">
                                    {{ __('Retry') }}
                                </button>
                            </div>
                            <div class="collapse mt-3" id="installer-restart-confirmation">
                                <form method="POST" action="{{ route('installer.restart') }}" class="bg-body-tertiary border rounded p-3 m-0">
                                    @csrf
                                    <input type="hidden" name="installation_token" value="{{ $installationToken }}">
                                    <h6 class="fw-semibold">{{ __('Start a fresh installation?') }}</h6>
                                    <p class="small text-body-secondary">{{ __('This clears saved installer progress, not your database. Back up any data you need, then clear the partial database or choose a new empty one before installing again.') }}</p>
                                    <div class="form-check mb-3">
                                        <input type="checkbox" class="form-check-input" name="confirm_restart" id="confirm-installer-restart" value="1" required>
                                        <label class="form-check-label small" for="confirm-installer-restart">{{ __('I understand that database data is not reset automatically.') }}</label>
                                    </div>
                                    @error('confirm_restart')
                                        <p class="small text-danger">{{ $message }}</p>
                                    @enderror
                                    <div class="d-flex justify-content-end">
                                        <button type="submit" class="btn btn-sm btn-outline-danger m-0">{{ __('Restart setup') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="text-sm text-muted installer-progress-message">
                            {{ empty($installationState['failed_step']) ? __('Starting installation…') : '' }}
                        </div>

                        <noscript>
                            <div class="alert bg-danger-subtle text-danger-emphasis border border-danger-subtle mt-3" role="alert">
                                {{ __('JavaScript must be enabled to continue the installation.') }}
                            </div>
                        </noscript>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
