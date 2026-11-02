{{-- Local Storage Warning --}}
@if(getSetting('storage.driver') === 'public')
    <div
        x-data="{ open: true }"
        x-show="open"
        class="alert-warning alert relative flex justify-between items-start gap-4 mb-4"
    >
        <div class="flex gap-3 pr-4">
            <div class="w-full">
                <div class="d-flex v-align-center mb-1">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 icon text-yellow-600 mt-1" />
                    <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                        Warning
                    </p>
                </div>

                <div class="space-y-2">
                    <p class="text-sm mb-0">
                        Coconut transcoding can only be used with a remote storage option. Local storage is not supported.
                    </p>
                </div>
            </div>
        </div>

        <button
            type="button"
            @click="open = false"
            class="text-yellow-600 hover:text-yellow-700 dark:text-yellow-300 text-lg leading-none"
            aria-label="Dismiss"
        >
            &times;
        </button>
    </div>

@endif

{{-- Websockets Not Configured Warning --}}
@if(!getSetting('websockets.pusher_app_id') && !getSetting('websockets.soketi_host_address'))
    <div
        x-data="{ open: true }"
        x-show="open"
        class="alert-warning alert relative flex justify-between items-start gap-4 mb-4"
    >
        <div class="flex gap-3 pr-4">
            <div class="w-full">
                <div class="d-flex v-align-center mb-1">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 icon text-yellow-600 mt-1" />
                    <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                        Warning
                    </p>
                </div>

                <div class="space-y-2">
                    <p class="text-sm mb-0">
                        Coconut transcoding requires Websockets to be enabled. Please configure the Websockets settings.
                    </p>
                </div>
            </div>
        </div>

        <button
            type="button"
            @click="open = false"
            class="text-yellow-600 hover:text-yellow-700 dark:text-yellow-300 text-lg leading-none"
            aria-label="Dismiss"
        >
            &times;
        </button>
    </div>
@endif
