<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V1001 extends SettingsMigration
{
    /**
     * Rename $from -> $to without exploding if $to already exists.
     * Strategy: if both exist, delete the destination and rename the source over it.
     */
    private function safeRename(string $from, string $to): void
    {
        if (! $this->migrator->exists($from)) {
            return;
        }

        if ($this->migrator->exists($to)) {
            // choose ONE strategy:
            // A) prefer $from (overwrite destination):
            $this->migrator->delete($to);

            // B) prefer $to (keep destination, drop source):
            // $this->migrator->delete($from);
            // return;
        }

        $this->migrator->rename($from, $to);
    }

    public function up(): void
    {
        // Use addIfNotExists behavior to avoid "already exists" on re-runs
        if (! $this->migrator->exists('profiles.social_links_enabled')) {
            $this->migrator->add('profiles.social_links_enabled', false);
        }
        if (! $this->migrator->exists('profiles.allowed_social_network_keys')) {
            $this->migrator->add('profiles.allowed_social_network_keys', []);
        }

        // Referrals2Payments
        $referralsMap = [
            'referrals.enabled' => 'payments.referrals_enabled',
            'referrals.fee_percentage' => 'payments.referrals_fee_percentage',
            'referrals.apply_for_months' => 'payments.referrals_apply_for_months',
            'referrals.fee_limit' => 'payments.referrals_fee_limit',
            'referrals.referrals_default_link_page' => 'payments.referrals_default_link_page',
            'referrals.disable_for_non_verified' => 'payments.referrals_disable_for_non_verified',
            'referrals.auto_follow_the_user' => 'payments.referrals_auto_follow_the_user',
        ];

        foreach ($referralsMap as $from => $to) {
            $this->safeRename($from, $to);
        }

        // SocialLinks2General
        $socialMap = [
            'social.facebook_url'   => 'site.social_facebook_url',
            'social.instagram_url'  => 'site.social_instagram_url',
            'social.twitter_url'    => 'site.social_twitter_url',
            'social.whatsapp_url'   => 'site.social_whatsapp_url',
            'social.tiktok_url'     => 'site.social_tiktok_url',
            'social.youtube_url'    => 'site.social_youtube_url',
            'social.telegram_link'  => 'site.social_telegram_link',
            'social.reddit_url'     => 'site.social_reddit_url',
        ];

        foreach ($socialMap as $from => $to) {
            $this->safeRename($from, $to);
        }

        $map = [
            'social.facebook_client_id' => 'profiles.social_auth_facebook_client_id',
            'social.facebook_secret'    => 'profiles.social_auth_facebook_secret',
            'social.twitter_client_id'  => 'profiles.social_auth_twitter_client_id',
            'social.twitter_secret'     => 'profiles.social_auth_twitter_secret',
            'social.google_client_id'   => 'profiles.social_auth_google_client_id',
            'social.google_secret'      => 'profiles.social_auth_google_secret',
        ];

        foreach ($map as $from => $to) {
            $this->safeRename($from, $to);
        }

        $map = [
            'code-and-ads.custom_css'     => 'site.custom_code_css',
            'code-and-ads.custom_js'      => 'site.custom_code_js',
            'code-and-ads.sidebar_ad_spot'=> 'site.ads_sidebar_spot',
        ];

        foreach ($map as $from => $to) {
            $this->safeRename($from, $to);
        }

        $this->migrator->add('ai.open_ai_image_model', null);
        $this->migrator->delete('ai.open_ai_enabled');
        $this->migrator->add('ai.open_ai_text_enabled', false);    // default ON (text suggestions)
        $this->migrator->add('ai.open_ai_images_enabled', false); // default OFF (image generation)

    }

    public function down(): void
    {
        if ($this->migrator->exists('profiles.social_links_enabled')) {
            $this->migrator->delete('profiles.social_links_enabled');
        }
        if ($this->migrator->exists('profiles.allowed_social_network_keys')) {
            $this->migrator->delete('profiles.allowed_social_network_keys');
        }

        // Payments2Referrals
        $referralsMap = [
            'payments.referrals_enabled' => 'referrals.enabled',
            'payments.referrals_fee_percentage' => 'referrals.fee_percentage',
            'payments.referrals_apply_for_months' => 'referrals.apply_for_months',
            'payments.referrals_fee_limit' => 'referrals.fee_limit',
            'payments.referrals_default_link_page' => 'referrals.referrals_default_link_page',
            'payments.referrals_disable_for_non_verified' => 'referrals.disable_for_non_verified',
            'payments.referrals_auto_follow_the_user' => 'referrals.auto_follow_the_user',
        ];

        foreach ($referralsMap as $from => $to) {
            $this->safeRename($from, $to);
        }

        // General2SocialLinks
        $socialMap = [
            'site.social_facebook_url'   => 'social.facebook_url',
            'site.social_instagram_url'  => 'social.instagram_url',
            'site.social_twitter_url'    => 'social.twitter_url',
            'site.social_whatsapp_url'   => 'social.whatsapp_url',
            'site.social_tiktok_url'     => 'social.tiktok_url',
            'site.social_youtube_url'    => 'social.youtube_url',
            'site.social_telegram_link'  => 'social.telegram_link',
            'site.social_reddit_url'     => 'social.reddit_url',
        ];

        foreach ($socialMap as $from => $to) {
            $this->safeRename($from, $to);
        }

        $map = [
            'profiles.social_auth_facebook_client_id' => 'social.facebook_client_id',
            'profiles.social_auth_facebook_secret'    => 'social.facebook_secret',
            'profiles.social_auth_twitter_client_id'  => 'social.twitter_client_id',
            'profiles.social_auth_twitter_secret'     => 'social.twitter_secret',
            'profiles.social_auth_google_client_id'   => 'social.google_client_id',
            'profiles.social_auth_google_secret'      => 'social.google_secret',
        ];

        foreach ($map as $from => $to) {
            $this->safeRename($from, $to);
        }

        $map = [
            'site.custom_code_css'     => 'code-and-ads.custom_css',
            'site.custom_code_js'      => 'code-and-ads.custom_js',
            'site.ads_sidebar_spot'    => 'code-and-ads.sidebar_ad_spot',
        ];

        foreach ($map as $from => $to) {
            $this->safeRename($from, $to);
        }

        $this->migrator->delete('ai.open_ai_image_model');
        $this->migrator->delete('ai.open_ai_text_enabled');
        $this->migrator->delete('ai.open_ai_images_enabled');
        $this->migrator->add('ai.open_ai_enabled', false);

    }
}
