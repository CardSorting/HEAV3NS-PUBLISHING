<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiManager;
use App\Services\Ai\AiSupportService;
use App\Services\Ai\Data\AiTextRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiController extends Controller
{
    public function generateSuggestion(
        Request $request,
        AiManager $aiManager,
        AiSupportService $aiSupport
    ) {
        abort_unless(getSetting('ai.text_enabled'), 404);

        $validated = $request->validate([
            'type' => ['required', Rule::in(['profile_bio', 'post', 'stream', 'story', 'reel'])],
        ]);

        $user = $request->user();

        $locale = data_get($user, 'locale')
            ?? data_get($user, 'settings.locale')
            ?? app()->getLocale();

        app()->setLocale($locale);

        $siteName = getSetting('site.name');
        $type = (string) $validated['type'];

        $key = match ($type) {
            'profile_bio' => 'ai.text.profile_bio',
            'post' => 'ai.text.post',
            'stream' => 'ai.text.stream',
            'story' => 'ai.text.story',
            'reel' => 'ai.text.reel',
            default => throw new \UnexpectedValueException('Invalid AI suggestion type.'),
        };

        $basePrompt = __($key, ['siteName' => $siteName]);

        $prompt = $basePrompt."\n\n".__('ai.prompt.no_explanations')."\n";
        $prompt = $aiSupport->augmentTextPromptForUser($prompt, $user, $type);

        $provider = (string) getSetting('ai.text_provider', 'openai');
        $model = (string) getSetting('ai.text_model');

        $aiSupport->logPrompt(
            'text',
            $provider,
            $model,
            $prompt,
            $user->id,
            ['type' => $type]
        );

        try {
            $text = $aiManager->generateText(
                new AiTextRequest(
                    prompt: $prompt,
                    model: $model,
                    temperature: (float) getSetting('ai.text_temperature', 1.0),
                    maxTokens: (int) getSetting('ai.text_max_tokens', 200),
                    meta: ['type' => $validated['type']]
                ),
                $user,
                $provider
            );

            return response()->json(['message' => $text]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
