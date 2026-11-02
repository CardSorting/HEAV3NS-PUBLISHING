@php
    $latestTest = $snapshot['latest_test'];
    $testState = $snapshot['test_state'];
    $workerState = $snapshot['worker_state'];
    $runtimeState = $snapshot['runtime_state'];
    $testStatus = $latestTest?->status;
    $testStatusClass = match ($testStatus) {
        'completed' => 'is-success',
        'failed' => 'is-danger',
        'processing', 'dispatched' => 'is-warning',
        default => 'is-neutral',
    };
    if ($testState['status'] === 'not_responding') {
        $testStatusClass = 'is-warning';
    }
    $workerCommand = $modeSelection['worker_command'];
    $testStatusLabel = match (true) {
        $testState['status'] === 'not_responding' => 'Check timed out',
        $testStatus === 'dispatched' => $snapshot['uses_worker'] ? 'Awaiting worker' : 'Dispatched',
        default => $testStatus ? ucfirst($testStatus) : null,
    };
@endphp

<div
    class="queue-runtime-card"
    @if ($testState['status'] === 'checking')
        @if ($snapshot['uses_worker'])
            wire:poll.5s
        @else
            wire:poll.2s
        @endif
    @elseif ($workerState['status'] === 'online')
        wire:poll.60s
    @endif
>
    <div class="queue-runtime-header">
        <div class="queue-runtime-heading">
            <h3 class="queue-runtime-title">Saved queue setup</h3>
            <p class="queue-runtime-subtitle">
                {{ $snapshot['mode_label'] }}
                @if ($snapshot['uses_worker'])
                    · {{ strtoupper($snapshot['connection']) }} · {{ $snapshot['queue_name'] }}
                @endif
            </p>
        </div>

        <span class="queue-runtime-badge {{ $runtimeState['class'] }}">{{ $runtimeState['label'] }}</span>
    </div>

    @if ($workerCommand)
        <div class="queue-runtime-worker">
            @include('filament.partials.copyable-command', [
                'id' => 'runtime-worker-command',
                'label' => __('Worker command'),
                'command' => $workerCommand,
            ])
            <p class="queue-runtime-worker-help">
                @if (! $modeSelection['is_saved'])
                    Previewed from the current form. Save the settings before starting or restarting the worker.
                @else
                    Run continuously with a process manager, or run with <code>--stop-when-empty</code> from cron on shared hosting. Restart persistent workers after changing queue settings.
                @endif
            </p>
        </div>
    @endif

    @if ($snapshot['database'])
        @if (! $snapshot['database']['table_exists'])
            <div class="queue-mode-note is-danger queue-runtime-storage-note">
                The database queue table is missing. Run the application migrations before using a database worker.
            </div>
        @else
            @if (! $snapshot['database']['failed_table_exists'])
                <div class="queue-mode-note is-warning queue-runtime-storage-note">
                    The failed-jobs table is missing, so failed-job tracking is unavailable until migrations are run.
                </div>
            @endif

            <div class="queue-runtime-stats">
                <div class="queue-runtime-stat">
                    <div class="queue-runtime-stat-value">{{ number_format($snapshot['database']['waiting']) }}</div>
                    <div class="queue-runtime-stat-label">Waiting</div>
                </div>
                <div class="queue-runtime-stat">
                    <div class="queue-runtime-stat-value">{{ number_format($snapshot['database']['delayed']) }}</div>
                    <div class="queue-runtime-stat-label">Delayed</div>
                </div>
                <div class="queue-runtime-stat">
                    <div class="queue-runtime-stat-value">{{ number_format($snapshot['database']['processing']) }}</div>
                    <div class="queue-runtime-stat-label">Processing</div>
                </div>
                <div class="queue-runtime-stat">
                    <div class="queue-runtime-stat-value">{{ $snapshot['database']['failed_table_exists'] ? number_format($snapshot['failed_jobs_count']) : '—' }}</div>
                    <div class="queue-runtime-stat-label">Failed</div>
                </div>
            </div>
        @endif
    @endif

    <div class="queue-runtime-test">
        @if ($latestTest)
            <div class="queue-runtime-test-row">
                <span class="queue-runtime-test-label">Latest test · {{ $latestTest->created_at?->diffForHumans() }}</span>
                <span class="queue-runtime-test-status {{ $testStatusClass }}">{{ $testStatusLabel }}</span>
            </div>
            @if ($latestTest->error)
                <p class="queue-runtime-test-error">{{ $latestTest->error }}</p>
            @endif
        @else
            <p class="queue-runtime-empty">No queue test has been run yet.</p>
        @endif
        <p class="queue-runtime-hint">
            @if ($testState['status'] === 'not_responding')
                The last check did not report back within
                {{ $snapshot['uses_worker'] ? \App\Services\Queue\QueueRuntimeService::WORKER_CHECK_TIMEOUT_SECONDS : \App\Services\Queue\QueueRuntimeService::TEST_CHECK_TIMEOUT_SECONDS }}
                seconds. This is a timeout, not a captured application error; run the test again after configuration or application changes.
            @elseif ($snapshot['uses_worker'])
                Worker settings are checked automatically after saving. A response is expected within {{ \App\Services\Queue\QueueRuntimeService::WORKER_CHECK_TIMEOUT_SECONDS }} seconds.
            @elseif ($snapshot['mode'] === \App\Services\Queue\QueueRuntimeService::MODE_DEFERRED)
                The test runs after the response and refreshes automatically while it is pending.
            @elseif ($snapshot['mode'] === \App\Services\Queue\QueueRuntimeService::MODE_BACKGROUND)
                The test verifies that a spawned PHP process can complete the job and report back within {{ \App\Services\Queue\QueueRuntimeService::TEST_CHECK_TIMEOUT_SECONDS }} seconds.
            @else
                Inline tests finish during the current request, so no status polling is needed.
            @endif
        </p>
    </div>
</div>
