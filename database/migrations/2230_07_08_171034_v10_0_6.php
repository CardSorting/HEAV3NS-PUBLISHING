<?php

use App\Model\User;
use Illuminate\Database\Schema\Blueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Illuminate\Support\Facades\Schema;

class V1006 extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->rename('site.allow_pwa_installs', 'site.pwa_enabled');

        $this->migrator->add('site.pwa_theme_color', '#FFF');
        $this->migrator->add('site.pwa_background_color', '#FFF');
        $this->migrator->add('site.pwa_install_prompt_enabled', true);
        $this->migrator->add('site.pwa_icon', null);
        $this->migrator->add('site.pwa_splash_logo', null);

        $this->migrator->add('profiles.push_notifications_enabled', false);
        $this->migrator->add('profiles.webpush_contact_email', '');
        $this->migrator->add('profiles.webpush_public_key', '');
        $this->migrator->add('profiles.webpush_private_key', '');

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('endpoint', 191)->unique();
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding')->nullable();

            $table->string('user_agent')->nullable();
            $table->string('device_key')->nullable();

            $table->timestamps();
        });

        $this->migrator->add('profiles.enable_toast_notification_setting', true);

        // Default theme colors
        $this->migrator->update('colors.theme_color_code', fn ($value) => blank($value) ? '#cb0c9f' : $value);
        $this->migrator->update('colors.theme_gradient_from', fn ($value) => blank($value) ? '#7928CA' : $value);
        $this->migrator->update('colors.theme_gradient_to', fn ($value) => blank($value) ? '#FF0080' : $value);


        // Existing users: add the new default if missing
        User::query()
            ->select(['id', 'settings'])
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $settings = is_array($user->settings) ? $user->settings : [];

                    $settings['notification_toast_enabled'] = 'true';

                    User::query()
                        ->where('id', $user->id)
                        ->update([
                            'settings' => $settings,
                        ]);
                }
            });

        // AI Updates
        $this->migrator->rename('ai.open_ai_text_enabled', 'ai.text_enabled');
        $this->migrator->rename('ai.open_ai_images_enabled', 'ai.images_enabled');
        $this->migrator->rename('ai.open_ai_model', 'ai.text_model');
        $this->migrator->rename('ai.open_ai_image_model', 'ai.image_model');
        $this->migrator->rename('ai.open_ai_completion_max_tokens', 'ai.text_max_tokens');
        $this->migrator->rename('ai.open_ai_completion_temperature', 'ai.text_temperature');
        $this->migrator->rename('ai.open_ai_api_key', 'ai.openai_api_key');

        $this->migrator->add('ai.text_provider', 'openai');
        $this->migrator->add('ai.image_provider', 'openai');

        $this->migrator->add('ai.openai_base_url', 'https://api.openai.com/v1');

        $this->migrator->add('ai.ollama_base_url', 'http://127.0.0.1:11434');

        $this->migrator->add('ai.anthropic_api_key', null);
        $this->migrator->add('ai.anthropic_base_url', 'https://api.anthropic.com');

        $this->migrator->add('ai.google_api_key', null);
        $this->migrator->add('ai.google_base_url', 'https://generativelanguage.googleapis.com');

        $this->migrator->add('ai.xai_api_key', null);
        $this->migrator->add('ai.xai_base_url', 'https://api.x.ai/v1');

    }

    public function down(): void
    {
        $this->migrator->rename('site.pwa_enabled', 'site.allow_pwa_installs');

        $this->migrator->delete('site.pwa_theme_color');
        $this->migrator->delete('site.pwa_background_color');
        $this->migrator->delete('site.pwa_install_prompt_enabled');
        $this->migrator->delete('site.pwa_icon');
        $this->migrator->delete('site.pwa_splash_logo');
        Schema::dropIfExists('push_subscriptions');

        $this->migrator->delete('profiles.push_notifications_enabled');
        $this->migrator->delete('profiles.webpush_contact_email');
        $this->migrator->delete('profiles.webpush_public_key');
        $this->migrator->delete('profiles.webpush_private_key');

        $this->migrator->delete('profiles.enable_toast_notification_setting');

        // AI updates
        $this->migrator->rename('ai.text_enabled', 'ai.open_ai_text_enabled');
        $this->migrator->rename('ai.images_enabled', 'ai.open_ai_images_enabled');
        $this->migrator->rename('ai.text_model', 'ai.open_ai_model');
        $this->migrator->rename('ai.image_model', 'ai.open_ai_image_model');
        $this->migrator->rename('ai.text_max_tokens', 'ai.open_ai_completion_max_tokens');
        $this->migrator->rename('ai.text_temperature', 'ai.open_ai_completion_temperature');
        $this->migrator->rename('ai.openai_api_key', 'ai.open_ai_api_key');

        $this->migrator->delete('ai.text_provider');
        $this->migrator->delete('ai.image_provider');

        $this->migrator->delete('ai.openai_base_url');

        $this->migrator->delete('ai.ollama_base_url');

        $this->migrator->delete('ai.anthropic_api_key');
        $this->migrator->delete('ai.anthropic_base_url');

        $this->migrator->delete('ai.google_api_key');
        $this->migrator->delete('ai.google_base_url');

        $this->migrator->delete('ai.xai_api_key');
        $this->migrator->delete('ai.xai_base_url');

    }
}
