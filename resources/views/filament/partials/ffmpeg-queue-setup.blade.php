@php
    $modeLead = match ($snapshot['mode']) {
        \App\Services\Queue\QueueRuntimeService::MODE_DEFERRED => 'Deferred processing',
        \App\Services\Queue\QueueRuntimeService::MODE_BACKGROUND => 'Spawned processing',
        \App\Services\Queue\QueueRuntimeService::MODE_WORKER => 'Queue jobs',
        default => 'Inline processing',
    };
@endphp

<div class="queue-runtime-card video-queue-runtime-card">
    <div class="queue-runtime-header">
        <div class="queue-runtime-heading">
            <h3 class="queue-runtime-title">{{ $modeLead }}</h3>
            <p class="queue-runtime-subtitle">Uses the saved Runtime queue setup: {{ $snapshot['mode_label'] }}</p>
        </div>
        <a class="video-queue-runtime-link" href="{{ $runtimeSettingsUrl }}">Open queue settings</a>
    </div>

    @if ($snapshot['uses_worker'] && $snapshot['connection'] === 'sqs')
        <div class="queue-mode-note is-warning queue-runtime-storage-note">
            Set the SQS visibility timeout above {{ number_format(\App\Services\Media\AttachmentTranscodingService::QUEUE_RETRY_AFTER_SECONDS) }} seconds so long video jobs are not delivered twice.
        </div>
    @endif

    @if (! $websocketsEnabled && $snapshot['mode'] !== \App\Services\Queue\QueueRuntimeService::MODE_SYNC)
        <div class="queue-mode-note is-warning queue-runtime-storage-note">
            Processing will continue, but users will only see the finished state after reopening a saved draft unless Pusher or Soketi is configured.
        </div>
    @endif
</div>
