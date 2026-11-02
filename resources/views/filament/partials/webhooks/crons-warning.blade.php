<div
    x-data="{ open: true }"
    x-show="open"
    class="alert-info alert relative flex justify-between items-start gap-4"
>
    <div class="flex gap-3 pr-4">

        <div class="d-flex v-align-center mb-1">
            <x-heroicon-o-information-circle class="w-5 h-5 icon text-yellow-600 mt-1" />
            <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                {{ __('The payment system requires cronjobs to run correctly.') }}
            </p>
        </div>

        <div class="space-y-2">

            <p class="text-sm">
                Set this up using the following command:
            </p>

            <ul class="list-disc list-inside text-sm">
                <li>
                    <code>* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</code>
                </li>
            </ul>

            <p class="text-sm mb-0">
                See the
                <a href="https://docs.qdev.tech/justfans/documentation.html#crons"
                   class="underline text-inherit hover:opacity-80"
                   target="_blank"
                >cron setup guide</a>.
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
