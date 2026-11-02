<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V770 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'profiles.show_online_users_indicator',
                    'display_name' => 'Show user online status',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, the users will have an online indicator on their profile pages. Note:* Websockets are required to be setup first."
                        }',
                    'type' => 'checkbox',
                    'order' => 150,
                    'group' => 'Profiles',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'profiles.record_users_last_activity_time',
                    'display_name' => 'Record users last activity time',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, the platform will track the last time an user has been active."
                        }',
                    'type' => 'checkbox',
                    'order' => 160,
                    'group' => 'Profiles',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'profiles.record_users_last_ip_address',
                    'display_name' => 'Record users IP Addresses',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, the platform will track the last IP address of an user."
                        }',
                    'type' => 'checkbox',
                    'order' => 170,
                    'group' => 'Profiles',
                )
            )
        );

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_active_at')->after('settings')->nullable();
            $table->string('last_ip', 15)->after('last_active_at')->nullable();
        });
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
                'profiles.show_online_users_indicator',
                'profiles.record_users_last_activity_time',
                'profiles.record_users_last_ip_address'
            ])
            ->delete();

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_active_at');
            $table->dropColumn('last_ip');
        });

    }
};
