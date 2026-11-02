<div class="settings-nav">
    <div class="card-settings border-bottom">
        <div class="list-group list-group-sm list-group-flush">
            @foreach($menuItems as $key => $item)
                <a href="{{ route($menuRoute, ['type' => $key]) }}" class="list-group-item list-group-item-action d-flex justify-content-between {{ $menuActive == $key ? 'active' : '' }}" @if($menuActive == $key) aria-current="page" @endif>
                    <div class="d-flex align-items-center">
                        @include('elements.icon', ['icon' => $item['icon'].'-outline', 'centered' => 'false', 'classes' => 'me-3', 'variant' => 'medium'])
                        <span>{{ $item['label'] ?? __(ucfirst($key)) }}</span>
                    </div>
                    <div class="d-flex align-items-center">
                        @include('elements.icon', ['icon' => 'chevron-forward-outline'])
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>
