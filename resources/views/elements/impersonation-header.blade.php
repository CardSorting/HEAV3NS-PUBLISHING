@if(Session::get('impersonated'))
    <div class="content text-center block bg-gradient-faded-primary text-dark py-3">
        <span class="fw-bold text-white">{{__("You're logged as")}}: {{\Illuminate\Support\Facades\Auth::user()->name}}</span>
        <a href="{{route('admin.leaveImpersonation')}}" class="fw-light text-white">{{__('Leave impersonation')}}</a>
    </div>
@endif
