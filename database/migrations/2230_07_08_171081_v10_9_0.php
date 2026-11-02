<?php

use Illuminate\Database\Schema\Blueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V1090 extends SettingsMigration
{
    public function up(): void
    {
        foreach ($this->coinbaseSettings() as $key) {
            $this->deleteSettingIfExists($key);
        }

        foreach ($this->yookassaSettings() as $key => $default) {
            $this->addSettingIfMissing($key, $default);
        }

        foreach ($this->mollieSettings() as $key => $default) {
            $this->addSettingIfMissing($key, $default);
        }

        foreach ($this->flutterwaveSettings() as $key => $default) {
            $this->addSettingIfMissing($key, $default);
        }

        foreach ($this->coingateSettings() as $key => $default) {
            $this->addSettingIfMissing($key, $default);
        }

        foreach ($this->xenditSettings() as $key => $default) {
            $this->addSettingIfMissing($key, $default);
        }

        foreach ($this->paddleSettings() as $key => $default) {
            $this->addSettingIfMissing($key, $default);
        }

        foreach ($this->cryptocomSettings() as $key => $default) {
            $this->addSettingIfMissing($key, $default);
        }

        foreach ($this->ageGateSettings() as $key => $default) {
            if (! $this->migrator->exists($key)) {
                $this->migrator->add($key, $default);
            }
        }

        if (! $this->migrator->exists('profiles.profile_monetization_mode')) {
            $this->migrator->add('profiles.profile_monetization_mode', 'mixed');
        }

        foreach ($this->deepSeekSettings() as $key => $default) {
            if (! $this->migrator->exists($key)) {
                $this->migrator->add($key, $default);
            }
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (! $this->columnExists('transactions', 'yookassa_payment_id')) {
                    $table->string('yookassa_payment_id')->nullable()->after('razorpay_payment_id');
                }

                if (! $this->columnExists('transactions', 'yookassa_payment_token')) {
                    $table->string('yookassa_payment_token')->nullable()->after('yookassa_payment_id');
                }

                if (! $this->columnExists('transactions', 'xendit_payment_id')) {
                    $table->string('xendit_payment_id')->nullable()->after('yookassa_payment_token');
                }

                if (! $this->columnExists('transactions', 'xendit_payment_token')) {
                    $table->string('xendit_payment_token')->nullable()->after('xendit_payment_id');
                }

                if (! $this->columnExists('transactions', 'mollie_payment_id')) {
                    $table->string('mollie_payment_id')->nullable()->after('xendit_payment_token');
                }

                if (! $this->columnExists('transactions', 'mollie_payment_token')) {
                    $table->string('mollie_payment_token')->nullable()->after('mollie_payment_id');
                }

                if (! $this->columnExists('transactions', 'flutterwave_payment_id')) {
                    $table->string('flutterwave_payment_id')->nullable()->after('mollie_payment_token');
                }

                if (! $this->columnExists('transactions', 'flutterwave_payment_token')) {
                    $table->string('flutterwave_payment_token')->nullable()->after('flutterwave_payment_id');
                }

                if (! $this->columnExists('transactions', 'coingate_order_id')) {
                    $table->string('coingate_order_id')->nullable()->after('flutterwave_payment_token');
                }

                if (! $this->columnExists('transactions', 'coingate_payment_token')) {
                    $table->string('coingate_payment_token')->nullable()->after('coingate_order_id');
                }

                if (! $this->columnExists('transactions', 'paddle_transaction_id')) {
                    $table->string('paddle_transaction_id')->nullable()->after('coingate_payment_token');
                }

                if (! $this->columnExists('transactions', 'paddle_transaction_token')) {
                    $table->string('paddle_transaction_token')->nullable()->after('paddle_transaction_id');
                }

                if (! $this->columnExists('transactions', 'cryptocom_payment_id')) {
                    $table->string('cryptocom_payment_id')->nullable()->after('paddle_transaction_token');
                }

                if (! $this->columnExists('transactions', 'cryptocom_payment_token')) {
                    $table->string('cryptocom_payment_token')->nullable()->after('cryptocom_payment_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->yookassaSettings()) as $key) {
            $this->deleteSettingIfExists($key);
        }

        foreach (array_keys($this->mollieSettings()) as $key) {
            $this->deleteSettingIfExists($key);
        }

        foreach (array_keys($this->flutterwaveSettings()) as $key) {
            $this->deleteSettingIfExists($key);
        }

        foreach (array_keys($this->coingateSettings()) as $key) {
            $this->deleteSettingIfExists($key);
        }

        foreach (array_keys($this->xenditSettings()) as $key) {
            $this->deleteSettingIfExists($key);
        }

        foreach (array_keys($this->paddleSettings()) as $key) {
            $this->deleteSettingIfExists($key);
        }

        foreach (array_keys($this->cryptocomSettings()) as $key) {
            $this->deleteSettingIfExists($key);
        }

        foreach (array_keys($this->ageGateSettings()) as $key) {
            if ($this->migrator->exists($key)) {
                $this->migrator->delete($key);
            }
        }

        if ($this->migrator->exists('profiles.profile_monetization_mode')) {
            $this->migrator->delete('profiles.profile_monetization_mode');
        }

        foreach (array_keys($this->deepSeekSettings()) as $key) {
            if ($this->migrator->exists($key)) {
                $this->migrator->delete($key);
            }
        }

        foreach ($this->coinbaseSettings() as $key) {
            $this->addSettingIfMissing($key, null);
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if ($this->columnExists('transactions', 'cryptocom_payment_token')) {
                    $table->dropColumn('cryptocom_payment_token');
                }

                if ($this->columnExists('transactions', 'cryptocom_payment_id')) {
                    $table->dropColumn('cryptocom_payment_id');
                }

                if ($this->columnExists('transactions', 'paddle_transaction_token')) {
                    $table->dropColumn('paddle_transaction_token');
                }

                if ($this->columnExists('transactions', 'paddle_transaction_id')) {
                    $table->dropColumn('paddle_transaction_id');
                }

                if ($this->columnExists('transactions', 'coingate_payment_token')) {
                    $table->dropColumn('coingate_payment_token');
                }

                if ($this->columnExists('transactions', 'coingate_order_id')) {
                    $table->dropColumn('coingate_order_id');
                }

                if ($this->columnExists('transactions', 'flutterwave_payment_token')) {
                    $table->dropColumn('flutterwave_payment_token');
                }

                if ($this->columnExists('transactions', 'flutterwave_payment_id')) {
                    $table->dropColumn('flutterwave_payment_id');
                }

                if ($this->columnExists('transactions', 'mollie_payment_token')) {
                    $table->dropColumn('mollie_payment_token');
                }

                if ($this->columnExists('transactions', 'mollie_payment_id')) {
                    $table->dropColumn('mollie_payment_id');
                }

                if ($this->columnExists('transactions', 'xendit_payment_token')) {
                    $table->dropColumn('xendit_payment_token');
                }

                if ($this->columnExists('transactions', 'xendit_payment_id')) {
                    $table->dropColumn('xendit_payment_id');
                }

                if ($this->columnExists('transactions', 'yookassa_payment_token')) {
                    $table->dropColumn('yookassa_payment_token');
                }

                if ($this->columnExists('transactions', 'yookassa_payment_id')) {
                    $table->dropColumn('yookassa_payment_id');
                }
            });
        }
    }

    protected function addSettingIfMissing(string $key, mixed $default): void
    {
        if (! $this->migrator->exists($key)) {
            $this->migrator->add($key, $default);
        }
    }

    protected function deleteSettingIfExists(string $key): void
    {
        if ($this->migrator->exists($key)) {
            $this->migrator->delete($key);
        }
    }

    protected function columnExists(string $table, string $column): bool
    {
        $connection = DB::connection();

        return ! empty($connection->select(
            'select column_name from information_schema.columns where table_schema = database() and table_name = ? and column_name = ? limit 1',
            [$connection->getTablePrefix().$table, $column]
        ));
    }

    /**
     * @return list<string>
     */
    protected function coinbaseSettings(): array
    {
        return [
            'payments.coinbase_api_key',
            'payments.coinbase_webhook_key',
            'payments.coinbase_checkout_disabled',
        ];
    }

    /**
     * @return list<string>
     */
    protected function yookassaSettings(): array
    {
        return [
            'payments.yookassa_shop_id' => '',
            'payments.yookassa_secret_key' => '',
            'payments.yookassa_checkout_disabled' => false,
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    protected function xenditSettings(): array
    {
        return [
            'payments.xendit_secret_key' => '',
            'payments.xendit_webhook_token' => '',
            'payments.xendit_checkout_disabled' => false,
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    protected function mollieSettings(): array
    {
        return [
            'payments.mollie_api_key' => '',
            'payments.mollie_checkout_disabled' => false,
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    protected function flutterwaveSettings(): array
    {
        return [
            'payments.flutterwave_secret_key' => '',
            'payments.flutterwave_webhook_secret_hash' => '',
            'payments.flutterwave_checkout_disabled' => false,
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    protected function coingateSettings(): array
    {
        return [
            'payments.coingate_api_token' => '',
            'payments.coingate_mode' => 'live',
            'payments.coingate_checkout_disabled' => false,
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    protected function paddleSettings(): array
    {
        return [
            'payments.paddle_api_key' => '',
            'payments.paddle_hosted_checkout_url' => '',
            'payments.paddle_webhooks_secret' => '',
            'payments.paddle_checkout_disabled' => false,
        ];
    }

    /**
     * @return array<string, string|bool>
     */
    protected function cryptocomSettings(): array
    {
        return [
            'payments.cryptocom_secret_key' => '',
            'payments.cryptocom_webhooks_secret' => '',
            'payments.cryptocom_checkout_disabled' => false,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    protected function deepSeekSettings(): array
    {
        return [
            'ai.deepseek_api_key' => null,
            'ai.deepseek_base_url' => 'https://api.deepseek.com',
        ];
    }

    /**
     * @return array<string, string|bool|int|array|null>
     */
    protected function ageGateSettings(): array
    {
        return [
            'compliance.age_gate_enabled' => false,
            'compliance.age_gate_driver' => 'none',
            'compliance.age_gate_mode' => 'checker',
            'compliance.age_gate_ageverif_public_key' => null,
            'compliance.age_gate_ageverif_oauth_client_id' => null,
            'compliance.age_gate_ageverif_oauth_client_secret' => null,
            'compliance.age_gate_ageverif_challenges' => [],
            'compliance.age_gate_country_detection_driver' => 'none',
            'compliance.age_gate_abstract_api_key' => null,
            'compliance.age_gate_countries_mode' => 'everyone',
            'compliance.age_gate_countries' => [],
            'compliance.age_gate_require_unknown_country' => true,
            'compliance.age_gate_minimum_age' => 18,
            'compliance.age_gate_cookie_lifetime_days' => 90,
        ];
    }
};
