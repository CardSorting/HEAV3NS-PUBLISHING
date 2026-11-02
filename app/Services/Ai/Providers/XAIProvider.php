<?php

namespace App\Services\Ai\Providers;

use App\Model\User;
use App\Services\Ai\Concerns\HandlesAiErrors;
use App\Services\Ai\Contracts\ImageGenerationProvider;
use App\Services\Ai\Contracts\TextGenerationProvider;
use App\Services\Ai\Data\AiImageRequest;
use App\Services\Ai\Data\AiImageResponse;
use App\Services\Ai\Data\AiTextRequest;
use App\Services\Ai\Data\AiTextResponse;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class XAIProvider implements TextGenerationProvider, ImageGenerationProvider
{
    use HandlesAiErrors;

    public function generateText(AiTextRequest $request, ?User $user = null): AiTextResponse
    {
        $model = $request->model ?: (string) getSetting('ai.text_model');

        try {
            $response = $this->client()->post($this->baseUrl().'/chat/completions', [
                'headers' => $this->headers(),
                'json' => array_filter([
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $request->prompt,
                        ],
                    ],
                    'max_tokens' => $request->maxTokens,
                    'temperature' => $request->temperature,
                ], fn ($value) => $value !== null),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return new AiTextResponse(
                text: trim((string) data_get($json, 'choices.0.message.content', '')),
                raw: $json,
            );
        } catch (\Throwable $e) {
            Log::channel('ai')->error('xAI text generation failed', [
                'provider' => 'xai',
                'model' => $model,
                'feature' => 'text_generation',
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                $this->toPublicErrorMessage('text_generation', $e->getMessage()),
                previous: $e,
            );
        }
    }

    public function generateImage(AiImageRequest $request, ?User $user = null): AiImageResponse
    {
        $model = $request->model ?: (string) getSetting('ai.image_model');

        try {
            $response = $this->client()->post($this->baseUrl().'/images/generations', [
                'headers' => $this->headers(),
                'json' => array_filter([
                    'model' => $model,
                    'prompt' => $request->prompt,
                    'n' => 1,
                    'aspect_ratio' => $this->mapAspectRatio($request->size),
                ], fn ($value) => $value !== null),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            $b64 = data_get($json, 'data.0.b64_json');
            $url = data_get($json, 'data.0.url');

            if (is_string($b64) && $b64 !== '') {
                return new AiImageResponse(base64: $b64, raw: $json);
            }

            if (is_string($url) && $url !== '') {
                $imageResponse = $this->client()->get($url, [
                    'headers' => [
                        'Accept' => 'image/*',
                    ],
                ]);

                $bytes = (string) $imageResponse->getBody();

                return new AiImageResponse(
                    base64: base64_encode($bytes),
                    raw: $json,
                );
            }

            Log::channel('ai')->warning('xAI image generation returned no usable image payload', [
                'provider' => 'xai',
                'model' => $model,
                'feature' => 'image_generation',
                'raw' => $json,
            ]);

            throw new \RuntimeException(
                __('Image generation failed. Please try again.')
            );
        } catch (\Throwable $e) {
            Log::channel('ai')->error('xAI image generation failed', [
                'provider' => 'xai',
                'model' => $model,
                'feature' => 'image_generation',
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                $this->toPublicErrorMessage('image_generation', $e->getMessage()),
                previous: $e,
            );
        }
    }

    protected function mapAspectRatio(?string $size): ?string
    {
        return match ($size) {
            '1024x1024' => '1:1',
            '1792x1024' => '16:9',
            '1024x1792' => '9:16',
            default => null,
        };
    }

    public function listTextModels(): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/language-models', [
                'headers' => $this->headers(),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return collect((array) data_get($json, 'models', []))
                ->mapWithKeys(function (array $item): array {
                    $id = (string) ($item['model_id'] ?? $item['id'] ?? '');
                    $label = (string) ($item['name'] ?? $item['model_id'] ?? $item['id'] ?? '');

                    return $id !== '' ? [$id => $label] : [];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('xAI text model listing failed', [
                'provider' => 'xai',
                'base_url' => $this->baseUrl(),
                'error' => $e->getMessage(),
            ]);

            return (array) config('ai.providers.xai.fallback_text_models', []);
        }
    }

    public function listImageModels(): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/image-generation-models', [
                'headers' => $this->headers(),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return collect((array) data_get($json, 'models', []))
                ->mapWithKeys(function (array $item): array {
                    $id = (string) ($item['model_id'] ?? $item['id'] ?? '');
                    $label = (string) ($item['name'] ?? $item['model_id'] ?? $item['id'] ?? '');

                    return $id !== '' ? [$id => $label] : [];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('xAI image model listing failed', [
                'provider' => 'xai',
                'base_url' => $this->baseUrl(),
                'error' => $e->getMessage(),
            ]);

            return (array) config('ai.providers.xai.fallback_image_models', []);
        }
    }

    protected function baseUrl(): string
    {
        return rtrim((string) (getSetting('ai.xai_base_url') ?: 'https://api.x.ai/v1'), '/');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.(string) getSetting('ai.xai_api_key'),
            'Content-Type' => 'application/json',
        ];
    }

    protected function client(): Client
    {
        return new Client([
            'timeout' => 120,
            'connect_timeout' => 15,
        ]);
    }
}
