@if($variant == 'desktop')
    @include('elements.account.sidebar-menu', [
        'menuItems' => $bookmarkTypes,
        'menuRoute' => 'my.bookmarks',
        'menuActive' => $activeTab,
    ])
@else
    <div class="mt-3 inline-border-tabs fw-bold">
        <nav class="nav nav-pills nav-justified pe-3 pe-md-0">
            @foreach($bookmarkTypes as $route => $setting)
                <a class="nav-item nav-link {{$activeTab == $route ? 'active' : ''}}" href="{{route('my.bookmarks',['type'=>$route])}}">
                    <div class="d-flex justify-content-center">
                        @include('elements.icon',['icon'=>$setting['icon'].'-outline','centered'=>'false','variant'=>'medium'])
                    </div>
                </a>
            @endforeach
        </nav>
    </div>
@endif
