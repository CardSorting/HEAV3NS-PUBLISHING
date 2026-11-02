@php
    $licensePath = storage_path('app/installed');
    $license = null;

    if (file_exists($licensePath)) {
        $contents = file_get_contents($licensePath);
        $json = json_decode($contents, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($json['data']) && is_array($json['data'])) {
            $license = $json['data'];
            $license['code'] = $json['code'] ?? null;
        }
    }
    if (in_array($license['source'] ?? null, ['qdev', 'lemon'], true) && ($license['item'] ?? null) === 'justfans') {
        $license['item'] = 'JustFans - Premium Content Creators SaaS platform';
    }
    $support = \App\Providers\InstallerServiceProvider::getLicenseSupportStatus($license ?? []);
@endphp

@if ($license)
    <div class="alert-info alert relative flex justify-between items-start gap-4 mb-4">
        <div class="flex gap-3 pr-4">
            <div class="d-flex v-align-center mb-1">
                <x-heroicon-o-information-circle class="w-5 h-5 icon text-yellow-600 mt-1" />
                <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                    License Details
                </p>
            </div>
            <div class="space-y-2">

                <ul class="list-disc list-inside text-sm space-y-1 mb-0">
                    <li><strong>Item:</strong> {{ $license['item'] ?? '—' }}</li>
                    <li><strong>License:</strong> {{ $license['license'] ?? '—' }}</li>
                    @if (($license['source'] ?? 'envato') === 'envato' && filled($license['buyer'] ?? null))
                        <li><strong>Username:</strong> {{ $license['buyer'] }}</li>
                    @endif
                    <li>
                        <strong>Support:</strong>
                        @if ($support['active'] === true)
                            {{ $support['until'] ? __('Active until :date', ['date' => $support['until']->toFormattedDateString()]) : __('Active') }}
                        @elseif ($support['active'] === false)
                            <span class="text-red-600 dark:text-red-400 font-semibold">{{ __('Expired') }}</span>
                            —
                            <a href="https://codecanyon.net/downloads" target="_blank" class="underline text-inherit hover:opacity-80">
                                Renew support
                            </a>
                        @else
                            {{ __('Support status unavailable') }}
                        @endif
                    </li>
                    <li><strong>License Code:</strong> <code>{{ $license['code'] ?? '—' }}</code></li>
                </ul>
            </div>
        </div>
    </div>
@else
    <div class="alert-warning alert relative flex justify-between items-start gap-4 mb-4">
        <div class="flex gap-3 pr-4">

            <div class="d-flex v-align-center mb-1">
                <x-heroicon-o-exclamation-triangle class="icon mt-1.5" />
                <p class="font-semibold text-yellow-800 dark:text-yellow-300 ml-1 mb-0">
                    No valid license found.
                </p>
            </div>
            <div class="space-y-1">
                    Please enter your product license key to activate your installation. You can find it in your
                    <a href="https://codecanyon.net/downloads" class="underline text-inherit hover:opacity-80" target="_blank">Codecanyon Downloads</a>.
                </p>
            </div>
        </div>
    </div>
@endif
