<div class="card mt-3">
    <div class="card-body">
        <h5 class="card-title text-uppercase fs-point-85 fw-bold mb-3">{{__('Popular hashtags')}}</h5>

        @if($popularHashtags = \App\Providers\PostsHelperServiceProvider::getTopHashtags(10))
            <div class="filter-pills mt-2">
                @foreach($popularHashtags as $row)
                    <a href="{{ route('search.get', ['filter' => 'top', 'query' => '#'.$row['tag']]) }}"
                       class="filter-pill">
                        <span>#{{ $row['tag'] }}</span>
                    </a>
                @endforeach
            </div>
        @else
            <div class="text-muted small mt-2">{{__('No hashtags yet.')}}</div>
        @endif
    </div>
</div>
