<div
    x-data="{ open: true }"
    x-show="open"
    class="alert-info alert relative flex justify-between items-start gap-4"
>
    <div class="flex gap-3 pr-4">
        <div class="d-flex v-align-center mb-1">
            <x-heroicon-o-information-circle class="w-5 h-5 icon text-yellow-600 mt-1" />
            <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                CoinGate integration guide.
            </p>
        </div>

        <div class="space-y-2">
            <p class="text-sm mb-0">
                Sandbox tokens should be used with sandbox mode.
            </p>
            <p class="text-sm mb-0">
                Learn more in the
                <a href="https://docs.qdev.tech/justfans/documentation.html#coingate"
                   class="underline text-inherit hover:opacity-80"
                   target="_blank"
                >CoinGate integration guide</a>.
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
