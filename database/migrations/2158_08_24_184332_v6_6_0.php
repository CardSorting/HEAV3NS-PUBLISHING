<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V660 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Mercado
        DB::table('settings')->insert(array(
            array(
                'key' => 'payments.mercado_access_token',
                'display_name' => 'Mercado Access Token',
                'value' => '',
                'details' => '',
                'type' => 'text',
                'order' => 102,
                'group' => 'Payments',
            ),
            array(
                'key' => 'payments.mercado_checkout_disabled',
                'display_name' => 'Disable for checkout',
                'value' => 0,
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "Won`t be shown on checkout, but it`s still available for deposits."
                        }',
                'type' => 'checkbox',
                'order' => 104,
                'group' => 'Payments',
            )
        ));

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('mercado_payment_token')->after('paystack_payment_token')->nullable();
                $table->index('mercado_payment_token');
                $table->string('mercado_payment_id')->after('mercado_payment_token')->nullable();
                $table->index('mercado_payment_id');
            });
        }

        // PPV only streams
        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'streams.allow_free_streams',
                    'display_name' => 'Allow free streams',
                    'value' => 1,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If disabled, PPV only streams will be enforced."
                        }',
                    'type' => 'checkbox',
                    'order' => 164,
                    'group' => 'Streams',
                ),
            )
        );

        // Local wallet disable for recurring subs
        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.disable_local_wallet_for_subscriptions',
                    'display_name' => 'Disable local wallet based subscriptions',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If disabled, users will not be able to do local wallet subscriptions, only one-time payments. Sometimes, payment providers might enforce this requirement."
                        }',
                    'type' => 'checkbox',
                    'order' => 115,
                    'group' => 'Payments',
                ),
            )
        );

        // Public pages
        if (Schema::hasTable('public_pages')) {
            Schema::table('public_pages', function (Blueprint $table) {
                $table->string('short_title')->default('')->nullable()->after('title');
                $table->boolean('is_tos')->default(0)->nullable();
                $table->boolean('is_privacy')->default(0)->nullable();
            });

            DB::table('public_pages')
                ->where('slug', 'privacy')
                ->update(['is_privacy' => 1,'short_title' => 'Privacy']);

            DB::table('public_pages')
                ->where('slug', 'terms-and-conditions')
                ->update(['is_tos' => 1,'short_title' => 'Terms']);

            DB::table('public_pages')
                ->where('slug', 'help')
                ->update(['short_title' => 'Help']);
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Delete old
        DB::table('settings')
            ->where('key', 'payments.mercado_access_token')
            ->delete();

        // Delete old
        DB::table('settings')
            ->where('key', 'payments.mercado_checkout_disabled')
            ->delete();

        DB::table('settings')
            ->where('key', 'streams.allow_free_streams')
            ->delete();

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('mercado_payment_token');
            $table->dropColumn('mercado_payment_id');
        });

        Schema::table('public_pages', function (Blueprint $table) {
            $table->dropColumn('is_tos');
            $table->dropColumn('is_privacy');
            $table->dropColumn('short_title');
        });

        DB::table('settings')
            ->where('key', 'payments.disable_local_wallet_for_subscriptions')
            ->delete();
    }
}
