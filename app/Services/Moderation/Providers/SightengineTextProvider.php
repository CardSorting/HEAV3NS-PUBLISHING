<?php

namespace App\Services\Moderation\Providers;

use App\Services\Moderation\Contracts\TextModerationProvider;
use App\Services\Moderation\Data\ModerationResult;
use App\Services\Moderation\Exceptions\ModerationProviderException;
use App\Services\Moderation\ModerationConfiguration;
use App\Settings\ModerationSettings;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SightengineTextProvider implements TextModerationProvider
{
    public function __construct(
        protected ModerationSettings $settings,
        protected ModerationConfiguration $configuration,
    ) {
    }

    public function key(): string
    {
        return 'sightengine';
    }

    public function estimateOperations(string $text, array $models, array $policy): int
    {
        $chunks = max(1, (int) ceil(max(1, mb_strlen($text)) / 1000));

        return $chunks * max(1, count($models));
    }

    public function scan(string $text, array $models, array $policy): ModerationResult
    {
        try {
            $response = $this->client()->post('text/check.json', [
                'form_params' => [
                    'text' => $text,
                    'mode' => 'ml',
                    'models' => implode(',', $models),
                    'lang' => implode(',', (array) ($policy['languages'] ?? ['en'])),
                    'api_user' => (string) $this->settings->sightengine_api_user,
                    'api_secret' => (string) $this->settings->sightengine_api_secret,
                ],
                'http_errors' => false,
            ]);
            $statusCode = $response->getStatusCode();
            $json = json_decode((string) $response->getBody(), true);

            if (!is_array($json)) {
                throw new ModerationProviderException(
                    'Sightengine returned an unreadable text moderation response.',
                    errorType: 'api_error',
                    retryable: $statusCode >= 500,
                );
            }

            if ($statusCode < 200 || $statusCode >= 300 || data_get($json, 'status') !== 'success') {
                throw $this->responseException($json, $statusCode);
            }

            return $this->normalize($json, $policy);
        } catch (ModerationProviderException $exception) {
            throw $exception;
        } catch (GuzzleException $exception) {
            throw new ModerationProviderException(
                'Sightengine could not be reached. The text scan can be retried.',
                errorType: 'request_error',
                retryable: true,
                previous: $exception,
            );
        }
    }

    public function normalizeStored(array $response, array $policy): ModerationResult
    {
        return $this->normalize($response, $policy);
    }

    protected function normalize(array $response, array $policy): ModerationResult
    {
        $classes = array_values(array_unique(array_map(
            'strval',
            (array) ($policy['flag_classes'] ?? []),
        )));
        $scores = [];
        $maxScore = 0.0;
        $matchedClass = null;

        foreach ($classes as $class) {
            $score = data_get($response, 'moderation_classes.'.$class, 0);
            $score = is_numeric($score) ? max(0, min(1, (float) $score)) : 0.0;
            $scores[$class] = $score;

            if ($score > $maxScore) {
                $maxScore = $score;
                $matchedClass = $class;
            }
        }

        return new ModerationResult(
            scores: $scores,
            maxScore: $maxScore,
            matchedClass: $matchedClass,
            matchedAtMs: null,
            operations: max(0, (int) data_get($response, 'request.operations', 0)),
            requestId: $this->nullableString(data_get($response, 'request.id', data_get($response, 'request'))),
            mediaId: null,
            raw: $this->sanitize($response),
        );
    }

    protected function responseException(array $json, int $statusCode): ModerationProviderException
    {
        $type = (string) data_get($json, 'error.type', 'provider_error');
        $code = data_get($json, 'error.code');
        $message = (string) data_get($json, 'error.message', 'Sightengine rejected the text moderation request.');
        $retryable = $statusCode === 408
            || $statusCode === 429
            || $statusCode >= 500
            || in_array($type, ['api_error', 'request_error', 'unknown_error'], true);

        return new ModerationProviderException(
            message: match ($type) {
                'usage_limit' => 'Sightengine usage limit reached. The scan has been paused.',
                'credentials_error' => 'Sightengine credentials were rejected.',
                default => mb_substr($message, 0, 500),
            },
            errorType: $type,
            providerCode: $code !== null ? (string) $code : null,
            retryable: $retryable,
            operations: max(0, (int) data_get($json, 'request.operations', 0)),
            requestId: $this->nullableString(data_get($json, 'request.id', data_get($json, 'request'))),
        );
    }

    protected function sanitize(array $response): array
    {
        unset($response['text'], $response['api_user'], $response['api_secret']);

        return $response;
    }

    protected function client(): Client
    {
        $timeout = $this->configuration->requestTimeout();

        return new Client([
            'base_uri' => 'https://api.sightengine.com/1.0/',
            'connect_timeout' => min(15, $timeout),
            'timeout' => $timeout,
        ]);
    }

    protected function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
