<?php

namespace App\Services\Ai;

class AiRegistry
{
    public function provider(string $key): array
    {
        return config("ai.providers.{$key}", []);
    }

    public function providerExists(string $key): bool
    {
        return config()->has("ai.providers.{$key}");
    }

    public function modelConfig(string $provider, string $model): array
    {
        return config("ai.providers.{$provider}.models.{$model}", []);
    }

    public function providerLabel(string $key): string
    {
        return (string) data_get($this->provider($key), 'label', $key);
    }

    public function textModels(string $provider): array
    {
        $models = (array) data_get($this->provider($provider), 'models', []);

        return collect($models)
            ->filter(fn ($cfg) => ($cfg['type'] ?? null) === 'text')
            ->mapWithKeys(fn ($cfg, $key) => [$key => $cfg['label'] ?? $key])
            ->all();
    }

    public function imageModels(string $provider): array
    {
        $models = (array) data_get($this->provider($provider), 'models', []);

        return collect($models)
            ->filter(fn ($cfg) => ($cfg['type'] ?? null) === 'image')
            ->mapWithKeys(fn ($cfg, $key) => [$key => $cfg['label'] ?? $key])
            ->all();
    }
}
