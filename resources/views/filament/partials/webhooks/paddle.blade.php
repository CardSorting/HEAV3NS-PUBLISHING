<div
    x-data="{ open: true }"
    x-show="open"
    class="alert-info alert relative flex justify-between items-start gap-4"
>
    <div class="flex gap-3 pr-4">
        <div class="d-flex v-align-center mb-1">
            <x-heroicon-o-information-circle class="w-5 h-5 icon text-yellow-600 mt-1" />
            <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                Paddle requires a <em>Webhook URL</em> and endpoint secret key.
            </p>
        </div>

        <div class="space-y-2">
            <ul class="list-disc list-inside text-sm">
                <li>
                    <code>{{ route('paddle.payment.update') }}</code>
                </li>
            </ul>

            <p class="text-sm mb-0">
                Configure this URL in your Paddle notification destination, subscribe to <code>transaction.completed</code>, and copy the endpoint secret key into the field below.
            </p>
            <p class="text-sm mb-0">
                This integration uses a Paddle-hosted checkout URL from <code>Checkout -> Hosted Checkouts</code>. Paste that full <code>https://pay.paddle.io/checkout/hsc_...</code> link into the admin setting below.
            </p>
            <p class="text-sm mb-0">
                In the Paddle dashboard, set the hosted checkout <em>return URL</em> to <code>{{ route('payment.checkPaddlePaymentStatus') }}</code> and keep the webhook above enabled for final payment confirmation.
            </p>
            <p class="text-sm mb-0">
                Learn more in the
                <a href="https://docs.qdev.tech/justfans/documentation.html#paddle"
                   class="underline text-inherit hover:opacity-80"
                   target="_blank"
                >Paddle integration guide</a>.
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
