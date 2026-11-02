<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V1110 extends SettingsMigration
{
    public function up(): void
    {
        $this->addSettingsIfMissing('storage', $this->getBackblazeStorageSettings());
        $this->addSettingsIfMissing('media', $this->getVoiceMessageSettings());
    }

    public function down(): void
    {
        $this->deleteSettings('media', array_keys($this->getVoiceMessageSettings()));
        $this->deleteSettings('storage', array_keys($this->getBackblazeStorageSettings()));
    }

    protected function addSettingsIfMissing(string $group, array $settings): void
    {
        foreach ($settings as $setting => $value) {
            $key = $group.'.'.$setting;

            if (! $this->migrator->exists($key)) {
                $this->migrator->add($key, $value);
            }
        }
    }

    protected function deleteSettings(string $group, array $settings): void
    {
        foreach ($settings as $setting) {
            $key = $group.'.'.$setting;

            if ($this->migrator->exists($key)) {
                $this->migrator->delete($key);
            }
        }
    }

    protected function getBackblazeStorageSettings(): array
    {
        return [
            'b2_access_key' => '',
            'b2_secret_key' => '',
            'b2_bucket_name' => '',
            'b2_endpoint' => '',
            'b2_region' => '',
            'b2_custom_url' => '',
        ];
    }

    protected function getVoiceMessageSettings(): array
    {
        return [
            'voice_messages_enabled' => true,
        ];
    }
}
