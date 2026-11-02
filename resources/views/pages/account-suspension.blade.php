@extends('layouts.generic')

@section('page_title', __('Account suspended'))

@section('content')
    <main class="container py-4 py-md-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8 col-xl-7">
                <div class="card border shadow-none">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-inline-flex align-items-center justify-content-center p-3 rounded-circle bg-warning-subtle text-warning-emphasis mb-3" aria-hidden="true">
                            @include('elements.icon', ['icon' => 'shield-outline', 'variant' => 'large', 'centered' => false])
                        </div>
                        <h1 class="h4 fw-bold mb-2">{{ __('Account suspended') }}</h1>
                        <p class="text-muted mb-4">{{ __('Normal app access is restricted. You can still manage essential account matters and contact support.') }}</p>

                        <dl class="row g-3 bg-body-tertiary border rounded-3 p-3 p-md-4 mx-0 mb-4">
                            <div class="col-12 mt-0 px-0">
                                <dt class="small text-muted fw-normal mb-1">{{ __('Reason') }}</dt>
                                <dd class="text-break fw-semibold mb-0">{{ $ban->reason }}</dd>
                            </div>
                            @if($ban->policy_reference)
                                <div class="col-12 col-sm-6 px-0 pe-sm-3">
                                    <dt class="small text-muted fw-normal mb-1">{{ __('Policy reference') }}</dt>
                                    <dd class="text-break mb-0">{{ $ban->policy_reference }}</dd>
                                </div>
                            @endif
                            <div class="col-12 col-sm-6 px-0">
                                <dt class="small text-muted fw-normal mb-1">{{ __('Duration') }}</dt>
                                <dd class="mb-0">{{ $ban->expires_at ? __('Until :date', ['date' => $ban->expires_at->toDayDateTimeString()]) : __('Until further notice') }}</dd>
                            </div>
                        </dl>

                        <h2 class="h6 fw-bold mb-3">{{ __('Payments and subscriptions') }}</h2>
                        <p class="small text-muted mb-2">{{ __('Future renewals and new payments are stopped. External cancellations may still be pending; contact support if you see another charge.') }}</p>
                        <p class="small text-muted mb-4">{{ __('Your balance and payment history are preserved. New withdrawal requests are blocked; existing requests keep their usual approval process.') }}</p>

                        <div class="border-top pt-4">
                            <p class="small text-muted mb-3">{{ __('Contact support to request a review of this suspension or help with payments, account deletion, and privacy requests.') }}</p>
                            <div class="d-grid d-sm-flex gap-2">
                                <a class="btn btn-primary mb-0" href="{{ route('contact') }}">{{ __('Contact support') }}</a>
                                <a class="btn btn-outline-primary mb-0" href="{{ route('my.settings', ['type' => 'account']) }}">{{ __('Account settings') }}</a>
                            </div>
                            <div class="d-flex flex-wrap gap-3 small mt-3">
                                <a href="{{ route('my.settings', ['type' => 'subscriptions']) }}">{{ __('Subscriptions') }}</a>
                                <a href="{{ route('my.settings', ['type' => 'payments']) }}">{{ __('Payment history') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@stop
