<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V670 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('settings')
            ->where('key', 'payments.disable_local_wallet_for_subscriptions')
            ->update([
                'order'=>'130',
         ]);

        DB::table('settings')
            ->where('key', 'security.recaptcha_enabled')
            ->update([
                'details'=>'{
                        "description": "If enabled, it will be shown the public user registration page."
                    }',
        ]);

        DB::table('settings')
            ->where('key', 'payments.min_ppv_content_price')
            ->delete();

        DB::table('settings')
            ->where('key', 'payments.max_ppv_content_price')
            ->delete();

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.min_ppv_post_price',
                    'display_name' => 'Min PPV post price',
                    'value' => '1',
                    'type' => 'text',
                    'order' => 100,
                    'group' => 'Payments',
                ),
                array (
                    'key' => 'payments.max_ppv_post_price',
                    'display_name' => 'Max PPV post price',
                    'value' => '500',
                    'type' => 'text',
                    'order' => 105,
                    'group' => 'Payments',
                ),

                array (
                    'key' => 'payments.min_ppv_message_price',
                    'display_name' => 'Min PPV message price',
                    'value' => '1',
                    'type' => 'text',
                    'order' => 110,
                    'group' => 'Payments',
                ),
                array (
                    'key' => 'payments.max_ppv_message_price',
                    'display_name' => 'Max PPV message price',
                    'value' => '500',
                    'type' => 'text',
                    'order' => 115,
                    'group' => 'Payments',
                ),

                array (
                    'key' => 'payments.min_ppv_stream_price',
                    'display_name' => 'Min PPV stream price',
                    'value' => '5',
                    'type' => 'text',
                    'order' => 120,
                    'group' => 'Payments',
                ),
                array (
                    'key' => 'payments.max_ppv_stream_price',
                    'display_name' => 'Max PPV stream price',
                    'value' => '500',
                    'type' => 'text',
                    'order' => 125,
                    'group' => 'Payments',
                ),

            )
        );

        \DB::table('settings')->insert(array(
            array(
                'key' => 'payments.withdrawal_custom_message_box',
                'display_name' => 'Withdrawal payments details box',
                'value' => '',
                'details' => '{"description":"This field can be used to add a custom info box, where users can be informed on any withdrawal information they might need to know about."}',
                'type' => 'code_editor',
                'order' => 99,
                'group' => 'Payments',
            )));

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'admin.send_notifications_on_pending_posts',
                    'display_name' => 'Admin notifications for posts to be approved',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, the admin users will receive an email whenever a post is pending approval."
                        }',
                    'type' => 'checkbox',
                    'order' => 10,
                    'group' => 'Admin',
                )
            )
        );

        DB::table('settings')
            ->where('key', 'payments.minimum_subscription_price')
            ->update([
                'display_name'=>'Min subscription price',
            ]);

        DB::table('settings')
            ->where('key', 'payments.maximum_subscription_price')
            ->update([
                'display_name'=>'Max subscription price',
            ]);

        DB::table('settings')
            ->where('key', 'payments.deposit_min_amount')
            ->update([
                'display_name'=>'Min deposit amount',
            ]);

        DB::table('settings')
            ->where('key', 'payments.deposit_max_amount')
            ->update([
                'display_name'=>'Max deposit amount',
            ]);

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
            ->where('key', 'admin.send_notifications_on_pending_posts')
            ->delete();
        DB::table('settings')
            ->where('key', 'payments.withdrawal_custom_message_box')
            ->delete();
        DB::table('settings')
            ->where('key', 'payments.disable_local_wallet_for_subscriptions')
            ->update([
                'order'=>'115',
            ]);

        DB::table('settings')
            ->where('key', 'payments.min_ppv_post_price')
            ->delete();
        DB::table('settings')
            ->where('key', 'payments.max_ppv_post_price')
            ->delete();
        DB::table('settings')
            ->where('key', 'payments.min_ppv_message_price')
            ->delete();
        DB::table('settings')
            ->where('key', 'payments.max_ppv_message_price')
            ->delete();
        DB::table('settings')
            ->where('key', 'payments.min_ppv_stream_price')
            ->delete();
        DB::table('settings')
            ->where('key', 'payments.max_ppv_stream_price')
            ->delete();

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'payments.min_ppv_content_price',
                    'display_name' => 'Min PPV content price',
                    'value' => '1',
                    'type' => 'text',
                    'order' => 100,
                    'group' => 'Payments',
                    'details' => '{
                        "description": "Applies to paid posts, messages and streams."
                        }',
                ),
                array (
                    'key' => 'payments.max_ppv_content_price',
                    'display_name' => 'Max PPV content price',
                    'value' => '500',
                    'type' => 'text',
                    'order' => 110,
                    'group' => 'Payments',
                    'details' => '{
                        "description": "Applies to paid posts, messages and streams."
                        }',
                ),

            )
        );
    }
}
