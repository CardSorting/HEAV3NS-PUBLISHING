<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V710 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('stripe_account_id')->after('auth_provider_id')->nullable();
                $table->unsignedBigInteger('country_id')->after('open_profile')->nullable();
                $table->foreign('country_id')->references('id')->on('countries');
                $table->boolean('stripe_onboarding_verified')->after('stripe_account_id')->default(0)->nullable();
            });
        }

        if (Schema::hasTable('withdrawals')) {
            Schema::table('withdrawals', function (Blueprint $table) {
                $table->string('stripe_payout_id')->nullable();
                $table->string('stripe_transfer_id')->nullable();
            });
        }

        DB::table('settings')->insert(
            array(
                'key' => 'payments.withdrawal_enable_stripe_connect',
                'display_name' => 'Enable Stripe Connect for withdrawals',
                'value' => 0,
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "Enables withdrawals through Stripe Connect. Will enforce users going through Stripe onboarding to validate their details"
                        }',
                'type' => 'checkbox',
                'order' => 100,
                'group' => 'Payments',
            )
        );

        DB::table('settings')->insert(
            array(
                'key' => 'payments.withdrawal_stripe_connect_webhooks_secret',
                'display_name' => 'Stripe Connect Webhooks Secret',
                'value' => NULL,
                'details' => '{
                        "description": "It must be set if Stripe Connect is enabled"
                        }',
                'type' => 'text',
                'order' => 101,
                'group' => 'Payments',
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.stripe_ideal_provider_enabled',
                    'display_name' => 'Allow iDEAL',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "Enable iDEAL payment provider through Stripe. This will allow customers to use iDEAL as payment provider in Stripe Checkout (currency must be EUR)"
                        }',
                    'type' => 'checkbox',
                    'order' => 102,
                    'group' => 'Payments',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.stripe_blik_provider_enabled',
                    'display_name' => 'Allow Blik',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "Enable Blik payment provider through Stripe. This will allow customers to use Blik as payment provider in Stripe Checkout (currency must be PLN)"
                        }',
                    'type' => 'checkbox',
                    'order' => 104,
                    'group' => 'Payments',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.stripe_bancontact_provider_enabled',
                    'display_name' => 'Allow Bancontact',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "Enable Bancontact payment provider through Stripe. This will allow customers to use Bancontact as payment provider in Stripe Checkout (currency must be EUR)"
                        }',
                    'type' => 'checkbox',
                    'order' => 106,
                    'group' => 'Payments',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.stripe_eps_provider_enabled',
                    'display_name' => 'Allow EPS',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "Enable EPS payment provider through Stripe. This will allow customers to use EPS as payment provider in Stripe Checkout (currency must be EUR)"
                        }',
                    'type' => 'checkbox',
                    'order' => 108,
                    'group' => 'Payments',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.stripe_giropay_provider_enabled',
                    'display_name' => 'Allow Giropay',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "Enable Giropay payment provider through Stripe. This will allow customers to use Giropay as payment provider in Stripe Checkout (currency must be EUR)"
                        }',
                    'type' => 'checkbox',
                    'order' => 110,
                    'group' => 'Payments',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.stripe_przelewy_provider_enabled',
                    'display_name' => 'Allow Przelewy24',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "Enable Przelewy24 payment provider through Stripe. This will allow customers to use Przelewy24 as payment provider in Stripe Checkout (currency must be EUR or PLN)"
                        }',
                    'type' => 'checkbox',
                    'order' => 112,
                    'group' => 'Payments',
                )
            )
        );

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('settings')
            ->whereIn('key', [
                'payments.withdrawal_enable_stripe_connect',
                'payments.withdrawal_stripe_connect_webhooks_secret',
                'payments.stripe_ideal_provider_enabled',
                'payments.stripe_blik_provider_enabled',
                'payments.stripe_bancontact_provider_enabled',
                'payments.stripe_eps_provider_enabled',
                'payments.stripe_giropay_provider_enabled',
                'payments.stripe_przelewy_provider_enabled',
            ])
            ->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('stripe_account_id');
            $table->dropColumn('stripe_onboarding_verified');
            $table->dropForeign('users_country_id_foreign');
            $table->dropColumn('country_id');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('stripe_payout_id');
            $table->dropColumn('stripe_transfer_id');
        });
    }
};
