@extends('layouts.install')
@section('page_title', __('Install the script'))
@section('scripts')
    {!!
        Minify::javascript([
            '/js/Installer.js',
         ])->withFullUrl()
    !!}
@stop
@section('content')
    <div class="container-fluid installer-bg px-3">

        <div class="row g-0 d-flex justify-content-center align-items-center min-vh-100 py-4">
            <div class="col-12 col-md-8 col-lg-6 col-xl-4">
                <div class="d-flex justify-content-center pb-5">
                    <img class="brand-logo" src="{{ asset('/img/logo-black.png') }}" alt="{{ config('app.site.name') }}">
                </div>
                <div class="col card shadow-sm rounded-xl">
                    <div class="card-body p-4">
                        <h4 class="card-title mt-2 mb-1 fw-bold">{{__('Database info')}}</h4>
                        <p class="text-sm text-muted">{{__("Your MySQL/MariaDB connection details.")}}</p>
                        <hr/>
                        @if(session('status'))
                            <div class="alert bg-info-subtle text-info-emphasis border border-info-subtle" role="status">{{ session('status') }}</div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-dismissible alert-danger text-white fw-bold mt-2" role="alert">
                                {{session('error')}}
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{__('Close')}}"></button>
                            </div>
                        @endif
                        <form method="POST" action="{{ route('installer.savedb') }}" data-installer-database-form>
                            @csrf
                            @if(session()->has('installer_database_warning'))
                                <div class="alert bg-warning-subtle text-warning-emphasis border border-warning-subtle" role="alert" data-installer-database-warning>
                                    <div class="fw-semibold">{{ __('This database already contains tables.') }}</div>
                                    <p class="small mt-1 mb-3">{{ __('It may belong to an existing or unfinished installation. Use an empty database for a fresh install; continuing may fail or change existing data. Back up any data you need first.') }}</p>
                                    <div class="form-check mb-0">
                                        <input class="form-check-input @error('confirm_non_empty_database') is-invalid @enderror" type="checkbox" name="confirm_non_empty_database" id="confirm-non-empty-database" value="1">
                                        <label class="form-check-label small" for="confirm-non-empty-database">{{ __('I understand the risks and want to continue with this database.') }}</label>
                                        @error('confirm_non_empty_database')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            @endif
                            <div class="d-flex row">
                                <div class="mb-3 col-6">
                                    <label for="db_host" class="col-form-label">{{ __('Database host') }}</label>
                                    <div class="">
                                        <input id="db_host" placeholder="localhost" type="text" class="form-control @error('db_host') is-invalid @enderror"  name="db_host" value="{{ old('db_host') ? old('db_host') : (session('db_host') ? session('db_host') : 'localhost') }}" autocomplete="db_host" autofocus>
                                        @error('db_host')
                                        <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                        @enderror
                                    </div>
                                </div>
                                <div class="mb-3 col-6">
                                    <label for="db_port" class="col-form-label">{{ __('Database port') }}</label>
                                    <div class="">
                                        <input id="db_port" type="text" class="form-control @error('db_port') is-invalid @enderror"  name="db_port" value="{{ old('db_port') ? old('db_port') : (session('db_port') ? session('db_port') : '3306') }}" autocomplete="db_port" autofocus>
                                        @error('db_port')
                                        <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="db_name" class="col-form-label">{{ __('Database name') }}</label>
                                <div class="">
                                    <input id="db_name" type="text" class="form-control @error('db_name') is-invalid @enderror"  name="db_name" value="{{ old('db_name') ? old('db_name') : (session('db_name') ? session('db_name') : '') }}" autocomplete="db_name" autofocus>
                                    @error('db_name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="db_username" class="col-form-label">{{ __('Database username') }}</label>
                                <div class="">
                                    <input id="db_username" type="text" class="form-control @error('db_username') is-invalid @enderror"  name="db_username" value="{{ old('db_username') ? old('db_username') : (session('db_username') ? session('db_username') : '') }}" autocomplete="db_username" autofocus>
                                    @error('db_username')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="db_password" class="col-form-label">{{ __('Database password') }}</label>
                                <div class="">
                                    @include('elements.password-field', [
                                        'id' => 'db_password',
                                        'name' => 'db_password',
                                        'errorName' => 'db_password',
                                        'value' => old('db_password') ? old('db_password') : (session('db_password') ? session('db_password') : ''),
                                        'autocomplete' => 'db_password',
                                        'autofocus' => true,
                                    ])
                                    @error('db_password')
                                    <span class="invalid-feedback d-block" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <a href="{{route('installer.install').'?step=1'}}" class="">{{__("Back")}}</a>
                                <button type="submit" class="btn btn-primary m-0">{{__("Next")}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
