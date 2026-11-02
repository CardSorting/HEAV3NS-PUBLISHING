@if(Session::has('error') || Session::has('success') || Session::has('warning'))
    <div class="alert-box {{isset($classes) ? $classes : ''}}">
@endif
@if(Session::has('error'))
    <div class="error-message alert text-white border-0 alert-dismissible alert-danger mb-0">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{__('Close')}}"></button>
        {{ Session::get("error") }}
    </div>
@endif

@if(Session::has('success'))
    <div class="alert text-white border-0 alert-dismissible alert-success mb-0">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{__('Close')}}"></button>
        {{ Session::get("success") }}
    </div>
@endif

@if(Session::has('warning'))
    <div class="alert text-white border-0 alert-dismissible alert-warning mb-0">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="{{__('Close')}}"></button>
        {{ Session::get("warning") }}
    </div>
@endif

@if(Session::has('error') || Session::has('success') || Session::has('warning'))
    </div>
@endif
