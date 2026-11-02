<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        foreach ($this->getSettings() as $spatieKey => $meta) {
            $voyagerKey = $meta['voyager_key'];
            $value = DB::table('settings')->where('key', $voyagerKey)->value('value');

            if (is_null($value)) {
                $value = $meta['default'] ?? null;
            }

            $this->migrator->add("emails.$spatieKey", $value);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->getSettings()) as $key) {
            $this->migrator->delete("emails.$key");
        }
    }

    protected function getSettings(): array
    {
        return [
            'driver' => [
                'voyager_key' => 'emails.driver',
                'default' => 'log',
            ],
            'from_name' => [
                'voyager_key' => 'emails.from_name',
                'default' => null,
            ],
            'from_address' => [
                'voyager_key' => 'emails.from_address',
                'default' => null,
            ],
            'mailgun_domain' => [
                'voyager_key' => 'emails.mailgun_domain',
                'default' => null,
            ],
            'mailgun_secret' => [
                'voyager_key' => 'emails.mailgun_secret',
                'default' => null,
            ],
            'mailgun_endpoint' => [
                'voyager_key' => 'emails.mailgun_endpoint',
                'default' => null,
            ],
            'smtp_host' => [
                'voyager_key' => 'emails.smtp_host',
                'default' => null,
            ],
            'smtp_port' => [
                'voyager_key' => 'emails.smtp_port',
                'default' => null,
            ],
            'smtp_encryption' => [
                'voyager_key' => 'emails.smtp_encryption',
                'default' => 'tls',
            ],
            'smtp_username' => [
                'voyager_key' => 'emails.smtp_username',
                'default' => null,
            ],
            'smtp_password' => [
                'voyager_key' => 'emails.smtp_password',
                'default' => null,
            ],
        ];
    }
};
