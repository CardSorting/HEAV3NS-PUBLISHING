<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AISettings extends Settings
{
    // Generic feature toggles
    public bool $text_enabled = false;

    public bool $images_enabled = false;

    // Generic runtime routing
    public ?string $text_provider = 'openai';

    public ?string $image_provider = 'openai';

    public ?string $text_model = null;

    public ?string $image_model = null;

    public ?int $text_max_tokens = 200;

    public ?float $text_temperature = 1.0;

    // OpenAI
    public ?string $openai_api_key = null;

    public ?string $openai_base_url = 'https://api.openai.com/v1';

    // Ollama
    public ?string $ollama_base_url = 'http://127.0.0.1:11434';

    // Anthropic
    public ?string $anthropic_api_key = null;

    public ?string $anthropic_base_url = 'https://api.anthropic.com';

    // DeepSeek
    public ?string $deepseek_api_key = null;

    public ?string $deepseek_base_url = 'https://api.deepseek.com';

    // Google Gemini
    public ?string $google_api_key = null;

    public ?string $google_base_url = 'https://generativelanguage.googleapis.com';

    // xAI / Grok
    public ?string $xai_api_key = null;

    public ?string $xai_base_url = 'https://api.x.ai/v1';

    public static function group(): string
    {
        return 'ai';
    }
}
