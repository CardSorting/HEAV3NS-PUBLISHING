<?php

namespace App\Services\Moderation;

use App\Services\Queue\QueueRuntimeService;
use App\Settings\ModerationSettings;

class ModerationConfiguration
{
    public const DEFAULT_VIDEO_INTERVAL_SECONDS = 2.0;

    public function __construct(
        protected ModerationSettings $settings,
        protected ModerationSignatureService $signatures,
    ) {
    }

    public function enabled(): bool
    {
        return $this->settings->enabled
            && filled($this->settings->sightengine_api_user)
            && filled($this->settings->sightengine_api_secret);
    }

    public function provider(): string
    {
        return $this->settings->provider ?: 'sightengine';
    }

    public function textEnabled(): bool
    {
        return $this->enabled() && $this->settings->text_enabled;
    }

    public function models(): array
    {
        return [$this->settings->sightengine_model ?: 'nudity-2.1'];
    }

    public function policy(): array
    {
        $reviewThreshold = max(0, min(1, $this->settings->review_threshold));

        return [
            'flag_classes' => array_values(array_unique(array_filter(
                array_map('strval', $this->settings->flag_classes),
            ))),
            'review_threshold' => $reviewThreshold,
            'violation_threshold' => max($reviewThreshold, min(1, $this->settings->violation_threshold)),
            'video_enabled' => $this->settings->video_enabled,
            'video_processing_mode' => $this->videoProcessingMode(),
            'video_sampling_mode' => $this->customVideoInterval() === null ? 'provider_default' : 'custom',
            'video_interval_seconds' => $this->customVideoInterval(),
            'video_effective_interval_seconds' => $this->videoInterval(),
            'max_video_duration_seconds' => $this->maxVideoDuration(),
            'max_concurrent_video_jobs' => $this->maxConcurrentVideoJobs(),
        ];
    }

    public function textModels(): array
    {
        $models = array_values(array_unique(array_intersect(
            ['general', 'self-harm'],
            array_map('strval', $this->settings->text_models),
        )));

        return $models ?: ['general'];
    }

    public function textPolicy(): array
    {
        $reviewThreshold = max(0, min(1, $this->settings->text_review_threshold));
        $languages = array_values(array_unique(array_intersect(
            ['en', 'fr', 'it', 'pt', 'es', 'ru', 'tr'],
            array_map('strval', $this->settings->text_languages),
        )));
        $flagClasses = array_values(array_unique(array_intersect(
            ['sexual', 'discriminatory', 'insulting', 'violent', 'toxic', 'self-harm'],
            array_map('strval', $this->settings->text_flag_classes),
        )));

        return [
            'mode' => 'ml',
            'languages' => $languages ?: ['en'],
            'flag_classes' => $flagClasses ?: ['sexual', 'discriminatory', 'insulting', 'violent', 'toxic'],
            'review_threshold' => $reviewThreshold,
            'violation_threshold' => max(
                $reviewThreshold,
                min(1, $this->settings->text_violation_threshold),
            ),
        ];
    }

    public function modelSignature(?string $mediaType = null): string
    {
        return $this->signatures->model(
            $this->provider(),
            $this->models(),
            $this->videoInterval(),
            $mediaType,
        );
    }

    public function mediaModelSignatures(): array
    {
        return [
            'image' => $this->modelSignature('image'),
            'video' => $this->modelSignature('video'),
        ];
    }

    public function textModelSignature(): string
    {
        return $this->signatures->textModel(
            $this->provider(),
            $this->textModels(),
            $this->textPolicy()['languages'],
        );
    }

    public function textPolicySignature(): string
    {
        return $this->signatures->policy($this->textPolicy());
    }

    public function policySignature(): string
    {
        return $this->signatures->policy($this->policy());
    }

    public function videoInterval(): float
    {
        return $this->customVideoInterval() ?? self::DEFAULT_VIDEO_INTERVAL_SECONDS;
    }

    public function customVideoInterval(): ?float
    {
        $allowed = [0.5, 1.0, 2.0, 3.0, 4.0, 5.0];
        $configured = (float) $this->settings->video_interval_seconds;

        return in_array($configured, $allowed, true) ? $configured : null;
    }

    public function videoProcessingMode(): string
    {
        return in_array($this->settings->video_processing_mode, [
            ModerationSettings::VIDEO_PROCESSING_AUTO,
            ModerationSettings::VIDEO_PROCESSING_SYNC,
            ModerationSettings::VIDEO_PROCESSING_ASYNC,
        ], true) ? $this->settings->video_processing_mode : ModerationSettings::VIDEO_PROCESSING_AUTO;
    }

    public function maxVideoDuration(): int
    {
        $maximum = $this->videoProcessingMode() === ModerationSettings::VIDEO_PROCESSING_SYNC ? 59 : 3600;

        return max(1, min($maximum, $this->settings->max_video_duration_seconds));
    }

    public function requestTimeout(): int
    {
        return max(15, min(210, $this->settings->request_timeout_seconds));
    }

    public function maxConcurrentVideoJobs(): int
    {
        return max(1, min(50, $this->settings->max_concurrent_video_jobs));
    }

    public function itemLimitForMode(string $mode): int
    {
        return max(1, match (QueueRuntimeService::normalizeMode($mode)) {
            QueueRuntimeService::MODE_DEFERRED => $this->settings->deferred_item_limit,
            QueueRuntimeService::MODE_BACKGROUND => $this->settings->background_item_limit,
            QueueRuntimeService::MODE_WORKER => $this->settings->worker_item_limit,
            default => $this->settings->inline_item_limit,
        });
    }

    public function dailyOperationLimit(): int
    {
        return max(1, $this->settings->daily_operation_limit);
    }

    public function monthlyOperationLimit(): int
    {
        return max($this->dailyOperationLimit(), $this->settings->monthly_operation_limit);
    }

    public function staleAfterMinutes(): int
    {
        return max(5, $this->settings->stale_after_minutes);
    }

    public function asyncProviderTimeoutMinutes(): int
    {
        return max(30, min(10080, $this->settings->async_provider_timeout_minutes));
    }

    public function asyncReconciliationEnabled(): bool
    {
        return $this->enabled()
            && $this->settings->video_enabled
            && $this->videoProcessingMode() !== ModerationSettings::VIDEO_PROCESSING_SYNC;
    }
}
