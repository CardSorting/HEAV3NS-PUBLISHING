<x-filament::widget>
    <div class="alert-info alert admin-moderation-notice">
        <div class="admin-moderation-notice__message">
            <x-heroicon-o-information-circle class="icon" />

            <div class="admin-moderation-notice__copy">
                <p class="font-semibold mb-1">{{ $title }}</p>
                <p class="mb-0">{{ $message }}</p>
            </div>
        </div>

        @if ($settingsUrl)
            <div class="admin-moderation-notice__action">
                <x-filament::button
                    tag="a"
                    href="{{ $settingsUrl }}"
                    color="gray"
                    size="sm"
                >
                    {{ __('Open settings') }}
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament::widget>
