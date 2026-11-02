<footer class="d-none d-md-block py-3">
    <div class="container">
        <div class="copyRightInfo d-flex flex-column-reverse flex-md-row d-md-flex justify-content-md-between">
            <div class="d-flex flex-column flex-md-row">
                <div class="d-flex align-items-center justify-content-center mt-3 mt-md-0 me-2">
                    <p class="mb-0 text-dark-r ">{{ GenericHelper::getFooterCopyright(true) }}</p> <span class="text-muted ms-2">|</span>
                </div>
                <a href="{{route('contact')}}" class="text-dark-r me-2 mt-0 mt-md-2 mb-2 ms-2 ms-md-0">
                    {{__('Contact page')}}
                </a>
                @foreach(GenericHelper::getFooterPublicPages() as $page)
                    @include('elements.public-page-link', ['page' => $page, 'classes' => 'text-dark-r m-2', 'short' => true])
                @endforeach
            </div>

            <div class="d-flex justify-content-md-center align-items-center mt-4 mt-md-0 footer-social-links">
                <div class="d-flex align-items-center justify-content-center gap-3">
                    @include('elements.footer.dark-mode-switcher')
                    @include('elements.footer.language-switcher')
                </div>
            </div>
        </div>
    </div>
</footer>
