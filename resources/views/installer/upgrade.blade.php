@extends('layouts.install')
@section('page_title', __('Update the script'))
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
                    <a href="{{route('home')}}">
                        <img class="brand-logo" src="{{asset('/img/logo-black.png')}}">
                    </a>
                </div>
                <div class="col card shadow-sm rounded-xl">
                    <div class="card-body p-4">
                        <h4 class="card-title mt-2 mb-1 fw-bold">{{__('Update the platform')}}</h4>
                        <p class="text-sm text-muted">{{__("Keep your platform up to date with latest JustFans releases.")}}</p>
                        <hr/>
                        @if(!$canMigrate && !session('success'))
                            <div class="alert alert-success text-white fw-bold mt-2" role="alert">
                                {{__("Your database is already up to date. You can still complete the update to refresh the platform caches.")}}
                            </div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert-dismissible alert-danger text-white fw-bold mt-2" role="alert">
                                {{session('error')}}
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        @if(session('success'))
                            <div class="alert alert-dismissible alert-success text-white fw-bold mt-2" role="alert">
                                {{session('success')}}
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif

                        <div>
                            <p>{{__("Before proceeding with an update, please ensure that")}}:</p>
                            <ul>
                                <li>{{__("You've backed up your files.")}}</li>
                                <li>{{__("You've backed up your database.")}}</li>
                                <li>{{__("You've copied the updated files onto your public directory.")}}</li>
                            </ul>
                        </div>
                        <form method="POST" action="{{ route('installer.doUpdate') }}">
                            @csrf
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <a href="{{route('home')}}" class="">{{__("Go home")}}</a>
                                <button type="submit" class="btn btn-primary m-0">{{__("Update")}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
