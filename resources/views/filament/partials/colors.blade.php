<div
    x-data="{ open: true }"
    x-show="open"
    class="alert-info alert relative flex justify-between items-start gap-4"
>
    <div class="flex gap-3 pr-4">
        <div class="d-flex v-align-center mb-1">
            <x-heroicon-o-information-circle class="w-5 h-5 icon text-yellow-600 mt-1" />
            <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                Few general notes about generating themes:
            </p>
        </div>

        <div class="space-y-2">

            <ul class="list-disc list-inside text-sm space-y-1 mb-0">
                <li>The themes are generated on a remote server. Timings may vary but it might take between 20–40s for a run.</li>
                <li>Regular license holders can generate 5 themes per day.</li>
                <li>If the PHP <code>zip</code> extension is available, generated themes are validated and applied automatically.</li>
                <li>If the extension is unavailable, enable it before applying the theme. You can then install the downloaded archive with <code>php artisan theme:install /path/to/theme.zip</code>. Uploading the ZIP into <code>public/css/theme</code> alone will not apply it.</li>
                <li>Before updating your site, back up <code>public/css/theme/theme.css</code> if it exists and restore it afterward. When upgrading from Bootstrap 4 to Bootstrap 5, regenerate your theme instead of restoring the old <code>bootstrap*.css</code> theme files.</li>
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
