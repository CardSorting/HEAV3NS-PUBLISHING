@if(getSetting('site.login_page_background_image'))
    <div class="col-md-6 d-none d-md-flex bg-image p-0 m-0">
        <div class="d-flex m-0 p-0  w-100 h-100 bg-image" style="background-image: url('{{getSetting('site.login_page_background_image')}}');">
        </div>
    </div>
@else
    <div class="col-md-6 d-none d-md-flex bg-image p-0 m-0">
        <div class="d-flex m-0 p-0 bg-gradient-primary w-100 h-100">
            <img src="{{asset('/img/pattern-lines.svg')}}" alt="pattern-lines" class="img-fluid opacity-10">
        </div>
    </div>
@endif
