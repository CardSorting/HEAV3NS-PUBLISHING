@extends('layouts.install')
@section('page_title', __('Recover installation'))
@section('content')
    <div class="container-fluid installer-bg px-3">
        <div class="row g-0 d-flex justify-content-center align-items-center min-vh-100 py-4">
            <div class="col-12 col-md-8 col-lg-6 col-xl-4">
                <div class="d-flex justify-content-center pb-5">
                    <img class="brand-logo" src="{{ asset('/img/logo-black.png') }}" alt="{{ config('app.site.name') }}">
                </div>
                <div class="card shadow-sm rounded-xl">
                    <div class="card-body p-4">
                        <h4 class="card-title mt-2 mb-1 fw-bold">{{ __('Setup already started') }}</h4>
                        <p class="text-body-secondary mt-1 mb-3">{{ __('Continue in the original browser using the same website address.') }}</p>
                        <hr>
                        <h6 class="fw-semibold">{{ __('Start over') }}</h6>
                        <p class="small text-body-secondary mt-1">{{ __('If you cannot resume, run this command from your site folder:') }}</p>
                        <pre class="bg-body-tertiary border rounded p-3 mb-3"><code>php artisan installer:reset</code></pre>
                        <h6 class="fw-semibold mt-3">{{ __('No terminal access?') }}</h6>
                        <p class="small text-body-secondary text-break mt-1">
                            {!! strtr(e(__('Use File Manager or FTP to rename :source to :backup.')), [
                                ':source' => '<code>storage/app/installing.json</code>',
                                ':backup' => '<code>installing.json.bak</code>',
                            ]) !!}
                        </p>
                        <p class="small text-body-secondary mb-0">{{ __('Only reset after installation has stopped. Your database is unchanged; back up any data you need and restart with an empty database.') }}</p>
                        <div class="d-flex justify-content-end mt-3">
                            <a class="btn btn-sm btn-primary m-0" href="{{ route('installer.install') }}">{{ __('Check again') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
