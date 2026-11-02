<div class="border rounded shadow-sm w-100 pb-3">
    <div class="creator-header">
        <div style="background:url({{$profile->cover}});" class="card-img suggestion-header-bg border-bottom rounded" alt="{{$profile->name}}"></div>
    </div>
    <div class="ms-4 creator-body">
        <div class="row">
            <div class="col-12 col-md-auto">
                <div class="d-flex justify-content-center">
                    <img src="{{$profile->avatar}}" class="avatar rounded-circle shadow" alt="{{$profile->name}}"/>
                </div>
            </div>
            <div class="ps-0 col-12 col-md-auto">
                <div class="h-50"></div>
                <div class="h-50 d-flex flex-column justify-content-center pt-2">
                    <div class="pe-2">
                        <div class="m-0 h6 text-truncate d-flex  align-items-center">
                            <a href="{{route('profile',['username'=>$profile->username])}}" class="fw-bold text-dark-r me-2 d-flex align-items-center">
                                {{$profile->name}}
                            </a>
                        </div>
                        <div class="m-0 text-truncate small"><a href="{{route('profile',['username'=>$profile->username])}}" class="text-muted">&commat;{{$profile->username}}</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="">
        <div class="mt-4 ms-4">
            <ul class="list-unstyled">
                <li class="d-flex align-items-center" >@include('elements.icon',['icon'=>'checkmark-sharp','centered'=>false, 'classes' => 'me-2 text-muted', 'variant'=>'medium'])  {{__("Full access to this user's content")}}</li>
                <li class="d-flex align-items-center" >@include('elements.icon',['icon'=>'checkmark-sharp','centered'=>false, 'classes' => 'me-2 text-muted', 'variant'=>'medium']) {{__('Direct message with this user')}}</li>
                <li class="d-flex align-items-center" >@include('elements.icon',['icon'=>'checkmark-sharp','centered'=>false, 'classes' => 'me-2 text-muted', 'variant'=>'medium'])  {{__('Cancel your subscription at any time')}}</li>
            </ul>
        </div>
    </div>
</div>
