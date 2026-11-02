<x-filament::widget>
    @if ($canMigrate)
        <div class="rounded-lg alert alert-warning border border-blue-300 dark:border-blue-700 p-4 d-flex w-100 justify-between v-align-center">

            <div>
                <div class="d-flex v-align-center mb-2">
                    <x-heroicon-o-arrow-path class="w-5 h-5 icon text-blue-600 mt-1" />
                    <p class="font-semibold text-blue-800 dark:text-blue-300 ml-1 mb-0">
                        {{ __('Update available') }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-blue-700 dark:text-blue-400 mt-1 mb-0">
                        {{ __('There are pending database migrations. Run the update to keep your installation in sync.') }}
                    </p>
                </div>
            </div>

            <div class="d-flex v-align-center">
                <x-filament::button
                    tag="a"
                    href="{{ $updateUrl }}"
                    color="warning"
                    size="sm"
                    target="_blank"
                >
                    {{ __('Run update') }}
                </x-filament::button>
            </div>

        </div>
    @endif
</x-filament::widget>
