@extends('layouts.install')
@section('page_title', __('Install the script'))
@section('content')
    <div class="container-fluid installer-bg px-3">
        <div class="row g-0 d-flex justify-content-center align-items-center min-vh-100 py-4">
            <div class="col-12 col-md-8 col-lg-6 col-xl-4">
                <div class="d-flex justify-content-center pb-5">
                    <img class="brand-logo" src="{{ asset('/img/logo-black.png') }}" alt="{{ config('app.site.name') }}">
                </div>
                <div class="col card shadow-sm rounded-xl">
                    <div class="card-body p-4">
                        <h4 class="card-title mt-2 mb-1 fw-bold">{{__('Requirements')}}</h4>
                        <p class="text-sm text-muted">{{__("Overall, minimal server requirements.")}}</p>
                        <hr/>
                        <div class="fw-bold mb-2">
                            {{__("Mandatory")}}
                        </div>
                        <div class="row mb-1">
                            <div class="col-8"><span>{{__("PHP Version")}}: {{phpversion()}} ({{ __('minimum :version', ['version' => \App\Support\PhpRuntimeRequirements::MINIMUM_VERSION]) }})</div>
                            <div class="col-4 d-flex justify-content-end">
                                @if(\App\Support\PhpRuntimeRequirements::supports())
                                    @include('elements.icon',['icon'=>'checkmark-circle-outline','variant'=>'medium', 'classes'=>'text-success'])
                                @else
                                    @include('elements.icon',['icon'=>'close-circle-outline','variant'=>'medium', 'classes'=>'text-warning'])
                                @endif
                            </div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">PHP allow_url_fopen</div>
                            <div class="col-4 d-flex justify-content-end ">
                                @if(ini_get('allow_url_fopen'))
                                    @include('elements.icon',['icon'=>'checkmark-circle-outline','variant'=>'medium', 'classes'=>'text-success'])
                                @else
                                    @include('elements.icon',['icon'=>'close-circle-outline','variant'=>'medium', 'classes'=>'text-danger'])
                                @endif
                            </div>
                        </div>
                        @foreach($requiredExtensions as $PHPExtension)
                            <div class="row mb-1">
                                <div class="col-8">{{$PHPExtension}}</div>
                                <div class="col-4 d-flex justify-content-end">
                                    @if(extension_loaded($PHPExtension))
                                        @include('elements.icon',['icon'=>'checkmark-circle-outline','variant'=>'medium', 'classes'=>'text-success'])
                                    @else
                                        @include('elements.icon',['icon'=>'close-circle-outline','variant'=>'medium', 'classes'=>'text-danger'])
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <hr>
                        <div class="fw-bold mb-2">
                            {{__("Optional")}}
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">FFMpeg</div>
                            <div class="col-4 d-flex justify-content-end">
                                <div data-bs-toggle="tooltip" data-bs-placement="top" title="{{__("FFMpeg paths are configured in admin panel. If not available, videos formats will fallback to mp4, uncompressed videos.")}}">
                                    @include('elements.icon',['icon'=>'information-circle-outline','variant'=>'medium', 'classes'=>'text-primary'])
                                </div>
                            </div>
                        </div>
                        <div class="d-flex flex-row-reverse">
                            @if($passesRequirements)
                                <a href="{{route('installer.install').'?step=2'}}" class="btn btn-primary mt-4">{{__("Next")}}</a>
                            @else
                                <span class="btn btn-primary mt-4 disabled">{{__("Next")}}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
