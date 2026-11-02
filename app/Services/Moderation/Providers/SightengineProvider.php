<?php

namespace App\Services\Moderation\Providers;

use App\Services\Moderation\Contracts\MediaModerationProvider;
use App\Services\Moderation\Data\ModerationInput;
use App\Services\Moderation\Data\ModerationPendingResult;
use App\Services\Moderation\Data\ModerationResult;
use App\Services\Moderation\Exceptions\ModerationProviderException;
use App\Services\Moderation\ModerationConfiguration;
use App\Services\Moderation\SightengineResponseNormalizer;
use App\Settings\ModerationSettings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SightengineProvider implements MediaModerationProvider
{
    public const PROCESSING_SYNC = 'sync';
    public const PROCESSING_ASYNC = 'async';

    public function __construct(
        protected ModerationSettings $settings,
        protected ModerationConfiguration $configuration,
        protected SightengineResponseNormalizer $normalizer,
    ) {
    }

    public function key(): string
    {
        return 'sightengine';
    }

    public function supports(string $mediaType): bool
    {
        return $mediaType === 'image'
            || ($mediaType === 'video' && $this->settings->video_enabled);
    }

    public function estimateOperations(ModerationInput $input, array $models, array $policy): int
    {
        $operationsPerSample = max(1, count($models));

        if ($input->mediaType === 'image') {
            return $operationsPerSample;
        }

        $interval = $this->customVideoInterval($policy)
            ?? ModerationConfiguration::DEFAULT_VIDEO_INTERVAL_SECONDS;
        $duration = $input->durationSeconds
            ?? max(1, (int) ($policy['max_video_duration_seconds'] ?? 59));

        return max(1, (int) ceil($duration / $interval)) * $operationsPerSample;
    }

    public function processingMode(ModerationInput $input, array $policy): string
    {
        if ($input->mediaType !== 'video') {
            return self::PROCESSING_SYNC;
        }

        return match ($policy['video_processing_mode'] ?? ModerationSettings::VIDEO_PROCESSING_AUTO) {
            ModerationSettings::VIDEO_PROCESSING_SYNC => self::PROCESSING_SYNC,
            ModerationSettings::VIDEO_PROCESSING_ASYNC => self::PROCESSING_ASYNC,
            default => $input->durationSeconds !== null && $input->durationSeconds < 60
                ? self::PROCESSING_SYNC
                : self::PROCESSING_ASYNC,
        };
    }

    public function scan(
        ModerationInput $input,
        array $models,
        array $policy,
        ?string $callbackUrl = null,
    ): ModerationResult|ModerationPendingResult {
        if (!$this->supports($input->mediaType)) {
            throw new ModerationProviderException(
                'Video moderation is disabled or unsupported by the configured provider.',
                errorType: 'unsupported_media',
            );
        }

        if ($input->mediaType === 'video'
            && $input->durationSeconds !== null
            && $input->durationSeconds > (int) ($policy['max_video_duration_seconds'] ?? 59)) {
            throw new ModerationProviderException(
                'The video is longer than the configured video moderation duration limit.',
                errorType: 'unsupported_media',
                providerCode: 'video_too_long',
            );
        }

        $processingMode = $this->processingMode($input, $policy);

        if ($input->mediaType === 'video'
            && $processingMode === self::PROCESSING_SYNC
            && $input->durationSeconds !== null
            && $input->durationSeconds >= 60) {
            throw new ModerationProviderException(
                'Synchronous Sightengine video moderation requires videos under 60 seconds. Select automatic or asynchronous processing for longer videos.',
                errorType: 'unsupported_media',
                providerCode: 'video_sync_duration_limit',
            );
        }

        if ($processingMode === self::PROCESSING_ASYNC && blank($callbackUrl)) {
            throw new ModerationProviderException(
                'The asynchronous video callback URL could not be generated.',
                errorType: 'configuration_error',
            );
        }

        $endpoint = match (true) {
            $input->mediaType !== 'video' => 'check.json',
            $processingMode === self::PROCESSING_ASYNC => 'video/check.json',
            default => 'video/check-sync.json',
        };

        $multipart = [
            ['name' => 'media', 'contents' => $input->stream, 'filename' => $input->filename],
            ['name' => 'models', 'contents' => implode(',', $models)],
            ['name' => 'api_user', 'contents' => (string) $this->settings->sightengine_api_user],
            ['name' => 'api_secret', 'contents' => (string) $this->settings->sightengine_api_secret],
        ];

        $customVideoInterval = $this->customVideoInterval($policy);

        if ($input->mediaType === 'video' && $customVideoInterval !== null) {
            $multipart[] = [
                'name' => 'interval',
                'contents' => (string) $customVideoInterval,
            ];
        }

        if ($processingMode === self::PROCESSING_ASYNC) {
            $multipart[] = ['name' => 'callback_url', 'contents' => $callbackUrl];
            $multipart[] = [
                'name' => 'max_duration',
                'contents' => (string) max(1, min(3600, (int) ($policy['max_video_duration_seconds'] ?? 59))),
            ];
        }

        try {
            $response = $this->client()->post($endpoint, [
                'multipart' => $multipart,
                'http_errors' => false,
            ]);
            $statusCode = $response->getStatusCode();
            $json = json_decode((string) $response->getBody(), true);

            if (!is_array($json)) {
                throw new ModerationProviderException(
                    'Sightengine returned an unreadable response.',
                    errorType: 'api_error',
                    retryable: $statusCode >= 500,
                );
            }

            if ($statusCode < 200 || $statusCode >= 300 || data_get($json, 'status') !== 'success') {
                throw $this->responseException($json, $statusCode);
            }

            if ($processingMode === self::PROCESSING_ASYNC) {
                return $this->pendingResult($json, 'submitted');
            }

            return $this->normalizer->normalize($json, (array) ($policy['flag_classes'] ?? []));
        } catch (ModerationProviderException $e) {
            throw $e;
        } catch (GuzzleException $e) {
            throw new ModerationProviderException(
                'Sightengine could not be reached. The scan can be retried.',
                errorType: 'request_error',
                retryable: true,
                previous: $e,
            );
        } finally {
            if (is_resource($input->stream)) {
                fclose($input->stream);
            }
        }
    }

    public function poll(string $mediaId, array $policy): ModerationResult|ModerationPendingResult
    {
        try {
            $response = $this->client(min(30, $this->configuration->requestTimeout()))->get('video/byid.json', [
                'query' => [
                    'id' => $mediaId,
                    'api_user' => (string) $this->settings->sightengine_api_user,
                    'api_secret' => (string) $this->settings->sightengine_api_secret,
                ],
                'http_errors' => false,
            ]);
            $statusCode = $response->getStatusCode();
            $json = json_decode((string) $response->getBody(), true);

            if (!is_array($json)) {
                throw new ModerationProviderException(
                    'Sightengine returned an unreadable video status response.',
                    errorType: 'api_error',
                    retryable: $statusCode >= 500,
                );
            }

            if ($statusCode < 200 || $statusCode >= 300 || data_get($json, 'status') !== 'success') {
                throw $this->responseException($json, $statusCode);
            }

            $output = data_get($json, 'output');

            if (!is_array($output)) {
                throw new ModerationProviderException(
                    'Sightengine did not return the asynchronous video result.',
                    errorType: 'api_error',
                    retryable: true,
                );
            }

            return $this->normalizeCallback($output, $policy);
        } catch (ModerationProviderException $exception) {
            throw $exception;
        } catch (GuzzleException $exception) {
            throw new ModerationProviderException(
                'Sightengine video status could not be retrieved. Try again shortly.',
                errorType: 'request_error',
                retryable: true,
                previous: $exception,
            );
        }
    }

    public function normalizeCallback(array $response, array $policy): ModerationResult|ModerationPendingResult
    {
        $status = (string) data_get($response, 'data.status', 'ongoing');

        if ($status === 'finished') {
            return $this->normalizer->normalize($response, (array) ($policy['flag_classes'] ?? []));
        }

        if (in_array($status, ['ongoing', 'submitted'], true)) {
            return $this->pendingResult($response, $status);
        }

        if (in_array($status, ['failure', 'stopped'], true)) {
            $message = $status === 'stopped'
                ? 'Sightengine stopped asynchronous video processing before completion.'
                : 'Sightengine could not complete asynchronous video processing.';

            throw new ModerationProviderException(
                $message,
                errorType: 'async_video_'.$status,
                providerCode: data_get($response, 'error.code'),
                retryable: true,
                operations: max(0, (int) data_get($response, 'data.operations', 0)),
                requestId: $this->requestId($response),
            );
        }

        throw new ModerationProviderException(
            'Sightengine returned an unknown asynchronous video status.',
            errorType: 'api_error',
            providerCode: $status,
            retryable: true,
            operations: max(0, (int) data_get($response, 'data.operations', 0)),
            requestId: $this->requestId($response),
        );
    }

    public function normalizeStored(array $response, array $policy): ModerationResult
    {
        return $this->normalizer->normalize(
            $response,
            (array) ($policy['flag_classes'] ?? []),
        );
    }

    protected function pendingResult(array $response, string $status): ModerationPendingResult
    {
        $mediaId = data_get($response, 'media.id');

        if (!is_string($mediaId) || $mediaId === '') {
            throw new ModerationProviderException(
                'Sightengine accepted the video but did not return a media identifier.',
                errorType: 'api_error',
                retryable: true,
                operations: max(0, (int) data_get($response, 'data.operations', 0)),
                requestId: $this->requestId($response),
            );
        }

        $progress = data_get($response, 'data.progress');

        return new ModerationPendingResult(
            status: $status,
            progress: is_numeric($progress) ? max(0, min(1, (float) $progress)) : null,
            operations: max(0, (int) data_get($response, 'data.operations', 0)),
            requestId: $this->requestId($response),
            mediaId: $mediaId,
            raw: $this->normalizer->sanitize($response),
        );
    }

    protected function requestId(array $response): ?string
    {
        $requestId = data_get($response, 'request.id', data_get($response, 'request'));

        return is_string($requestId) && $requestId !== '' ? $requestId : null;
    }

    protected function client(?int $timeout = null): Client
    {
        $timeout ??= $this->configuration->requestTimeout();

        return new Client([
            'base_uri' => 'https://api.sightengine.com/1.0/',
            'connect_timeout' => min(15, $timeout),
            'timeout' => $timeout,
        ]);
    }

    protected function responseException(array $json, int $statusCode): ModerationProviderException
    {
        $type = (string) data_get($json, 'error.type', 'provider_error');
        $code = data_get($json, 'error.code');
        $message = (string) data_get($json, 'error.message', 'Sightengine rejected the moderation request.');
        $type = $this->normalizeErrorType($type, $message, $code !== null ? (string) $code : null);
        $retryable = $statusCode === 408
            || $statusCode === 429
            || $statusCode >= 500
            || in_array($type, ['api_error', 'request_error', 'unknown_error', 'provider_capacity'], true);

        return new ModerationProviderException(
            message: $this->publicErrorMessage($type, $message),
            errorType: $type,
            providerCode: $code !== null ? (string) $code : null,
            retryable: $retryable,
            operations: max(0, (int) data_get($json, 'request.operations', data_get($json, 'data.operations', 0))),
            requestId: $this->requestId($json),
        );
    }

    protected function publicErrorMessage(string $type, string $message): string
    {
        return match ($type) {
            'usage_limit' => 'Sightengine usage limit reached. The scan has been paused.',
            'provider_capacity' => 'The Sightengine video concurrency limit is currently in use. Try the video again when a provider slot becomes available.',
            'credentials_error' => 'Sightengine credentials were rejected.',
            'plan_error' => str_contains(strtolower($message), 'interval')
                ? 'The selected video sampling interval is not supported by the current Sightengine plan. Select Provider default in moderation settings before starting another scan.'
                : 'This moderation feature is not available on the current Sightengine plan.',
            'media_error' => 'Sightengine could not process this media file.',
            default => mb_substr($message, 0, 500),
        };
    }

    protected function customVideoInterval(array $policy): ?float
    {
        $interval = $policy['video_interval_seconds'] ?? null;

        if (!is_numeric($interval)) {
            return null;
        }

        $interval = (float) $interval;

        return in_array($interval, [0.5, 1.0, 2.0, 3.0, 4.0, 5.0], true)
            ? $interval
            : null;
    }

    protected function normalizeErrorType(string $type, string $message, ?string $code = null): string
    {
        $message = strtolower($message);

        if ($code === '37' || str_contains($message, 'simultaneous video streams')) {
            return 'provider_capacity';
        }

        if (str_contains($message, 'plan does not support')
            || str_contains($message, 'enterprise plan')) {
            return 'plan_error';
        }

        return $type;
    }
}
