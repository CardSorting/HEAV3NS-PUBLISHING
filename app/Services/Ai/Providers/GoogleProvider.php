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

class GoogleProvider implements TextGenerationProvider, ImageGenerationProvider
{
    use HandlesAiErrors;

    public function generateText(AiTextRequest $request, ?User $user = null): AiTextResponse
    {
        $model = $request->model ?: (string) getSetting('ai.text_model');

        try {
            $response = $this->client()->post(
                $this->baseUrl().'/v1beta/models/'.$model.':generateContent',
                [
                    'headers' => $this->headers(),
                    'json' => [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $request->prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => $this->buildGenerationConfig($request, $model),
                    ],
                ]
            );

            $json = json_decode((string) $response->getBody(), true);

            if (config('ai.log_enabled')) {
                Log::channel('ai')->debug('Google AI response', [
                    'provider' => 'google',
                    'model' => $model,
                    'finish_reason' => data_get($json, 'candidates.0.finishReason'),
                    'usage' => data_get($json, 'usageMetadata'),
                ]);
            }

            return new AiTextResponse(
                text: $this->extractText($json),
                raw: $json,
            );
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Google text generation failed', [
                'provider' => 'google',
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
            $response = $this->client()->post(
                $this->baseUrl().'/v1beta/models/'.$model.':generateContent',
                [
                    'headers' => $this->headers(),
                    'json' => [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $request->prompt],
                                ],
                            ],
                        ],
                        'generationConfig' => $this->buildImageGenerationConfig($request, $model),
                    ],
                ]
            );

            $json = json_decode((string) $response->getBody(), true);

            $b64 = $this->extractImageBase64($json);

            if ($b64 === '') {
                Log::channel('ai')->warning('Google image generation returned no image payload', [
                    'provider' => 'google',
                    'model' => $model,
                    'finish_reason' => data_get($json, 'candidates.0.finishReason'),
                    'raw' => $json,
                ]);
            }

            return new AiImageResponse(
                base64: $b64,
                raw: $json,
            );
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Google image generation failed', [
                'provider' => 'google',
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
            $response = $this->client()->get($this->baseUrl().'/v1beta/models', [
                'headers' => $this->headers(),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return collect((array) data_get($json, 'models', []))
                ->filter(fn (array $item): bool => $this->supportsGenerateContent($item))
                ->reject(fn (array $item): bool => $this->looksLikeImageOnlyModel($item))
                ->mapWithKeys(function (array $item): array {
                    $name = str_replace('models/', '', (string) ($item['name'] ?? ''));
                    $label = (string) ($item['displayName'] ?? $name);

                    return $name !== '' ? [$name => $label] : [];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('Google model listing failed', [
                'provider' => 'google',
                'error' => $e->getMessage(),
            ]);

            return (array) config('ai.providers.google.fallback_text_models', []);
        }
    }

    public function listImageModels(): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/v1beta/models', [
                'headers' => $this->headers(),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return collect((array) data_get($json, 'models', []))
                ->filter(fn (array $item): bool => $this->supportsGenerateContent($item))
                ->filter(fn (array $item): bool => $this->looksLikeImageCapableModel($item))
                ->mapWithKeys(function (array $item): array {
                    $name = str_replace('models/', '', (string) ($item['name'] ?? ''));
                    $label = (string) ($item['displayName'] ?? $name);

                    return $name !== '' ? [$name => $label] : [];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('Google image model listing failed', [
                'provider' => 'google',
                'error' => $e->getMessage(),
            ]);

            return (array) config('ai.providers.google.fallback_image_models', []);
        }
    }

    protected function buildGenerationConfig(AiTextRequest $request, string $model): array
    {
        $config = array_filter([
            'temperature' => $request->temperature,
            'maxOutputTokens' => $request->maxTokens,
        ], fn ($value) => $value !== null);

        if ($this->usesThinkingBudget($model)) {
            $config['thinkingConfig'] = [
                'thinkingBudget' => 0,
            ];
        }

        if ($this->usesThinkingLevel($model)) {
            $config['thinkingConfig'] = [
                'thinkingLevel' => 'low',
            ];
        }

        return $config;
    }

    protected function buildImageGenerationConfig(AiImageRequest $request, string $model): array
    {
        $config = [
            'responseModalities' => ['IMAGE'],
        ];

        $aspectRatio = $this->mapAspectRatio($request->size);

        if ($aspectRatio !== null) {
            $config['imageConfig'] = [
                'aspectRatio' => $aspectRatio,
            ];
        }

        return $config;
    }

    protected function usesThinkingBudget(string $model): bool
    {
        return str_starts_with($model, 'gemini-2.5');
    }

    protected function usesThinkingLevel(string $model): bool
    {
        return str_starts_with($model, 'gemini-3');
    }

    protected function supportsGenerateContent(array $item): bool
    {
        $methods = (array) ($item['supportedGenerationMethods'] ?? []);

        return in_array('generateContent', $methods, true);
    }

    protected function looksLikeImageCapableModel(array $item): bool
    {
        $name = str_replace('models/', '', (string) ($item['name'] ?? ''));
        $displayName = (string) ($item['displayName'] ?? '');
        $haystack = strtolower($name.' '.$displayName);

        return str_contains($haystack, 'image')
            || str_contains($haystack, 'imagen')
            || str_contains($haystack, 'nano banana');
    }

    protected function looksLikeImageOnlyModel(array $item): bool
    {
        return $this->looksLikeImageCapableModel($item);
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

    protected function headers(): array
    {
        return [
            'x-goog-api-key' => (string) getSetting('ai.google_api_key'),
            'Content-Type' => 'application/json',
        ];
    }

    protected function baseUrl(): string
    {
        return rtrim((string) (getSetting('ai.google_base_url') ?: 'https://generativelanguage.googleapis.com'), '/');
    }

    protected function client(): Client
    {
        return new Client([
            'timeout' => 120,
            'connect_timeout' => 15,
        ]);
    }

    protected function extractText(array $json): string
    {
        $parts = (array) data_get($json, 'candidates.0.content.parts', []);
        $text = [];

        foreach ($parts as $part) {
            if (is_string($part['text'] ?? null) && $part['text'] !== '') {
                $text[] = $part['text'];
            }
        }

        return trim(implode("\n", $text));
    }

    protected function extractImageBase64(array $json): string
    {
        $parts = (array) data_get($json, 'candidates.0.content.parts', []);

        foreach ($parts as $part) {
            $inlineData = $part['inlineData'] ?? null;

            if (!is_array($inlineData)) {
                continue;
            }

            $data = $inlineData['data'] ?? null;
            if (is_string($data) && $data !== '') {
                return $data;
            }
        }

        return '';
    }
}
