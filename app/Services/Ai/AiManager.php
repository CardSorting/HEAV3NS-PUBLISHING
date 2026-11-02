<?php

namespace App\Services\Ai;

use App\Model\User;
use App\Services\Ai\Contracts\ImageGenerationProvider;
use App\Services\Ai\Contracts\TextGenerationProvider;
use App\Services\Ai\Data\AiImageRequest;
use App\Services\Ai\Data\AiTextRequest;
use App\Services\Ai\Providers\AnthropicProvider;
use App\Services\Ai\Providers\DeepSeekProvider;
use App\Services\Ai\Providers\GoogleProvider;
use App\Services\Ai\Providers\OllamaProvider;
use App\Services\Ai\Providers\OpenAIProvider;
use App\Services\Ai\Providers\XAIProvider;
use InvalidArgumentException;

class AiManager
{
    public function __construct(
        protected OpenAIProvider $openAIProvider,
        protected OllamaProvider $ollamaProvider,
        protected AnthropicProvider $anthropicProvider,
        protected GoogleProvider $googleProvider,
        protected XAIProvider $xaiProvider,
        protected DeepSeekProvider $deepSeekProvider,
    ) {
    }

    public function textProvider(?string $provider = null): TextGenerationProvider
    {
        $provider = $provider ?: (string) getSetting('ai.text_provider');

        return match ($provider) {
            'openai' => $this->openAIProvider,
            'ollama' => $this->ollamaProvider,
            'anthropic' => $this->anthropicProvider,
            'google' => $this->googleProvider,
            'xai' => $this->xaiProvider,
            'deepseek' => $this->deepSeekProvider,
            default => throw new InvalidArgumentException("Unsupported AI text provider [{$provider}]"),
        };
    }

    public function imageProvider(?string $provider = null): ImageGenerationProvider
    {
        $provider = $provider ?: (string) getSetting('ai.image_provider');

        return match ($provider) {
            'openai' => $this->openAIProvider,
            'google' => $this->googleProvider,
            'xai' => $this->xaiProvider,
            default => throw new InvalidArgumentException("Unsupported AI image provider [{$provider}]"),
        };
    }

    public function generateText(AiTextRequest $request, ?User $user = null, ?string $provider = null): string
    {
        return $this->textProvider($provider)->generateText($request, $user)->text;
    }

    public function generateImage(AiImageRequest $request, ?User $user = null, ?string $provider = null): string
    {
        return $this->imageProvider($provider)->generateImage($request, $user)->base64;
    }
}
