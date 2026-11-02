<?php

namespace App\Services\Ai\Providers;

use App\Model\User;
use App\Services\Ai\Concerns\HandlesAiErrors;
use App\Services\Ai\Contracts\TextGenerationProvider;
use App\Services\Ai\Data\AiTextRequest;
use App\Services\Ai\Data\AiTextResponse;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OllamaProvider implements TextGenerationProvider
{
    use HandlesAiErrors;

    public function generateText(AiTextRequest $request, ?User $user = null): AiTextResponse
    {
        $model = $request->model ?: (string) getSetting('ai.text_model');

        try {
            $response = $this->client()->post($this->baseUrl().'/api/chat', [
                'json' => [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful assistant. Do not include any internal reasoning or thinking. Respond only with the final answer.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $request->prompt,
                        ],
                    ],
                    'stream' => false,
                    'options' => [
                        'temperature' => $request->temperature ?? 0.7,
                        'num_predict' => $request->maxTokens ?? 512,
                    ],
                ],
            ]);

            $json = json_decode((string) $response->getBody(), true);

            $content = trim((string) data_get($json, 'message.content', ''));

            if ($content === '') {
                Log::channel('ai')->warning('Ollama returned empty response', [
                    'provider' => 'ollama',
                    'model' => $model,
                    'feature' => 'text_generation',
                    'prompt_preview' => substr($request->prompt, 0, 200),
                    'done_reason' => data_get($json, 'done_reason'),
                    'eval_count' => data_get($json, 'eval_count'),
                    'raw' => $json,
                ]);

                throw new \RuntimeException(
                    __('Text generation failed. Please try again.')
                );
            }

            return new AiTextResponse(
                text: $content,
                raw: $json,
            );
        } catch (\Throwable $e) {
            Log::channel('ai')->error('Ollama text generation failed', [
                'provider' => 'ollama',
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
            $response = $this->client()->get($this->baseUrl().'/api/tags');
            $json = json_decode((string) $response->getBody(), true);

            return collect((array) data_get($json, 'models', []))
                ->mapWithKeys(function (array $item): array {
                    $name = (string) ($item['name'] ?? '');
                    return $name !== '' ? [$name => $name] : [];
                })
                ->all();
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('Ollama model listing failed', [
                'provider' => 'ollama',
                'error' => $e->getMessage(),
            ]);

            return (array) config('ai.providers.ollama.fallback_text_models', []);
        }
    }

    protected function baseUrl(): string
    {
        return rtrim((string) (getSetting('ai.ollama_base_url') ?: 'http://127.0.0.1:11434'), '/');
    }

    protected function client(): Client
    {
        return new Client([
            'timeout' => 120,
            'connect_timeout' => 5,
        ]);
    }
}
