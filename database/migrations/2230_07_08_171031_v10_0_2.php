<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V1002 extends SettingsMigration
{
    public function up(): void
    {
        # Multi-language public pages
        $table = 'public_pages';
        $fallback = config('app.fallback_locale', 'en');

        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        // Ensure columns are LONGTEXT (MySQL-friendly, no JSON type)
        DB::statement("ALTER TABLE `{$table}` MODIFY `title` LONGTEXT NULL");
        DB::statement("ALTER TABLE `{$table}` MODIFY `short_title` LONGTEXT NULL");
        DB::statement("ALTER TABLE `{$table}` MODIFY `content` LONGTEXT NULL");

        // Convert plain strings -> JSON strings: {"en":"..."}
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $fallback) {
            foreach ($rows as $row) {
                $updates = [];

                foreach (['title', 'short_title', 'content'] as $field) {
                    $current = $row->{$field};
                    $currentString = is_null($current) ? '' : (string) $current;

                    // If already valid JSON array/object, assume migrated
                    if ($this->isJsonArrayOrObject($currentString)) {
                        continue;
                    }

                    $updates[$field] = json_encode(
                        [$fallback => $currentString],
                        JSON_UNESCAPED_UNICODE
                    );
                }

                if (!empty($updates)) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        });

        # Links security options
        $this->migrator->add('security.domain_policy', 'allow_all');
        $this->migrator->add('security.allowedlist_domains', []);
        $this->migrator->add('security.blocklist_domains', []);

    }

    public function down(): void
    {
        # Multi-language public pages
        $table = 'public_pages';
        $fallback = config('app.fallback_locale', 'en');

        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        // 1) Convert JSON strings back to plain strings (fallback locale)
        DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $fallback) {
            foreach ($rows as $row) {
                $updates = [];

                foreach (['title', 'short_title', 'content'] as $field) {
                    $current = $row->{$field};
                    $currentString = is_null($current) ? '' : (string) $current;

                    // If not JSON, leave as-is (already plain)
                    if (!$this->isJsonArrayOrObject($currentString)) {
                        continue;
                    }

                    $decoded = json_decode($currentString, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                        continue;
                    }

                    // Extract fallback locale; if missing, try first value; else empty
                    $plain = null;

                    if (array_key_exists($fallback, $decoded)) {
                        $plain = $decoded[$fallback];
                    } else {
                        // Try first scalar value in the map (best-effort)
                        foreach ($decoded as $v) {
                            if (is_scalar($v) || is_null($v)) {
                                $plain = $v;
                                break;
                            }
                        }
                    }

                    $updates[$field] = is_null($plain) ? '' : (string) $plain;
                }

                if (!empty($updates)) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        });

        // 2) Alter columns back to non-i18n-friendly plain types
        // Adjust these if your original schema differs.
        DB::statement("ALTER TABLE `{$table}` MODIFY `title` VARCHAR(191) NULL");
        DB::statement("ALTER TABLE `{$table}` MODIFY `short_title` VARCHAR(191) NULL");
        DB::statement("ALTER TABLE `{$table}` MODIFY `content` LONGTEXT NULL");

        # Links security options
        $this->migrator->delete('security.domain_policy');
        $this->migrator->delete('security.allowedlist_domains');
        $this->migrator->delete('security.blocklist_domains');

    }

    private function isJsonArrayOrObject(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }

        $first = $value[0];
        $last  = substr($value, -1);

        if (!(($first === '{' && $last === '}') || ($first === '[' && $last === ']'))) {
            return false;
        }

        json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        $decoded = json_decode($value, true);
        return is_array($decoded);
    }
}
