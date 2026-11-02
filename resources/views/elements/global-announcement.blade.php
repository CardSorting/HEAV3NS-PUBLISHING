@php
    $announcement = GenericHelper::getLatestGlobalMessage();
    if($announcement){
        $isSticky = $announcement->is_sticky;
        if(in_array(Route::currentRouteName(),['feed']) && \App\Providers\GenericHelperServiceProvider::isMobileDevice()){
            $isSticky = true;
        }
    }
@endphp

@if( $announcement &&
    ($announcement->is_global || (!$announcement->is_global && in_array(Route::currentRouteName(),['home']))) &&
    !request()->cookie('dismissed_banner_' . $announcement->id) &&
    (!$announcement->expiring_at || ($announcement->expiring_at && $announcement->expiring_at > \Carbon\Carbon::now()))
)
    <div class="{{ $isSticky ? 'sticky-info-bar' : '' }}
            alert alert-dismissible fade show mb-0 border-0 global-announcement-banner
            bg-gradient-faded-primary text-dark"
         role="alert"
         data-banner-id="{{ $announcement->id }}">

        @if($announcement->is_dismissible)
            <button type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="alert"
                    aria-label="Close"
                    onclick="dimissGlobalAnnouncement('{{ $announcement->id }}')"></button>
        @endif

        <!-- NEW WRAPPER -->
        <div class="announcement-body text-center">
            <div class="content {{ $announcement->size === 'small' ? '' : 'py-1' }}">
                {!! $announcement->content !!}
            </div>
        </div>

    </div>

@endif

@if($announcement && $isSticky)
    <script>
        (function () {
            var root = document.documentElement;
            root.classList.add('ga-pending');

            function update() {
                var banner = document.querySelector('.global-announcement-banner.sticky-info-bar');
                if (banner && !banner.classList.contains('show')) {
                    banner = null;
                }

                var h = banner ? Math.ceil(banner.getBoundingClientRect().height) : 0;
                root.style.setProperty('--ga-h', h + 'px');
                root.classList.remove('ga-pending');
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', update, { once: true });
            } else {
                update();
            }

            window.addEventListener('resize', update);

            document.addEventListener('click', function (e) {
                if (e.target.closest('[data-bs-dismiss="alert"]')) {
                    root.style.setProperty('--ga-h', '0px');
                }
            });

        })();
    </script>
@endif
