<div
    x-data="{ open: true }"
    x-show="open"
    class="alert-info alert relative flex justify-between items-start gap-4"
>
    <div class="flex gap-3 pr-4">

        <div class="d-flex v-align-center mb-1">
            <x-heroicon-o-information-circle class="w-5 h-5 icon text-yellow-600 mt-1" />
            <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                Each provider requires a <em>Callback URL</em>.
            </p>
        </div>


        <div class="space-y-2">
            <p class="text-sm">
                Use the following values when configuring your social login apps:
            </p>

            <ul class="list-disc list-inside text-sm mb-0">
                <li><code>{{ route('social.login.callback', ['provider' => 'facebook']) }}</code></li>
                <li><code>{{ route('social.login.callback', ['provider' => 'twitter']) }}</code></li>
                <li><code>{{ route('social.login.callback', ['provider' => 'google']) }}</code></li>
            </ul>
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
