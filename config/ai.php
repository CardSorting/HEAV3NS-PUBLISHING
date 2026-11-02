<?php

return [
    'log_prompts' => env('AI_LOG_PROMPTS', false),
    'log_prompts_mode' => env('AI_LOG_PROMPTS_MODE', 'hash'),

    'providers' => [
        'openai' => [
            'label' => 'OpenAI',
            'supports_text' => true,
            'supports_images' => true,
            'fallback_text_models' => [
                'gpt-5.4' => 'GPT-5.4',
                'gpt-5.4-mini' => 'GPT-5.4 mini',
                'gpt-5.4-nano' => 'GPT-5.4 nano',
            ],
            'fallback_image_models' => [
                'gpt-image-1' => 'GPT Image 1',
                'dall-e-3' => 'DALL·E 3',
            ],
        ],

        'ollama' => [
            'label' => 'Ollama',
            'supports_text' => true,
            'supports_images' => false,
            'fallback_text_models' => [
                'llama3.1' => 'Llama 3.1',
                'mistral' => 'Mistral',
            ],
            'fallback_image_models' => [],
        ],

        'anthropic' => [
            'label' => 'Anthropic',
            'supports_text' => true,
            'supports_images' => false,
            'fallback_text_models' => [
                'claude-sonnet-4-6' => 'Claude Sonnet 4.6',
                'claude-opus-4-6' => 'Claude Opus 4.6',
            ],
            'fallback_image_models' => [],
        ],

        'deepseek' => [
            'label' => 'DeepSeek',
            'supports_text' => true,
            'supports_images' => false,
            'fallback_text_models' => [
                'deepseek-v4-flash' => 'DeepSeek V4 Flash',
                'deepseek-v4-pro' => 'DeepSeek V4 Pro',
            ],
            'fallback_image_models' => [],
        ],

        'google' => [
            'label' => 'Google (Gemini)',
            'supports_text' => true,
            'supports_images' => true,
            'fallback_text_models' => [
                'gemini-3' => 'Gemini 3',
                'gemini-2.0-flash' => 'Gemini 2.0 Flash',
            ],
            'fallback_image_models' => [
                'gemini-2.5-flash-image' => 'Nano Banana',
                'gemini-3-pro-image-preview' => 'Nano Banana Pro',
                'gemini-3.1-flash-image-preview' => 'Nano Banana 2',
            ],
        ],

        'xai' => [
            'label' => 'xAI',
            'supports_text' => true,
            'supports_images' => true,
            'fallback_text_models' => [
                'grok-4.20' => 'Grok 4.20',
                'grok-4-fast' => 'Grok 4 Fast',
            ],
            'fallback_image_models' => [
                'grok-2-image' => 'Grok 2 Image',
            ],
        ],
    ],
];
