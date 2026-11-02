<div class="{{getSetting('site.ads_sidebar_spot') ? 'mt-3' : 'mt-1'}} pt-3 text-center {{getSetting('site.ads_sidebar_spot') ? 'border-top' : ''}} widgets-footer">
    @foreach(GenericHelper::getFooterPublicPages() as $page)
        @include('elements.public-page-link', ['page' => $page, 'classes' => 'widgets-footer-link text-muted text-dark-r m-2', 'short' => true])
    @endforeach
</div>
