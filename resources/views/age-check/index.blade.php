@extends('layouts.no-nav')

@section('page_title', __('Age verification'))

@section('content')
    <div class="container age-check-shell">
        <div class="row justify-content-center">
            <div class="col-12 col-md-9 col-lg-7 col-xl-5">
                <div class="card age-check-card">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex justify-content-center align-items-center mb-3">
                            <img class="brand-logo"
                                 src="{{ GenericHelper::getCurrentThemeLogo() }}"
                                 data-theme-light-src="{{ asset(getSetting('site.light_logo')) }}"
                                 data-theme-dark-src="{{ asset(getSetting('site.dark_logo')) }}"
                                 alt="{{ __('Site logo') }}">
                        </div>

                        <div class="text-center mb-3">
                            <span class="age-check-badge">{{ __('Adult access') }}</span>
                        </div>

                        <h4 class="text-center fw-bold mb-2">{{ __('Verify your age to continue') }}</h4>
                        <p class="text-muted text-center mb-4 age-check-copy">
                            {{ __('This website is intended for adults. AgeVerif will confirm eligibility without storing your documents on this site.') }}
                        </p>

                        @if(session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        <div class="age-check-actions">
                            <a href="{{ route('age-check.start', ['return' => request()->query('return', url()->previous())]) }}" class="btn btn-primary w-100">
                                {{ __('Verify with AgeVerif') }}
                            </a>

                            @if(getSetting('compliance.age_verification_cancel_url'))
                                <a href="{{ getSetting('compliance.age_verification_cancel_url') }}" class="btn btn-outline-secondary w-100 mt-2">
                                    {{ __('Leave') }}
                                </a>
                            @endif
                        </div>

                        <p class="text-muted text-center small mt-4 mb-0">
                            {{ __('You will return here automatically after verification.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
