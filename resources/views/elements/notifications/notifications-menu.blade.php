<nav class="nav nav-pills nav-justified notifications-nav pe-3 pe-md-0">
    <a class="nav-item nav-link fw-bold {{!$activeType ? 'active' : ''}}" href="{{route('my.notifications')}}">
        <div class="d-flex justify-content-center">
            @include('elements.icon',['icon'=>'list-outline','centered'=>false,'variant'=>'medium'])
            <span class="d-none d-md-block ms-2">{{__('All')}}</span>
        </div>
    </a>
    @foreach($notificationTypes as $type)
        <a class="nav-item nav-link fw-bold {{$activeType == $type ? 'active' : ''}}" href="{{route('my.notifications',['type' => $type])}}">
            <div class="d-flex justify-content-center">
                <span>
                @switch($type)
                    @case('messages')
                    @include('elements.icon',['icon'=>'chatbubbles-outline','centered' => false,'variant'=>'medium'])
                    @break
                    @case('likes')
                    @include('elements.icon',['icon'=>'heart-outline','centered' => false,'variant'=>'medium'])
                    @break
                    @case('subscriptions')
                    @include('elements.icon',['icon'=>'people-circle-outline','centered' => false,'variant'=>'medium'])
                    @break
                    @case('tips')
                    @include('elements.icon',['icon'=>'gift-outline','centered' => false,'variant'=>'medium'])
                    @break
                    @case('promos')
                    @include('elements.icon',['icon'=>'sparkles-outline','centered' => false,'variant'=>'medium'])
                    @break
                    @case('PPV')
                    @include('elements.icon',['icon'=>'lock-open-outline','centered' => false,'variant'=>'medium'])
                    @break
                @endswitch
                </span>

                <span class="d-none d-md-block ms-2">{{__(ucfirst($type))}}</span>
            </div>
        </a>
    @endforeach
</nav>
