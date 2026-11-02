<?php

namespace App\Services\Ai\Providers;

use App\Model\User;
use App\Services\Ai\Concerns\HandlesAiErrors;
use App\Services\Ai\Contracts\TextGenerationProvider;
use App\Services\Ai\Data\AiTextRequest;
use App\Services\Ai\Data\AiTextResponse;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements TextGenerationProvider
{
    use HandlesAiErrors;

    public function generateText(AiTextRequest $request, ?User $user = null): AiTextResponse
    {
        $model = $request->model ?: (string) getSetting('ai.text_model');

        try {
            $response = $this->client()->post($this->baseUrl().'/v1/messages', [
                'headers' => $this->headers(),
                'json' => array_filter([
                    'model' => $model,
                    'max_tokens' => $request->maxTokens ?: 200,
                    'temperature' => $request->temperature,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $request->prompt,
                        ],
                    ],
                ], fn ($value) => $value !== null),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return new AiTextResponse(
                text: $this->extractText($json),
                raw: $json,
            );
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Anthropic text generation failed', [
                'provider' => 'anthropic',
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

    public function listTextModels(): array
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/v1/models', [
                'headers' => $this->headers(),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return collect((array) data_get($json, 'data', []))
                ->mapWithKeys(function (array $item): array {
                    $id = (string) ($item['id'] ?? '');
                    $label = (string) ($item['display_name'] ?? $id);

                    return $id !== '' ? [$id => $label] : [];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('Anthropic model listing failed', [
                'provider' => 'anthropic',
                'base_url' => $this->baseUrl(),
                'error' => $e->getMessage(),
            ]);

            return (array) config('ai.providers.anthropic.fallback_text_models', []);
        }
    }

    protected function baseUrl(): string
    {
        return rtrim((string) (getSetting('ai.anthropic_base_url') ?: 'https://api.anthropic.com'), '/');
    }

    protected function headers(): array
    {
        return [
            'x-api-key' => (string) getSetting('ai.anthropic_api_key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];
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
        $content = (array) data_get($json, 'content', []);
        $parts = [];

        foreach ($content as $item) {
            if (($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null)) {
                $parts[] = $item['text'];
            }
        }

        return trim(implode("\n", $parts));
    }
}
