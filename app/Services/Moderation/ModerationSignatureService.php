<?php

namespace App\Services\Moderation;

class ModerationSignatureService
{
    public const NORMALIZER_VERSION = 'sightengine-nudity-v1';
    public const TEXT_NORMALIZER_VERSION = 'sightengine-text-v1';

    public function model(string $provider, array $models, float $videoInterval, ?string $mediaType = null): string
    {
        $models = array_values(array_unique(array_map('strval', $models)));
        sort($models);

        $payload = [
            'provider' => $provider,
            'models' => $models,
            'media_type' => $mediaType ?? 'mixed',
            'normalizer' => self::NORMALIZER_VERSION,
        ];

        if ($mediaType !== 'image') {
            $payload['video_interval'] = $videoInterval;
        }

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function policy(array $policy): string
    {
        $classes = array_values(array_unique(array_map('strval', (array) ($policy['flag_classes'] ?? []))));
        sort($classes);

        return hash('sha256', json_encode([
            'flag_classes' => $classes,
            'review_threshold' => (float) ($policy['review_threshold'] ?? 0.65),
            'violation_threshold' => (float) ($policy['violation_threshold'] ?? 0.90),
        ], JSON_THROW_ON_ERROR));
    }

    public function textModel(string $provider, array $models, array $languages): string
    {
        $models = array_values(array_unique(array_map('strval', $models)));
        $languages = array_values(array_unique(array_map('strval', $languages)));
        sort($models);
        sort($languages);

        return hash('sha256', json_encode([
            'provider' => $provider,
            'models' => $models,
            'languages' => $languages,
            'media_type' => 'text',
            'normalizer' => self::TEXT_NORMALIZER_VERSION,
        ], JSON_THROW_ON_ERROR));
    }
}
