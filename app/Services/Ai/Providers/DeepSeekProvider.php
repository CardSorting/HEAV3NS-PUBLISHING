<?php

namespace App\Services\Ai\Providers;

use App\Model\User;
use App\Services\Ai\Concerns\HandlesAiErrors;
use App\Services\Ai\Contracts\TextGenerationProvider;
use App\Services\Ai\Data\AiTextRequest;
use App\Services\Ai\Data\AiTextResponse;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class DeepSeekProvider implements TextGenerationProvider
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
                            'role' => 'system',
                            'content' => 'You are a helpful assistant. Respond only with the final answer.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $request->prompt,
                        ],
                    ],
                    'thinking' => [
                        'type' => 'disabled',
                    ],
                    'max_tokens' => $request->maxTokens,
                    'temperature' => $request->temperature,
                    'stream' => false,
                ], fn ($value) => $value !== null),
            ]);

            $json = json_decode((string) $response->getBody(), true);

            return new AiTextResponse(
                text: trim((string) data_get($json, 'choices.0.message.content', '')),
                raw: $json,
            );
        } catch (\Throwable $e) {
            Log::channel('ai')->error('DeepSeek text generation failed', [
                'provider' => 'deepseek',
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
        return (array) config('ai.providers.deepseek.fallback_text_models', []);
    }

    protected function baseUrl(): string
    {
        return rtrim((string) (getSetting('ai.deepseek_base_url') ?: 'https://api.deepseek.com'), '/');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.(string) getSetting('ai.deepseek_api_key'),
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
