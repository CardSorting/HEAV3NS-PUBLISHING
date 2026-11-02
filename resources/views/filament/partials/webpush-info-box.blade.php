<div
    x-data="{ open: true }"
    x-show="open"
    class="alert-info alert relative flex justify-between items-start gap-4"
>
    <div class="flex gap-3 pr-4">
        <div>
            <div class="d-flex v-align-center mb-1">
                <x-heroicon-o-information-circle class="w-5 h-5 icon text-yellow-600 mt-1" />
                <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                    Web Push is not fully configured.
                </p>
            </div>

            <p class="text-sm mb-1">
                Use the <strong>Generate VAPID keys</strong> button below to create a valid key pair for browser push notifications.
            </p>

            <p class="text-sm mb-1">
                If generation does not work on this server, please check the
                <a href="https://docs.qdev.tech/justfans/documentation.html#vapid-keys" target="_blank" class="text-primary">
                    documentation
                </a>.
            </p>

            <p class="text-sm mb-0">
                <strong>Important:</strong> changing the VAPID keys later will invalidate current push subscribers, and users may need to subscribe again.
            </p>
        </div>
    </div>

    <button
        type="button"
        @click="open = false"
        class="text-blue-500 hover:text-blue-700 dark:text-blue-300 text-lg leading-none"
        aria-label="Dismiss"
    >
        &times;
    </button>
</div>
