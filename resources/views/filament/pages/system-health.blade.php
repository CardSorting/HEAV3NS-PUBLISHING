<x-filament-panels::page class="system-health-page" x-data="{
    copied: false,
    copyFailed: false,
    async copy() {
        this.copied = false; this.copyFailed = false;
        try {
            if (navigator.clipboard &amp;&amp; window.isSecureContext) {
                await navigator.clipboard.writeText(this.$refs.report.value);
                this.copied = true;
            } else {
                this.$refs.report.closest('details').open = true;
                this.$refs.report.focus(); this.$refs.report.select();
                this.copied = document.execCommand('copy');
                this.copyFailed = !this.copied;
            }
        } catch (error) {
            this.copyFailed = true; this.$refs.report.closest('details').open = true;
                this.$refs.report.focus(); this.$refs.report.select();
        }
    }
}">
    @php
        $statusLabels = ['ok' => 'Healthy', 'warning' => 'Warning', 'error' => 'Error', 'unknown' => 'Unverified'];
        $statusColors = ['ok' => 'success', 'warning' => 'warning', 'error' => 'danger', 'unknown' => 'gray'];
        $counts = array_count_values(array_column($snapshot['checks'], 'status'));
        $displayNames = [
            'mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite', 'sqlsrv' => 'SQL Server',
            's3' => 'S3', 'ffmpeg' => 'FFmpeg', 'coconut' => 'Coconut', 'redis' => 'Redis',
            'file' => 'File', 'public' => 'Public disk', 'local' => 'Local',
            'sync' => 'Inline execution', 'deferred' => 'Deferred execution',
            'background' => 'Background execution', 'worker' => 'Queue worker',
        ];
        $displayName = static fn ($value) => __($displayNames[$value ?? ''] ?? ($value ?? 'Unknown'));
    @endphp

    <section class="system-health-overview system-health-overview-{{ $snapshot['overall'] }}" aria-label="{{ __('System overview') }}">
        <div class="system-health-summary">
            @if ($snapshot['overall'] === 'ok')
                <x-heroicon-o-check-circle class="system-health-overview-icon" />
            @elseif ($snapshot['overall'] === 'error')
                <x-heroicon-o-x-circle class="system-health-overview-icon" />
            @else
                <x-heroicon-o-exclamation-triangle class="system-health-overview-icon" />
            @endif
            <div>
                <h2>{{ __(['ok' => 'Checks passed', 'warning' => 'Needs attention', 'error' => 'Action required', 'unknown' => 'Some checks unverified'][$snapshot['overall']]) }}</h2>
                <p class="system-health-muted">{{ trans_choice(':count error|:count errors', $counts['error'] ?? 0) }} · {{ trans_choice(':count warning|:count warnings', $counts['warning'] ?? 0) }} · {{ __(':count unverified', ['count' => $counts['unknown'] ?? 0]) }}</p>
            </div>
        </div>
        <div class="system-health-actions">
            <span class="system-health-muted">{{ __('Checked') }} {{ \Carbon\Carbon::parse($snapshot['checked_at'])->format('H:i T') }}</span>
        </div>
    </section>

    <section class="system-health-overview" aria-label="{{ __('Maintenance') }}">
        <div class="system-health-service-title">
            <h3>{{ __('Maintenance') }}</h3>
            <p class="system-health-muted">
                @if (!$maintenance['active'])
                    {{ __('The public site is available.') }}
                @elseif ($maintenance['manual'])
                    {{ __('The public site is closed. Admin access and payment notifications remain available; scheduled tasks and queued workers are paused.') }}
                @else
                    {{ __('An update or server maintenance is in progress. Complete it before changing maintenance here.') }}
                @endif
            </p>
        </div>
        <x-filament::badge :color="$maintenance['active'] ? 'warning' : 'gray'">
            {{ $maintenance['active'] ? __('Enabled') : __('Disabled') }}
        </x-filament::badge>
    </section>

    <x-filament::section compact>
        <x-slot name="heading">{{ __('Services') }}</x-slot>
        <div class="system-health-grid">
            @foreach ($snapshot['checks'] as $name => $check)
                @php
                    $details = $check['details'];
                    $summary = match ($name) {
                        'application' => ucfirst($details['environment'] ?? 'unknown').' · PHP '.($details['php_version'] ?? 'unknown'),
                        'database', 'cache', 'storage' => $displayName($details['driver'] ?? null),
                        'queue' => $displayName($details['execution_mode'] ?? null).' · '.trans_choice(':count failed job|:count failed jobs', $details['failed_jobs_count'] ?? 0),
                        'scheduler' => !empty($details['last_run_at']) ? \Carbon\Carbon::parse($details['last_run_at'])->diffForHumans() : __('No heartbeat observed'),
                        'media' => $displayName($details['transcoding_driver'] ?? null),
                    };
                    if (in_array($check['status'], ['warning', 'error'], true)) {
                        $summary = match ($name) {
                            'application' => $check['status'] === 'warning' && !empty($details['debug']) ? __('Debug enabled in production') : __('Application requirements check failed'),
                            'database' => !empty($details['pending_migrations']) ? __('Database migrations pending') : __('Database check failed'),
                            'cache' => __('Cache write/read check failed'),
                            'storage' => __($check['message']),
                            'queue' => !empty($details['failed_jobs_count']) ? trans_choice(':count failed job|:count failed jobs', $details['failed_jobs_count']) : __($check['message']),
                            'scheduler' => __('No recent scheduler heartbeat'),
                            'media' => __('Media tools or process execution unavailable'),
                        };
                    }
                @endphp
                <details class="system-health-service">
                    <summary class="system-health-service-summary">
                        <div class="system-health-service-title">
                            <h3>{{ __(ucfirst($name)) }}</h3>
                            <p class="system-health-muted">{{ $summary }}</p>
                        </div>
                        <x-filament::badge :color="$statusColors[$check['status']]">{{ __($statusLabels[$check['status']]) }}</x-filament::badge>
                        <x-heroicon-o-chevron-down class="system-health-chevron" />
                    </summary>
                    <div class="system-health-service-body">
                        <p class="system-health-muted">{{ __($check['message']) }}</p>
                        <dl class="system-health-details">
                            @foreach ($details as $key => $value)
                                <div class="system-health-detail">
                                    <dt>{{ __(ucfirst(str_replace('_', ' ', $key))) }}</dt>
                                    <dd>{{ is_bool($value) ? ($value ? __('Yes') : __('No')) : (is_array($value) ? (implode(', ', $value) ?: __('None')) : (is_string($value) ? $displayName($value) : ($value ?? __('Unknown')))) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        @if (!empty($commands[$name]))
                            <div class="system-health-settings-link">
                                @include('filament.partials.copyable-command', [
                                    'id' => 'health-command-'.$name,
                                    'label' => $name === 'scheduler' ? __('Scheduler cron entry') : __('Worker command'),
                                    'command' => $commands[$name],
                                ])
                            </div>
                            <p class="system-health-muted system-health-settings-link">{{ __('Use the PHP executable provided by your host if it differs from php.') }}</p>
                            @if ($name === 'queue')
                                <p class="system-health-muted">{{ __('Use a process manager, or append --stop-when-empty for a cron worker on shared hosting.') }}</p>
                            @endif
                        @elseif ($name === 'queue')
                            <p class="system-health-muted system-health-settings-link">{{ __('This execution mode does not require a queue worker.') }}</p>
                        @endif
                        @if (!empty($actionUrls[$name]))
                            <div class="system-health-settings-link"><x-filament::link :href="$actionUrls[$name]">{{ __('Open settings') }}</x-filament::link></div>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    </x-filament::section>

    <section id="diagnostic-report" class="system-health-service system-health-diagnostics" aria-label="{{ __('Diagnostics') }}">
        <div class="system-health-diagnostics-header">
            <div class="system-health-service-title">
                <h3>{{ __('Diagnostics') }}</h3>
                <p class="system-health-muted">{{ __('Copy the report for support or expand it to review.') }}</p>
            </div>
            <div class="system-health-actions">
                <span x-cloak x-show="copied" role="status" class="system-health-message">{{ __('Diagnostics copied.') }}</span>
                <x-filament::button color="gray" icon="heroicon-o-clipboard-document" x-on:click="copy()">
                    {{ __('Copy diagnostics') }}
                </x-filament::button>
            </div>
        </div>
        <p x-cloak x-show="copyFailed" role="status" class="system-health-copy-error">{{ __('Clipboard access is unavailable. Select and copy the report below.') }}</p>
        <details class="system-health-report-preview">
            <summary class="system-health-service-summary">
                <span class="system-health-service-title system-health-muted">{{ __('View report') }}</span>
                <x-heroicon-o-chevron-down class="system-health-chevron" />
            </summary>
            <div class="system-health-service-body">
                <p class="system-health-muted">{{ __('Selected settings and check results. Passwords, API keys, license codes and user data are excluded.') }}</p>
                <p class="system-health-muted">{{ __('Email delivery and payment processing are not tested.') }}</p>
                <textarea x-ref="report" readonly aria-label="{{ __('Diagnostic report') }}" rows="10" class="system-health-report">{{ $snapshot['diagnostics'] }}</textarea>
            </div>
        </details>
    </section>
</x-filament-panels::page>
