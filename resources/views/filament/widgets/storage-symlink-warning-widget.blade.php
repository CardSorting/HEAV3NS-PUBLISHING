<x-filament::widget>
    @if (!$symlinkFixed)
        <div class="rounded-lg alert alert-warning border border-yellow-300 dark:border-yellow-700 p-4 d-flex w-100 justify-between v-align-center">


            <div>
                <div class="d-flex v-align-center mb-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 icon text-yellow-600 mt-1" />
                    <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                        {{ __('Warning!') }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-yellow-700 dark:text-yellow-400 mt-1 mb-0">
                        {{ __('The public/storage symlink is missing or points to the wrong folder. This may cause images or files to be inaccessible.') }}
                    </p>
                </div>
            </div>

            <div class="d-flex v-align-center">
                <x-filament::button
                    wire:click="createSymlink"
                    color="warning"
                    size="sm"
                >
                    {{ __('Fix it') }}
                </x-filament::button>
            </div>

        </div>
    @endif
</x-filament::widget>
