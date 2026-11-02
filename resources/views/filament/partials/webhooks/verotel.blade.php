<div
    x-data="{ open: true }"
    x-show="open"
    class="alert-info alert relative flex justify-between items-start gap-4"
>
    <div class="flex gap-3 pr-4">

        <div class="d-flex v-align-center mb-1">
            <x-heroicon-o-information-circle class="w-5 h-5 icon text-yellow-600 mt-1" />
            <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                In order to use Verotel as payment provider you'll need the following URLs
            </p>
        </div>

        <div class="space-y-2">

            <ul class="list-disc list-inside text-sm">
                <li>
                    Flexpay postback URL: <code>{{ route('verotel.payment.update') }}</code>
                </li>
                <li>
                    When using Verotel as a payment provider, please be aware of the following transaction amount limits:
                    <ul class="list-none pl-5 mt-1">
                        <li><strong>Verotel:</strong> $2.95 – $125 USD per transaction</li>
                        <li><strong>CardBilling:</strong> $2.95 – $500 USD per transaction</li>
                    </ul>
                </li>
                <li>
                    Transactions <strong>outside these ranges</strong> will be rejected.
                </li>
            </ul>

            <p class="text-sm mb-0">
                Learn more in the
                <a href="https://docs.qdev.tech/justfans/documentation.html#verotel"
                   class="underline text-inherit hover:opacity-80"
                   target="_blank"
                >Verotel integration guide</a>.
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
