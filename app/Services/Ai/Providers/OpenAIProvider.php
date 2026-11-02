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

class OpenAIProvider implements TextGenerationProvider, ImageGenerationProvider
{
    use HandlesAiErrors;

    public function generateText(AiTextRequest $request, ?User $user = null): AiTextResponse
    {
        $model = $request->model ?: (string) getSetting('ai.text_model');

        try {
            $response = $this->client()->post($this->baseUrl().'/responses', [
                'headers' => $this->headers(),
                'json' => array_filter([
                    'model' => $model,
                    'input' => $request->prompt,
                    'max_output_tokens' => $request->maxTokens,
                    'temperature' => $request->temperature,
                ], fn ($value) => $value !== null),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return new AiTextResponse(
                text: $this->normalizeOutput($this->extractResponseText($json)),
                raw: $json,
            );
        } catch (\Throwable $e) {
            Log::channel('ai')->error('OpenAI text generation failed', [
                'provider' => 'openai',
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
            $payload = [
                'model' => $model,
                'prompt' => $request->prompt,
                'n' => 1,
                'size' => $request->size,
            ];

            if (in_array($model, ['dall-e-2', 'dall-e-3'], true)) {
                $payload['response_format'] = 'b64_json';
            }

            $response = $this->client()->post($this->baseUrl().'/images/generations', [
                'headers' => $this->headers(),
                'json' => $payload,
            ]);

            $json = json_decode((string) $response->getBody(), true);

            $b64 = data_get($json, 'data.0.b64_json');
            if (is_string($b64) && $b64 !== '') {
                return new AiImageResponse(base64: $b64, raw: $json);
            }

            $url = data_get($json, 'data.0.url');
            if (is_string($url) && $url !== '') {
                $imageResponse = $this->client()->get($url);

                return new AiImageResponse(
                    base64: base64_encode((string) $imageResponse->getBody()),
                    raw: $json,
                );
            }

            Log::channel('ai')->warning('OpenAI image generation returned no image payload', [
                'provider' => 'openai',
                'model' => $model,
                'feature' => 'image_generation',
                'raw' => $json,
            ]);

            throw new \RuntimeException(
                __('Image generation failed. Please try again.')
            );
        } catch (\Throwable $e) {
            Log::channel('ai')->error('OpenAI image generation failed', [
                'provider' => 'openai',
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

    public function listTextModels(): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/models', [
                'headers' => $this->headers(),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return collect((array) data_get($json, 'data', []))
                ->pluck('id')
                ->filter(fn ($id) => is_string($id) && !$this->looksLikeImageModel($id))
                ->sort()
                ->mapWithKeys(fn ($id) => [$id => $id])
                ->all();
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('OpenAI text model listing failed', [
                'provider' => 'openai',
                'error' => $e->getMessage(),
            ]);

            return (array) config('ai.providers.openai.fallback_text_models', []);
        }
    }

    public function listImageModels(): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/models', [
                'headers' => $this->headers(),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return collect((array) data_get($json, 'data', []))
                ->pluck('id')
                ->filter(fn ($id) => is_string($id) && $this->looksLikeImageModel($id))
                ->sort()
                ->mapWithKeys(fn ($id) => [$id => $id])
                ->all();
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('OpenAI image model listing failed', [
                'provider' => 'openai',
                'error' => $e->getMessage(),
            ]);

            return (array) config('ai.providers.openai.fallback_image_models', []);
        }
    }

    protected function baseUrl(): string
    {
        return rtrim((string) (getSetting('ai.openai_base_url') ?: 'https://api.openai.com/v1'), '/');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.getSetting('ai.openai_api_key'),
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

    protected function extractResponseText(array $json): string
    {
        $outputText = data_get($json, 'output_text');
        if (is_string($outputText)) {
            return $outputText;
        }

        $output = (array) data_get($json, 'output', []);
        $parts = [];

        foreach ($output as $item) {
            foreach ((array) ($item['content'] ?? []) as $content) {
                $text = $content['text'] ?? null;
                if (is_string($text) && $text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return implode("\n", $parts);
    }

    protected function looksLikeImageModel(string $id): bool
    {
        return str_contains($id, 'image') || str_starts_with($id, 'dall-e');
    }

    protected function normalizeOutput(string $text): string
    {
        $text = trim($text);

        if (
            (str_starts_with($text, '"') && str_ends_with($text, '"')) ||
            (str_starts_with($text, "'") && str_ends_with($text, "'"))
        ) {
            $text = mb_substr($text, 1, -1);
        }

        return trim($text);
    }
}
