<?php

use App\Model\ManualPaymentMethod;
use App\Services\ManualPayments\DepositReceiptMigration;
use App\Services\ManualPayments\ManualPaymentDefaults;
use App\Services\ManualPayments\ManualPaymentFields;
use App\Services\Media\AttachmentPlacementMigration;
use App\Support\MigrationSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

return new class extends SettingsMigration {
    private const LEGACY_COLUMNS = [
        'method_key', 'account_holder_name', 'iban', 'swift_bic', 'bank_name', 'bank_address', 'country_id',
    ];

    public $withinTransaction = false;

    public function up(): void
    {
        $this->addSettings();
        $this->createManualPaymentMethods();
        $this->normalizePayoutAccounts();
        $this->convertDepositNotes();
        $this->moveDepositDetails();
        $this->createManualPaymentDocuments();
        app(DepositReceiptMigration::class)->up();
        $this->grantSystemHealthPermission();
        $this->retireAttachmentOwnershipRepairs();
        $this->retireLegacySettings();

        $this->clearSettingsCache();
        if (Artisan::call('minify:clear', ['--type' => 'all']) !== 0) {
            throw new RuntimeException('The v12.0.0 upgrade was applied, but clearing stale frontend assets failed. Rerun the upgrade after resolving the cache error.');
        }
    }

    public function down(): void
    {
        throw new RuntimeException('The v12.0.0 upgrade contains financial configuration, payout destinations, and request evidence. Use a forward repair or restore a reviewed database and private-storage backup with its matching code.');
    }

    private function retireAttachmentOwnershipRepairs(): void
    {
        if (!$this->hasTable('attachment_ownership_repairs')) {
            return;
        }
        if ($this->hasTable('attachments')) {
            foreach (array_keys(AttachmentPlacementMigration::PARENTS) as $column) {
                if ($this->hasColumn('attachments', $column)) {
                    throw new RuntimeException('Complete the media placements migration before removing its obsolete ownership repair history.');
                }
            }
        }
        // Only historical audit rows are removed; current owners and media are unchanged.
        Schema::drop('attachment_ownership_repairs');
    }

    private function retireLegacySettings(): void
    {
        if (!$this->hasTable('settings_old')) {
            return;
        }
        if (config('settings.admin_version') !== 'v2'
            || !$this->hasColumns('settings', ['group', 'name', 'payload'])
            || !$this->hasColumns('settings_old', ['key', 'display_name', 'value', 'details', 'type', 'order', 'group'])) {
            throw new RuntimeException('Verify the Filament settings cutover before removing the legacy Voyager settings table.');
        }
        foreach (config('settings.repositories', []) as $repository) {
            $model = $repository['model'] ?? null;
            if (($repository['table'] ?? null) === 'settings_old' || ($model && (new $model)->getTable() === 'settings_old')) {
                throw new RuntimeException('A settings repository still uses settings_old. Update its configuration before retrying the upgrade.');
            }
        }
        // v9 kept this as a Voyager backup; active settings have lived in settings since cutover.
        Schema::drop('settings_old');
    }

    private function addSettings(): void
    {
        // Preserve choices from a previous attempt or the individual prerelease migrations.
        foreach ([
            'payments.billing_details_collection' => 'full',
            'payments.invoices_date_format' => 'Y-m-d',
            'admin.show_staff_badges' => true,
            'feed.mobile_suggestions_enabled' => true,
            'feed.youtube_embeds_enabled' => false,
            'compliance.delete_verification_assets_after_review' => false,
        ] as $setting => $default) {
            if (!$this->migrator->exists($setting)) {
                $this->migrator->add($setting, $default);
            }
        }
    }

    private function grantSystemHealthPermission(): void
    {
        if (!$this->hasTable('permissions') || !$this->hasTable('roles') || !$this->hasTable('role_has_permissions')) {
            return;
        }

        DB::table('permissions')->insertOrIgnore([
            'name' => 'View:SystemHealth', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->where('name', 'View:SystemHealth')->where('guard_name', 'web')->value('id');
        $adminId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');
        if ($adminId !== null) {
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $adminId, 'permission_id' => $permissionId]);
        }
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createManualPaymentMethods(): void
    {
        if (!$this->hasTable('manual_payment_methods')) {
            Schema::create('manual_payment_methods', function (Blueprint $table): void {
                $table->id();
                $table->string('direction', 16)->index();
                $table->string('key', 100);
                $table->string('name', 150);
                $table->boolean('enabled')->default(false);
                $table->text('instructions')->nullable();
                $table->longText('receiving_details')->nullable();
                $table->longText('account_fields')->nullable();
                $table->longText('request_fields')->nullable();
                $table->longText('submission_settings')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['direction', 'key']);
            });
        }

        foreach (['user_payout_accounts', 'withdrawals', 'transactions'] as $name) {
            if (!$this->hasTable($name)) {
                continue;
            }
            if (!$this->hasColumn($name, 'manual_method_id')) {
                Schema::table($name, function (Blueprint $table): void {
                    $table->foreignId('manual_method_id')->nullable()->constrained('manual_payment_methods')->restrictOnDelete();
                });
            }
            $columns = match ($name) {
                'user_payout_accounts' => ['account_details'],
                'withdrawals' => ['submitted_fields', 'details_snapshot'],
                default => [],
            };
            foreach ($columns as $column) {
                if (!$this->hasColumn($name, $column)) {
                    Schema::table($name, fn (Blueprint $table) => $table->longText($column)->nullable());
                }
            }
        }

        if ($this->hasTable('user_payout_accounts')) {
            // Generic destinations need not contain bank-specific details. Retain the original columns and data.
            Schema::table('user_payout_accounts', function (Blueprint $table): void {
                foreach (['account_holder_name' => 191, 'iban' => 64, 'bank_name' => 191] as $column => $length) {
                    if ($this->hasColumn('user_payout_accounts', $column)) {
                        $table->string($column, $length)->nullable()->change();
                    }
                }
            });
        }

        ManualPaymentDefaults::seed();
    }

    private function normalizePayoutAccounts(): void
    {
        $this->normalizeAccounts();

        $indexes = Schema::getIndexes('user_payout_accounts');
        $indexName = 'user_payout_accounts_user_id_manual_method_id_is_default_index';
        if (!in_array($indexName, array_column($indexes, 'name'), true)) {
            // This replaces the old composite index before it is dropped, preserving the user foreign key on MySQL.
            Schema::table('user_payout_accounts', fn (Blueprint $table) => $table->index(['user_id', 'manual_method_id', 'is_default'], $indexName));
        }

        foreach (Schema::getForeignKeys('user_payout_accounts') as $foreignKey) {
            if (array_intersect($foreignKey['columns'], self::LEGACY_COLUMNS)) {
                Schema::table('user_payout_accounts', fn (Blueprint $table) => $table->dropForeign($foreignKey['name'] ?? $foreignKey['columns']));
            }
        }
        foreach ($indexes as $index) {
            if (!$index['primary'] && array_intersect($index['columns'], self::LEGACY_COLUMNS)) {
                Schema::table('user_payout_accounts', fn (Blueprint $table) => $table->dropIndex($index['name']));
            }
        }
        $legacyColumns = array_values(array_filter(self::LEGACY_COLUMNS, fn ($column) => $this->hasColumn('user_payout_accounts', $column)));
        if ($legacyColumns !== []) {
            Schema::table('user_payout_accounts', fn (Blueprint $table) => $table->dropColumn($legacyColumns));
        }
        Schema::table('user_payout_accounts', function (Blueprint $table): void {
            $table->unsignedBigInteger('manual_method_id')->nullable(false)->change();
            $table->longText('account_details')->nullable(false)->change();
        });
    }

    private function normalizeAccounts(): void
    {
        DB::transaction(function (): void {
            DB::table('user_payout_accounts')->orderBy('id')->lockForUpdate()->chunkById(200, function ($accounts): void {
                foreach ($accounts as $account) {
                    $method = $this->methodFor($account);
                    if (!$method || $method->direction !== 'withdrawal') {
                        throw new RuntimeException("Payout account {$account->id} has no valid withdrawal method. Repair its method reference before retrying the migration.");
                    }

                    $details = $account->account_details !== null
                        ? json_decode(Crypt::decryptString($account->account_details), true, 512, JSON_THROW_ON_ERROR)
                        : [];
                    if (!is_array($details)) {
                        throw new RuntimeException("Payout account {$account->id} contains invalid encrypted account details.");
                    }

                    $legacy = [];
                    foreach (array_diff(self::LEGACY_COLUMNS, ['method_key']) as $key) {
                        $value = $account->{$key} ?? null;
                        if ($value !== null && (string) $value !== '') {
                            $legacy[$key] = (string) $value;
                        }
                    }
                    if (isset($legacy['country_id']) && $this->hasTable('countries')) {
                        $country = DB::table('countries')->where('id', $legacy['country_id'])->value('name');
                        if ($country !== null) {
                            $legacy['country_name'] = $country;
                        }
                    }
                    $normalized = array_replace($legacy, $details);
                    $changes = [];
                    if ((int) ($account->manual_method_id ?? 0) !== (int) $method->id) {
                        $changes['manual_method_id'] = $method->id;
                    }
                    if ($account->account_details === null || $normalized !== $details) {
                        $changes['account_details'] = Crypt::encryptString(json_encode($normalized, JSON_THROW_ON_ERROR));
                    }
                    if (!$account->label && !empty($account->bank_name)) {
                        $changes['label'] = $account->bank_name;
                    }
                    if ($changes !== []) {
                        DB::table('user_payout_accounts')->where('id', $account->id)->update($changes);
                    }
                }
            });
        });
    }

    private function methodFor(object $account): ?object
    {
        $methods = DB::table('manual_payment_methods');
        if ($account->manual_method_id !== null) {
            return $methods->find($account->manual_method_id);
        }
        $key = $account->method_key ?? '';
        if (preg_match('/^manual_([1-9][0-9]*)$/D', $key, $matches)) {
            return $methods->find($matches[1]);
        }

        return $methods->where('direction', 'withdrawal')->where('key', $key)->first();
    }

    private function convertDepositNotes(): void
    {
        if (!$this->hasTable('manual_payment_methods')) {
            return;
        }

        DB::transaction(function (): void {
            ManualPaymentMethod::query()->where('direction', ManualPaymentMethod::DEPOSIT)->lockForUpdate()
                ->chunkById(100, function ($methods): void {
                    foreach ($methods as $method) {
                        $settings = $method->submission_settings ?? [];
                        // Removing this legacy key also makes retries preserve subsequent admin edits.
                        if (!array_key_exists('notes_required', $settings)) {
                            continue;
                        }

                        $fields = $method->request_fields ?? [];
                        $required = filter_var($settings['notes_required'], FILTER_VALIDATE_BOOLEAN);
                        $notesIndex = collect($fields)->search(fn (array $field) => in_array($field['type'] ?? '', ['text', 'textarea'], true)
                            && (($field['key'] ?? '') === 'notes' || mb_strtolower(trim($field['label'] ?? '')) === 'notes'));
                        if ($notesIndex !== false) {
                            $fields[$notesIndex]['required'] = $required || ($fields[$notesIndex]['required'] ?? false);
                        } else {
                            if (count($fields) >= ManualPaymentFields::MAX_FIELDS) {
                                throw new RuntimeException('Deposit method '.$method->id.' needs room for its migrated Notes field. Remove an unused request field and retry.');
                            }
                            $notes = ManualPaymentDefaults::depositNotesField($required);
                            $keys = array_column($fields, 'key');
                            $suffix = 1;
                            while (in_array($notes['key'], $keys, true)) {
                                $notes['key'] = 'payment_notes_'.$suffix++;
                            }
                            $fields[] = $notes;
                        }
                        unset($settings['notes_required']);
                        $method->update(['request_fields' => $fields, 'submission_settings' => $settings]);
                    }
                });
        });
    }

    private function moveDepositDetails(): void
    {
        $columns = ['submitted_fields', 'details_snapshot'];
        $missing = array_values(array_filter($columns, fn ($column) => !$this->hasColumn('payment_requests', $column)));
        if ($missing) {
            Schema::table('payment_requests', function (Blueprint $table) use ($missing): void {
                foreach ($missing as $column) {
                    $table->longText($column)->nullable();
                }
            });
        }

        $sourceColumns = array_values(array_filter($columns, fn ($column) => $this->hasColumn('transactions', $column)));
        if (!$sourceColumns) {
            return;
        }

        DB::transaction(function () use ($sourceColumns): void {
            DB::table('transactions')->select(['id', ...$sourceColumns])
                ->where(function ($query) use ($sourceColumns): void {
                    foreach ($sourceColumns as $column) {
                        $query->orWhereNotNull($column);
                    }
                })->lockForUpdate()->chunkById(100, function ($transactions) use ($sourceColumns): void {
                    $requests = DB::table('payment_requests')->whereIn('transaction_id', $transactions->pluck('id'))
                        ->where('type', 'deposit')->lockForUpdate()->get(['id', 'transaction_id', ...$sourceColumns])
                        ->groupBy('transaction_id');

                    foreach ($transactions as $transaction) {
                        $matches = $requests->get($transaction->id, collect());
                        if ($matches->count() !== 1) {
                            throw new RuntimeException('Transaction '.$transaction->id.' has stored deposit details but '.($matches->isEmpty() ? 'no matching deposit request' : 'multiple deposit requests').'. Repair its request relationship and retry; the transaction columns have been retained.');
                        }

                        $request = $matches->first();
                        $values = [];
                        foreach ($sourceColumns as $column) {
                            if ($transaction->{$column} === null) {
                                continue;
                            }
                            if ($request->{$column} !== null && $request->{$column} !== $transaction->{$column}) {
                                throw new RuntimeException('Deposit request '.$request->id.' has conflicting '.$column.'. Reconcile its stored details and retry; the transaction columns have been retained.');
                            }
                            // Copy ciphertext directly: no re-encryption, observer effects, or timestamp changes.
                            $values[$column] = $transaction->{$column};
                        }
                        DB::table('payment_requests')->where('id', $request->id)->update($values);
                    }
                });
        });

        // MySQL DDL commits implicitly, so only remove the source after the entire copy succeeds.
        Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn($sourceColumns));
    }

    private function createManualPaymentDocuments(): void
    {
        if (!$this->hasTable('manual_payment_documents')) {
            Schema::create('manual_payment_documents', function (Blueprint $table): void {
                $table->id();
                // Keep orphan records so scheduled cleanup can remove their private files.
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('manual_method_id')->nullable()->constrained('manual_payment_methods')->nullOnDelete();
                $table->foreignId('payment_request_id')->nullable()->constrained('payment_requests')->nullOnDelete();
                $table->foreignId('withdrawal_id')->nullable()->constrained('withdrawals')->nullOnDelete();
                $table->string('field_key', 64);
                $table->string('field_label', 150);
                $table->text('original_name');
                $table->string('path');
                $table->string('extension', 8);
                $table->string('mime_type', 100);
                $table->unsignedInteger('size');
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });
        }
        // Short names also support MySQL's 64-character identifier limit.
        foreach (['manual_docs_expiry' => ['submitted_at', 'created_at'], 'manual_docs_owner_field' => ['user_id', 'manual_method_id', 'field_key']] as $name => $columns) {
            if (!Schema::hasIndex('manual_payment_documents', $columns)) {
                Schema::table('manual_payment_documents', fn (Blueprint $table) => $table->index($columns, $name));
            }
        }
    }

    private function clearSettingsCache(): void
    {
        try {
            // The web installer cannot use Spatie's console-only settings command.
            if (app()->bound(SettingsCacheFactory::class)) {
                foreach (app(SettingsCacheFactory::class)->all() as $cache) {
                    $cache->clear();
                }

                return;
            }

            if (Artisan::call('settings:clear-cache') !== 0) {
                throw new RuntimeException('The settings cache could not be cleared.');
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('The v12.0.0 settings were updated, but clearing the settings cache failed. Rerun the upgrade after resolving the cache error.', 0, $exception);
        }
    }

    private function hasTable(string $table): bool
    {
        return MigrationSchema::hasTable($table);
    }

    private function hasColumns(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!$this->hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return MigrationSchema::hasColumn($table, $column);
    }
};
