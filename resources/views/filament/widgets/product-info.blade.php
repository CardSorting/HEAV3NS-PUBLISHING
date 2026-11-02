<x-filament::widget>
    <x-filament::card class="qs-widget">
        <h3 class="qs-heading">{{ __('admin.widgets.product_info.title') }}</h3>

        <div class="qs-grid">
            {{-- Website --}}
            <div class="qs-card">
                <a href="https://codecanyon.net/item/justfans-premium-content-creators-saas-platform/35154898"
                   target="_blank" class="qs-link">
                    <div class="qs-iconbox">
                        <x-heroicon-o-globe-alt />
                    </div>
                    <div>
                        <p class="qs-title">{{ __('admin.widgets.product_info.website.title') }}</p>
                        <p class="qs-desc">{{ __('admin.widgets.product_info.website.description') }}</p>
                    </div>
                </a>
            </div>

            {{-- Documentation --}}
            <div class="qs-card">
                <a href="https://docs.qdev.tech/justfans/" target="_blank" class="qs-link">
                    <div class="qs-iconbox">
                        <x-heroicon-o-book-open />
                    </div>
                    <div>
                        <p class="qs-title">{{ __('admin.widgets.product_info.documentation.title') }}</p>
                        <p class="qs-desc">{{ __('admin.widgets.product_info.documentation.description') }}</p>
                    </div>
                </a>
            </div>

            {{-- Changelog --}}
            <div class="qs-card">
                <a href="https://changelogs.qdev.tech/products/fans" target="_blank" class="qs-link">
                    <div class="qs-iconbox">
                        <x-heroicon-o-document-text />
                    </div>
                    <div>
                        <p class="qs-title">{{ __('admin.widgets.product_info.changelog.title') }}</p>
                        <p class="qs-desc">{{ __('admin.widgets.product_info.changelog.description') }}</p>
                    </div>
                </a>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>
