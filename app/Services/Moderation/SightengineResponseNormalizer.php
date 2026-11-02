<?php

namespace App\Services\Moderation;

use App\Services\Moderation\Data\ModerationResult;

class SightengineResponseNormalizer
{
    public function normalize(array $response, array $flagClasses): ModerationResult
    {
        $flagClasses = array_values(array_unique(array_map('strval', $flagClasses)));
        $frames = data_get($response, 'data.frames');
        $samples = is_array($frames) ? $frames : [['nudity' => (array) data_get($response, 'nudity', [])]];
        $scores = array_fill_keys($flagClasses, 0.0);
        $maxScore = 0.0;
        $matchedClass = null;
        $matchedAtMs = null;

        foreach ($samples as $sample) {
            $nudity = (array) data_get($sample, 'nudity', []);
            $position = data_get($sample, 'info.position');

            foreach ($flagClasses as $class) {
                $score = $this->classScore($nudity, $class);
                $scores[$class] = max($scores[$class] ?? 0.0, $score);

                if ($score > $maxScore) {
                    $maxScore = $score;
                    $matchedClass = $class;
                    $matchedAtMs = is_numeric($position) ? (int) $position : null;
                }
            }
        }

        return new ModerationResult(
            scores: $scores,
            maxScore: $maxScore,
            matchedClass: $matchedClass,
            matchedAtMs: $matchedAtMs,
            operations: max(0, (int) data_get($response, 'request.operations', data_get($response, 'data.operations', 0))),
            requestId: $this->nullableString(data_get($response, 'request.id', data_get($response, 'request'))),
            mediaId: $this->nullableString(data_get($response, 'media.id')),
            raw: $this->sanitize($response),
        );
    }

    protected function classScore(array $nudity, string $class): float
    {
        $score = data_get($nudity, $class);

        if ($score === null) {
            $score = data_get($nudity, 'suggestive_classes.'.$class);
        }

        return is_numeric($score) ? max(0, min(1, (float) $score)) : 0.0;
    }

    public function sanitize(array $response): array
    {
        if (isset($response['media']['uri'])) {
            $uri = (string) $response['media']['uri'];
            $path = parse_url($uri, PHP_URL_PATH);
            $response['media']['uri'] = basename(is_string($path) ? $path : $uri);
        }

        return $response;
    }

    protected function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
