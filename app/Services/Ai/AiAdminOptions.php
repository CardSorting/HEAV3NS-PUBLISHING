<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;

class AiAdminOptions
{
    public function __construct(
        protected AiManager $aiManager,
    ) {
    }

    public function getTextProviders(): array
    {
        return collect(config('ai.providers', []))
            ->filter(fn (array $provider, string $key): bool => $this->supportsText($key, $provider))
            ->mapWithKeys(fn (array $provider, string $key): array => [$key => $provider['label'] ?? $key])
            ->all();
    }

    public function getImageProviders(): array
    {
        return collect(config('ai.providers', []))
            ->filter(fn (array $provider, string $key): bool => $this->supportsImages($key, $provider))
            ->mapWithKeys(fn (array $provider, string $key): array => [$key => $provider['label'] ?? $key])
            ->all();
    }

    public function getTextModelsForProvider(string $provider): array
    {
        if ($provider === '') {
            return [];
        }

        try {
            $driver = $this->aiManager->textProvider($provider);
            $models = $driver->listTextModels();

            if (!empty($models)) {
                return $models;
            }
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('Failed to fetch live AI text models', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }

        return (array) config("ai.providers.{$provider}.fallback_text_models", []);
    }

    public function getImageModelsForProvider(string $provider): array
    {
        if ($provider === '') {
            return [];
        }

        try {
            $driver = $this->aiManager->imageProvider($provider);
            $models = $driver->listImageModels();

            if (!empty($models)) {
                return $models;
            }
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('Failed to fetch live AI image models', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }

        return (array) config("ai.providers.{$provider}.fallback_image_models", []);
    }

    public function getDefaultTextModelForProvider(string $provider): ?string
    {
        return array_key_first($this->getTextModelsForProvider($provider));
    }

    public function getDefaultImageModelForProvider(string $provider): ?string
    {
        return array_key_first($this->getImageModelsForProvider($provider));
    }

    protected function supportsText(string $provider, array $config): bool
    {
        if (array_key_exists('supports_text', $config)) {
            return (bool) $config['supports_text'];
        }

        if (!empty($config['fallback_text_models'])) {
            return true;
        }

        try {
            $this->aiManager->textProvider($provider);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function supportsImages(string $provider, array $config): bool
    {
        if (array_key_exists('supports_images', $config)) {
            return (bool) $config['supports_images'];
        }

        if (!empty($config['fallback_image_models'])) {
            return true;
        }

        try {
            $this->aiManager->imageProvider($provider);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
