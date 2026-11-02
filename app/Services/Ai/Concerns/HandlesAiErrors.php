<?php

namespace App\Services\Ai\Concerns;

trait HandlesAiErrors
{
    protected function toPublicErrorMessage(string $feature, string $rawMessage): string
    {
        $message = mb_strtolower($rawMessage);

        if (str_contains($message, '503') || str_contains($message, 'high demand')) {
            return __('The AI provider is currently under heavy load. Please try again in a few moments.');
        }

        if (str_contains($message, '403') || str_contains($message, 'permission')) {
            return __('This AI provider is not currently available.');
        }

        if (str_contains($message, 'timeout')) {
            return __('The AI provider took too long to respond. Please try again.');
        }

        return match ($feature) {
            'text_generation' => __('Text generation failed. Please try again.'),
            'image_generation' => __('Image generation failed. Please try again.'),
            'asset_generation' => __('Asset generation failed. Please try again.'),
            default => __('The AI request failed. Please try again.'),
        };
    }
}
