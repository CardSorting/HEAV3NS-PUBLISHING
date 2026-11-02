<?php

namespace App\Services\ManualPayments;

use App\Model\ManualPaymentMethod;
use App\Support\MigrationSchema;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** Import existing settings and saved destinations once; retries never reset an administrator's changes. */
class ManualPaymentDefaults
{
    /** Preserve the generic receipt uploader's capacity as ordinary configurable File fields. */
    public static function withReceiptFields(array $fields, int $minimum): array
    {
        $capacity = array_sum(array_map(fn ($field) => ($field['type'] ?? '') === 'file'
            ? (int) ($field['max_files'] ?? ManualPaymentFields::MAX_FILES) : 0, $fields));
        $requiredCapacity = max(1, $minimum);
        $keys = array_column($fields, 'key');
        $suffix = 0;
        while ($capacity < $requiredCapacity) {
            if (count($fields) >= ManualPaymentFields::MAX_FIELDS) {
                throw new \RuntimeException('A deposit method needs room for receipt File fields. Remove an unused request field and retry the upgrade.');
            }
            do {
                $key = 'payment_receipt'.($suffix ? '_'.$suffix : '');
                $suffix++;
            } while (in_array($key, $keys, true));
            $fields[] = [
                'key' => $key, 'label' => $capacity ? 'Additional payment receipts' : 'Payment receipt',
                'type' => 'file', 'required' => false,
                'help_text' => 'Attach your payment receipt or proof of transfer.',
                'allowed_extensions' => ManualPaymentFields::FILE_EXTENSIONS,
                'max_size_kb' => ManualPaymentFields::MAX_FILE_SIZE_KB, 'max_files' => ManualPaymentFields::MAX_FILES,
            ];
            $keys[] = $key;
            $capacity += ManualPaymentFields::MAX_FILES;
        }

        return $fields;
    }

    public static function seed(): void
    {
        $receiptMinimum = self::setting('offline_payments_minimum_attachments_required');
        if ($receiptMinimum === null || $receiptMinimum === '') {
            $receiptMinimum = 0;
        }
        $receiving = [];
        foreach ([
            'owner' => 'Account owner',
            'account_number' => 'Account number',
            'bank_name' => 'Bank name',
            'routing_number' => 'Routing number',
            'iban' => 'IBAN',
            'swift' => 'SWIFT/BIC',
        ] as $key => $label) {
            $value = self::setting('offline_payments_'.$key);
            if ($value !== null && (string) $value !== '') {
                $receiving[] = ['key' => $key, 'label' => $label, 'value' => (string) $value];
            }
        }

        ManualPaymentMethod::query()->firstOrCreate([
            'direction' => ManualPaymentMethod::DEPOSIT, 'key' => 'offline',
        ], [
            'name' => 'Bank transfer',
            'enabled' => (bool) self::setting('allow_manual_payments'),
            'instructions' => self::plainInstructions(self::setting('offline_payments_custom_message_box')),
            'receiving_details' => $receiving,
            'account_fields' => [],
            'request_fields' => self::withReceiptFields([self::depositNotesField((bool) self::setting('offline_payments_make_notes_field_mandatory'))], (int) $receiptMinimum),
            'submission_settings' => [
                'minimum_attachments' => $receiptMinimum,
            ],
            'sort_order' => 0,
        ]);

        self::seedPixDepositMethod();

        $enabled = self::configuredWithdrawalKeys();
        $methods = [];
        foreach (['bank_transfer' => 'Bank transfer', 'paypal' => 'PayPal', 'crypto' => 'Crypto', 'custom' => 'Custom', 'pix' => 'Pix'] as $key => $name) {
            $methods[$key] = self::seedWithdrawalMethod($key, $name, in_array($key, $enabled, true), count($methods));
        }

        self::backfillBankAccounts($methods['bank_transfer']);
        self::backfillRememberedDestinations($methods);
    }

    /** Add the Pix template to existing installations without changing administrator configuration. */
    public static function seedPixWithdrawalMethod(): void
    {
        $sortOrder = (int) ManualPaymentMethod::query()->where('direction', ManualPaymentMethod::WITHDRAWAL)->max('sort_order') + 1;
        self::seedWithdrawalMethod('pix', 'Pix', in_array('pix', self::configuredWithdrawalKeys(), true), $sortOrder);
    }

    /** Add a disabled deposit template without replacing a site's existing Pix configuration. */
    public static function seedPixDepositMethod(): void
    {
        if (ManualPaymentMethod::query()->where('direction', ManualPaymentMethod::DEPOSIT)
            ->where(fn ($query) => $query->where('key', 'pix')->orWhereRaw('LOWER(TRIM(name)) = ?', ['pix']))
            ->exists()) {
            return;
        }

        ManualPaymentMethod::query()->firstOrCreate([
            'direction' => ManualPaymentMethod::DEPOSIT, 'key' => 'pix',
        ], [
            'name' => 'Pix',
            'enabled' => false,
            'instructions' => 'Pay using the Pix key, Pix Copia e Cola, or reusable QR code below. Enter the deposit amount in your banking app, then attach your payment receipt and submit for manual review. Your wallet is credited after approval.',
            'receiving_details' => [
                ['key' => 'recipient_name', 'label' => 'Recipient name', 'type' => 'text', 'value' => ''],
                ['key' => 'pix_key', 'label' => 'Pix key', 'type' => 'text', 'value' => ''],
                ['key' => 'pix_qr', 'label' => 'Payment QR', 'type' => 'image', 'value' => ''],
                ['key' => 'pix_copy_paste', 'label' => 'Pix Copia e Cola', 'type' => 'text', 'value' => ''],
            ],
            'account_fields' => [],
            'request_fields' => self::withReceiptFields([self::depositNotesField()], 1),
            'submission_settings' => ['minimum_attachments' => 1],
            'sort_order' => (int) ManualPaymentMethod::query()->where('direction', ManualPaymentMethod::DEPOSIT)->max('sort_order') + 1,
        ]);
    }

    public static function depositNotesField(bool $required = false): array
    {
        return ['key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => $required, 'max_length' => ManualPaymentFields::MAX_LENGTH];
    }

    private static function seedWithdrawalMethod(string $key, string $name, bool $enabled, int $sortOrder): ManualPaymentMethod
    {
        return ManualPaymentMethod::query()->firstOrCreate([
            'direction' => ManualPaymentMethod::WITHDRAWAL, 'key' => $key,
        ], [
            'name' => $name,
            'enabled' => $enabled,
            'instructions' => null,
            'receiving_details' => [],
            'account_fields' => self::accountFields($key),
            'request_fields' => [[
                'key' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'required' => false, 'max_length' => 5000,
            ]],
            'submission_settings' => [],
            'sort_order' => $sortOrder,
        ]);
    }

    public static function accountFields(string $key): array
    {
        $text = fn (string $key, string $label, bool $required = false, string $type = 'text', int $length = 255) => [
            'key' => $key, 'label' => $label, 'type' => $type, 'required' => $required, 'max_length' => $length,
        ];

        return match ($key) {
            'bank_transfer' => [
                $text('account_holder_name', 'Account holder', true),
                $text('iban', 'IBAN', true, 'text', 64),
                $text('swift_bic', 'SWIFT/BIC', false, 'text', 64),
                $text('bank_name', 'Bank name', true),
                $text('bank_address', 'Bank address', false, 'textarea', 1000),
                $text('country_name', 'Country'),
            ],
            'paypal' => [$text('email', 'PayPal email', true, 'email')],
            'crypto' => [$text('wallet_address', 'Wallet address', true)],
            'pix' => [
                $text('pix_key', 'Pix key', true),
                [
                    'key' => 'pix_key_type', 'label' => 'Pix key type', 'type' => 'select', 'required' => true, 'max_length' => 32,
                    'options' => [
                        ['value' => 'cpf', 'label' => 'CPF'],
                        ['value' => 'cnpj', 'label' => 'CNPJ'],
                        ['value' => 'email', 'label' => 'Email'],
                        ['value' => 'phone', 'label' => 'Phone number'],
                        ['value' => 'random', 'label' => 'Random key'],
                    ],
                ],
                $text('account_holder_name', 'Account holder'),
            ],
            default => [$text('destination', 'Payout destination', true, 'textarea', 1000)],
        };
    }

    private static function plainInstructions(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = preg_replace('~</?(?:p|div|br|li|ul|ol)[^>]*>~i', "\n", (string) $value);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\n[\h]*\n(?:[\h]*\n)*/', "\n\n", $text));
    }

    private static function configuredWithdrawalKeys(): array
    {
        $configured = self::setting('withdrawal_payment_methods');
        $values = is_array($configured) ? $configured : explode(',', (string) $configured);
        $keys = [];
        foreach ($values as $value) {
            $normalized = str_replace(['-', ' '], '_', strtolower(trim((string) $value)));
            $key = match ($normalized) {
                'bank_transfer', 'bank', 'wire_transfer' => 'bank_transfer',
                'paypal', 'pay_pal' => 'paypal',
                'crypto', 'cryptocurrency' => 'crypto',
                'pix' => 'pix',
                'stripe_connect', 'stripe' => null,
                default => 'custom',
            };
            if ($key !== null && $normalized !== '') {
                $keys[] = $key;
            }
        }

        // This is the existing site's fallback when no manual withdrawal method has been configured.
        return $keys === [] ? ['custom'] : array_values(array_unique($keys));
    }

    private static function backfillBankAccounts(ManualPaymentMethod $method): void
    {
        if (!MigrationSchema::hasTable('user_payout_accounts') || !MigrationSchema::hasColumn('user_payout_accounts', 'method_key')) {
            return;
        }

        DB::table('user_payout_accounts')->where('method_key', 'bank_transfer')
            ->where(function ($query): void {
                $query->whereNull('manual_method_id')->orWhereNull('account_details');
            })->chunkById(200, function ($accounts) use ($method): void {
                foreach ($accounts as $account) {
                    $changes = [];
                    if ($account->manual_method_id === null) {
                        $changes['manual_method_id'] = $method->id;
                    }
                    if ($account->account_details === null) {
                        $details = [];
                        foreach (['account_holder_name', 'iban', 'swift_bic', 'bank_name', 'bank_address', 'country_id'] as $key) {
                            $value = $account->{$key} ?? null;
                            if ($value !== null) {
                                $details[$key] = (string) $value;
                            }
                        }
                        if (($account->country_id ?? null) && MigrationSchema::hasTable('countries')) {
                            $country = DB::table('countries')->where('id', $account->country_id)->value('name');
                            if ($country !== null) {
                                $details['country_name'] = (string) $country;
                            }
                        }
                        $changes['account_details'] = Crypt::encryptString(json_encode($details, JSON_THROW_ON_ERROR));
                    }
                    DB::table('user_payout_accounts')->where('id', $account->id)->update($changes);
                }
            });
    }

    private static function backfillRememberedDestinations(array $methods): void
    {
        if (!MigrationSchema::hasTable('users') || !MigrationSchema::hasColumn('users', 'settings') || !MigrationSchema::hasTable('user_payout_accounts')) {
            return;
        }

        DB::table('users')->select(['id', 'settings'])->whereNotNull('settings')
            ->chunkById(200, function ($users) use ($methods): void {
                foreach ($users as $user) {
                    $settings = json_decode($user->settings, true);
                    if (!is_array($settings)) {
                        continue;
                    }
                    foreach (['paypal' => 'email', 'crypto' => 'wallet_address', 'custom' => 'destination'] as $key => $field) {
                        $identifier = data_get($settings, 'withdrawal_payout_details.'.$key.'.identifier');
                        if (!is_string($identifier) || trim($identifier) === '') {
                            continue;
                        }

                        $method = $methods[$key];
                        $source = 'user_settings.withdrawal_payout_details.'.$key;
                        $existing = DB::table('user_payout_accounts')->where('user_id', $user->id)
                            ->where('manual_method_id', $method->id)->get(['extra_data']);
                        if ($existing->contains(fn ($account) => data_get(json_decode($account->extra_data ?? '{}', true), 'migration_source') === $source)) {
                            continue;
                        }

                        $isDefault = !DB::table('user_payout_accounts')->where('user_id', $user->id)
                            ->where('manual_method_id', $method->id)->where('is_active', true)->where('is_default', true)->exists();
                        $account = [
                            'user_id' => $user->id,
                            'manual_method_id' => $method->id,
                            'label' => 'Saved '.$method->name,
                            'is_default' => $isDefault,
                            'is_active' => true,
                            'account_details' => Crypt::encryptString(json_encode([$field => trim($identifier)], JSON_THROW_ON_ERROR)),
                            'extra_data' => json_encode(['migration_source' => $source], JSON_THROW_ON_ERROR),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        if (MigrationSchema::hasColumn('user_payout_accounts', 'method_key')) {
                            $account['method_key'] = $method->key;
                        }
                        DB::table('user_payout_accounts')->insert($account);
                    }
                }
            });
    }

    private static function setting(string $name): mixed
    {
        $repository = config('settings.default_repository', 'database');
        $configured = config('settings.repositories.'.$repository.'.table', 'settings');
        foreach (array_unique([$configured, 'settings', 'settings_new']) as $table) {
            if (!is_string($table) || !MigrationSchema::hasTable($table)) {
                continue;
            }
            if (MigrationSchema::hasColumn($table, 'group') && MigrationSchema::hasColumn($table, 'payload')) {
                $row = DB::table($table)->where('group', 'payments')->where('name', $name)->first(['payload']);
                if ($row) {
                    return json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
                }
            } elseif (MigrationSchema::hasColumn($table, 'key') && MigrationSchema::hasColumn($table, 'value')) {
                $row = DB::table($table)->where('key', 'payments.'.$name)->first(['value']);
                if ($row) {
                    return $row->value;
                }
            }
        }

        return null;
    }
}
