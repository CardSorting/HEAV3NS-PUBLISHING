<?php

namespace App\Services\Ai;

use App\Model\User;
use Illuminate\Support\Facades\Log;

class AiSupportService
{
    public function getUserAiPrefs(User $user): array
    {
        $ai = (array) data_get($user, 'settings.ai', []);

        return array_merge([
            'tone' => 'neutral',
            'length' => 'short',
            'share_profile' => false,
            'traits' => [],
        ], $ai);
    }

    public function augmentTextPromptForUser(string $prompt, User $user, string $aiType = ''): string
    {
        $prefs = $this->getUserAiPrefs($user);

        $rules = [];
        $rules[] = __('ai.prompt.tone', ['tone' => (string) $prefs['tone']]);

        $rules[] = ($prefs['length'] ?? 'short') === 'short'
            ? __('ai.prompt.length_short')
            : __('ai.prompt.length_medium');

        $rules[] = __('ai.prompt.avoid_prices');
        $rules[] = __('ai.prompt.avoid_links');

        $hashtagsEnabled = (bool) getSetting('feed.enable_hashtags');

        if ($aiType === 'post' && $hashtagsEnabled) {
            $rules[] = __('ai.prompt.allow_hashtags');
        } else {
            $rules[] = __('ai.prompt.avoid_hashtags');
        }

        $traits = (array) ($prefs['traits'] ?? []);
        if (!empty($traits)) {
            $rules[] = __('ai.prompt.keywords', ['keywords' => implode(', ', $traits)]);
        }

        $profile = [];
        if (!empty($prefs['share_profile'])) {
            if (!empty($user->name)) {
                $profile[] = __('ai.prompt.profile_name', ['name' => $user->name]);
            }

            if (!empty($user->gender_pronoun)) {
                $profile[] = __('ai.prompt.profile_pronouns', ['pronouns' => $user->gender_pronoun]);
            }

            if (!empty($user->location)) {
                $profile[] = __('ai.prompt.profile_location', ['location' => $user->location]);
            }
        }

        $out = rtrim($prompt);

        if ($aiType !== '') {
            $out .= "\n\n".__('ai.prompt.context', ['type' => $aiType]);
        }

        $out .= "\n\n".__('ai.prompt.rules_label').' '.implode(' ', $rules);

        if ($profile) {
            $out .= "\n".__('ai.prompt.profile_label').' '.implode(' ', $profile);
        }

        return $out;
    }

    public function augmentImagePromptForUser(string $prompt, User $user, string $assetType = ''): string
    {
        $prefs = $this->getUserAiPrefs($user);

        $rules = [];
        $rules[] = __('ai.prompt.mood', ['tone' => (string) $prefs['tone']]);

        $traits = (array) ($prefs['traits'] ?? []);
        if (!empty($traits)) {
            $rules[] = __('ai.prompt.keywords', ['keywords' => implode(', ', $traits)]);
        }

        if ($assetType === 'avatar' && !empty($prefs['share_profile'])) {
            $rules[] = __('ai.prompt.adult_subject');
        }

        return rtrim($prompt)."\n\n".__('ai.prompt.rules_label').' '.implode(' ', $rules);
    }

    public function logPrompt(string $kind, string $provider, string $model, string $prompt, ?int $userId = null, array $meta = []): void
    {
        if (!config('ai.log_prompts')) {
            return;
        }

        $mode = config('ai.log_prompts_mode', 'hash'); // hash|full|preview

        $payload = [
            'kind' => $kind,
            'provider' => $provider,
            'model' => $model,
            'user_id' => $userId,
            'meta' => $meta,
            'prompt_len' => mb_strlen($prompt),
        ];

        if ($mode === 'full') {
            $payload['prompt'] = $prompt;
        } else {
            $payload['prompt_sha256'] = hash('sha256', $prompt);

            if (in_array($mode, ['hash', 'preview'], true)) {
                $payload['prompt_preview'] = mb_substr(
                    preg_replace('/\s+/', ' ', $prompt),
                    0,
                    400
                );
            }
        }

        Log::channel('ai')->debug('AI prompt', $payload);

    }

    public function normalizeOutput(string $text): string
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
