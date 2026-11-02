<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

class V950 extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('profiles', function (SettingsBlueprint $blueprint): void {
            $blueprint->update('default_users_to_follow', function ($value) {
                if (is_array($value)) {
                    return $value;
                }

                if (is_string($value) && $value !== '') {
                    return array_values(array_filter(array_map('trim', explode(',', $value))));
                }

                return [];
            });
        });

        $this->migrator->inGroup('payments', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('razorpay_checkout_disabled', false);
            $blueprint->add('razorpay_api_key', '');
            $blueprint->add('razorpay_api_secret', '');
            $blueprint->add('razorpay_webhooks_secret', '');
        });

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('razorpay_payment_token')->after('verotel_payment_token')->nullable();
                $table->string('razorpay_payment_id')->after('razorpay_payment_token')->nullable();
                $table->index('razorpay_payment_token');
                $table->index('razorpay_payment_id');
            });
        }

        if ($this->migrator->exists('payments.tax_info_dac7_enabled')) {
            $this->migrator->rename(
                'payments.tax_info_dac7_enabled',
                'compliance.tax_info_dac7_enabled'
            );
        }

        if ($this->migrator->exists('payments.tax_info_dac7_withdrawals_enforced')) {
            $this->migrator->rename(
                'payments.tax_info_dac7_withdrawals_enforced',
                'compliance.tax_info_dac7_withdrawals_enforced'
            );
        }

        $this->migrator->inGroup('compliance', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('tax_info_dac7_earnings_limit_before_enforced', 0);
        });

    }

    public function down(): void
    {
        $this->migrator->inGroup('profiles', function (SettingsBlueprint $blueprint): void {
            $blueprint->update('default_users_to_follow', function ($value) {
                if (is_array($value)) {
                    return implode(',', $value);
                }

                if (is_string($value)) {
                    return $value;
                }

                return '';
            });
        });

        $this->migrator->inGroup('payments', function (SettingsBlueprint $blueprint): void {
            $blueprint->delete('razorpay_checkout_disabled');
            $blueprint->delete('razorpay_api_key');
            $blueprint->delete('razorpay_api_secret');
            $blueprint->delete('razorpay_webhooks_secret');
        });

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex(['razorpay_payment_token']);
                $table->dropIndex(['razorpay_payment_id']);

                $table->dropColumn('razorpay_payment_token');
                $table->dropColumn('razorpay_payment_id');
            });
        }

        if ($this->migrator->exists('compliance.tax_info_dac7_enabled')) {
            $this->migrator->rename(
                'compliance.tax_info_dac7_enabled',
                'payments.tax_info_dac7_enabled'
            );
        }

        if ($this->migrator->exists('compliance.tax_info_dac7_withdrawals_enforced')) {
            $this->migrator->rename(
                'compliance.tax_info_dac7_withdrawals_enforced',
                'payments.tax_info_dac7_withdrawals_enforced'
            );
        }

        $this->migrator->inGroup('compliance', function (SettingsBlueprint $blueprint): void {
            $blueprint->delete('tax_info_dac7_earnings_limit_before_enforced');
        });

    }
}
