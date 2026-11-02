<?php

namespace App\Http\Middleware;

use App\Settings\SecuritySettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class FeatureRateLimit
{
    public function __construct(
        protected ?SecuritySettings $settings = null,
    ) {
        $this->settings ??= app(SecuritySettings::class);
    }

    public function handle(Request $request, Closure $next, string $feature)
    {
        if (!$this->settings->enable_feature_rate_limits) {
            return $next($request);
        }

        $settingPrefix = $this->getSettingPrefix($feature);

        if (!$settingPrefix) {
            return $next($request);
        }

        if (!(bool) ($this->settings->{$settingPrefix.'_enabled'} ?? false)) {
            return $next($request);
        }

        $maxAttempts = max(1, (int) ($this->settings->{$settingPrefix.'_max_attempts'} ?? 10));
        $decaySeconds = max(1, (int) ($this->settings->{$settingPrefix.'_decay_seconds'} ?? 60));
        $rateLimitKey = $this->getRateLimitKey($request, $feature);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            return $this->buildThrottleResponse($request, RateLimiter::availableIn($rateLimitKey));
        }

        RateLimiter::hit($rateLimitKey, $decaySeconds);

        return $next($request);
    }

    protected function getSettingPrefix(string $feature): ?string
    {
        return match ($feature) {
            'posts_save' => 'rate_limit_posts_save',
            'posts_comments_add' => 'rate_limit_posts_comments_add',
            'stories_store' => 'rate_limit_stories_store',
            'reels_store' => 'rate_limit_reels_store',
            'reels_comments_add' => 'rate_limit_reels_comments_add',
            'streams_init' => 'rate_limit_streams_init',
            'stream_comments_add' => 'rate_limit_stream_comments_add',
            'suggestions_generate' => 'rate_limit_suggestions_generate',
            'profile_asset_generate' => 'rate_limit_profile_asset_generate',
            'messenger_send' => 'rate_limit_messenger_send',
            default => null,
        };
    }

    protected function getRateLimitKey(Request $request, string $feature): string
    {
        $userKey = $request->user()?->getAuthIdentifier();
        $identifier = $userKey ? 'user:'.$userKey : 'ip:'.$request->ip();

        return 'feature-rate-limit:'.$feature.':'.$identifier;
    }

    protected function buildThrottleResponse(Request $request, int $retryAfter)
    {
        $message = __('Too many attempts. Please try again later.');
        $retryAfter = (string) max(1, $retryAfter);

        if ($request->expectsJson() || $request->ajax()) {
            return response()
                ->json([
                    'success' => false,
                    'message' => $message,
                ], 429)
                ->header('Retry-After', $retryAfter);
        }

        return response($message, 429)->header('Retry-After', $retryAfter);
    }
}
