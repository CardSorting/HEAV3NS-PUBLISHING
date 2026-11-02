<a href="{{ $page->getUrl() }}"
   @if($page->open_in_new_tab) target="_blank" @endif
   rel="{{ $page->isExternal() ? 'nofollow ' : '' }}{{ $page->open_in_new_tab ? 'noopener noreferrer' : '' }}"
   class="{{ $classes ?? '' }}">{{ ($short ?? false) ? ($page->translated('short_title') ?: $page->translated('title')) : $page->translated('title') }}</a>
