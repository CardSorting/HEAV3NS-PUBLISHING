@if($paginator->hasPages())
    @php
        $currentPage = (int) $paginator->currentPage();
        $lastPage = $simple ? null : (int) $paginator->lastPage();
        $pageLabel = $simple
            ? __('Page').' '.$currentPage
            : __('Page :current of :total', ['current' => $currentPage, 'total' => $lastPage]);
        $pages = [];
        if (!$simple) {
            // The same bounded window is used by the asynchronous Vault paginator.
            $start = $lastPage <= 5 ? 1 : max(1, min($currentPage - 1, $lastPage - 2));
            $end = $lastPage <= 5 ? $lastPage : min($lastPage, max($currentPage + 1, 3));
            $pages = array_unique(array_merge([1, $lastPage], range($start, $end)));
            sort($pages);
        }
    @endphp
    <nav class="app-pagination-wrapper justify-content-center justify-content-md-end" aria-label="{{ __('Pagination') }}">
        <div class="app-pagination {{ $simple ? '' : 'app-pagination--responsive' }}">
            @include('elements.pagination.arrow-link', ['url' => $paginator->previousPageUrl(), 'previous' => true])

            @unless($simple)
                <div class="app-pagination-pages">
                    @foreach($pages as $index => $page)
                        @if($index > 0 && $page - $pages[$index - 1] > 1)
                            <span class="app-pagination-ellipsis" aria-hidden="true">…</span>
                        @endif
                        @if($page === $currentPage)
                            <span class="app-pagination-control" aria-current="page" aria-label="{{ __('Page').' '.$page }}">{{ $page }}</span>
                        @else
                            <a class="app-pagination-control" href="{{ $paginator->url($page) }}" aria-label="{{ __('Page').' '.$page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                </div>
            @endunless

            <span class="app-pagination-status" @if($simple) aria-current="page" @endif>
                <span class="app-pagination-status-full">{{ $pageLabel }}</span>
                <span class="app-pagination-status-short" aria-label="{{ $pageLabel }}">{{ $simple ? $currentPage : $currentPage.' / '.$lastPage }}</span>
            </span>

            @include('elements.pagination.arrow-link', ['url' => $paginator->nextPageUrl(), 'previous' => false])
        </div>
    </nav>
@endif
