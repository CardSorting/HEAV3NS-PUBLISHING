<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V830 extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->insert(
            array(
                array(
                    'key' => 'payments.verotel_merchant_id',
                    'display_name' => 'Verotel Merchant ID',
                    'value' => NULL,
                    'details' => '{
                        "description": "A Merchant ID is generated when you create a merchant account on their platform"
                        }',
                    'type' => 'text',
                    'order' => 113,
                    'group' => 'Payments',
                ),
                array(
                    'key' => 'payments.verotel_shop_id',
                    'display_name' => 'Verotel Shop ID',
                    'value' => NULL,
                    'details' => '{
                        "description": "A Shop ID is generated after you complete the website setup on their platform"
                        }',
                    'type' => 'text',
                    'order' => 114,
                    'group' => 'Payments',
                ),
                array(
                    'key' => 'payments.verotel_signature_key',
                    'display_name' => 'Verotel Signature Key',
                    'value' => NULL,
                    'details' => '{
                        "description": "A Signature Key is generated after you complete the website setup on their platform and is used to sign their hooks and requests"
                        }',
                    'type' => 'text',
                    'order' => 115,
                    'group' => 'Payments',
                ),
                array(
                    'key' => 'payments.verotel_control_center_api_user',
                    'display_name' => 'Verotel ControlCenter API Username',
                    'value' => NULL,
                    'details' => '{
                        "description": "You can obtain your username in Control center on a "Setup Control Center API" page"
                        }',
                    'type' => 'text',
                    'order' => 116,
                    'group' => 'Payments',
                ),
                array(
                    'key' => 'payments.verotel_control_center_api_password',
                    'display_name' => 'Verotel ControlCenter API Password',
                    'value' => NULL,
                    'details' => '{
                        "description": "You can obtain your password in Control center on a "Setup Control Center API" page"
                        }',
                    'type' => 'text',
                    'order' => 117,
                    'group' => 'Payments',
                ),
                array (
                    'key' => 'payments.verotel_checkout_disabled',
                    'display_name' => 'Disable on checkout',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "Won`t be shown on checkout, but it`s still available for deposits."
                        }',
                    'type' => 'checkbox',
                    'order' => 118,
                    'group' => 'Payments',
                ),
                array (
                    'key' => 'payments.verotel_recurring_disabled',
                    'display_name' => 'Disable on recurring payments',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "Won`t be available for subscription payments, but it`s still available for deposits and one time payments."
                        }',
                    'type' => 'checkbox',
                    'order' => 119,
                    'group' => 'Payments',
                )
            )
        );

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('verotel_sale_id')->after('mercado_payment_id')->nullable();
                $table->index('verotel_sale_id');
                $table->string('verotel_payment_token')->after('verotel_sale_id')->nullable();
                $table->index('verotel_payment_token');
            });
        }

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->string('verotel_sale_id')->after('ccbill_subscription_id')->nullable();
                $table->index('verotel_sale_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'payments.verotel_merchant_id',
                'payments.verotel_shop_id',
                'payments.verotel_signature_key',
                'payments.verotel_cardbilling_enabled',
                'payments.verotel_checkout_disabled',
                'payments.verotel_recurring_disabled',
                'payments.verotel_control_center_api_user',
                'payments.verotel_control_center_api_password',
            ])
            ->delete();

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('verotel_sale_id');
            $table->dropColumn('verotel_payment_token');
        });

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex(['verotel_sale_id']);
                $table->dropIndex(['verotel_payment_token']);
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('verotel_sale_id');
        });

        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex(['verotel_sale_id']);
            });
        }
    }
};
