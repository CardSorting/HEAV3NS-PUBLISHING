<?php

use App\Model\Attachment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V700 extends Migration
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
                    'key' => 'profiles.disable_profile_offers',
                    'display_name' => 'Disable profile promotions',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "Disables creator time limited offers. Sometimes, payment providers like CCBill might ask for this."
                        }',
                    'type' => 'checkbox',
                    'order' => 140,
                    'group' => 'Profiles',
                )
            )
        );

        DB::table('settings')->insert(array(
            array(
                'key' => 'media.coconut_video_region',
                'display_name' => 'Coconut region',
                'value' => '',
                'details' => '{
"description" : "Make sure you\'re using the same region under which you registered the account on. EG: us-east-1/us-west-2/eu-west-1"}',
                'type' => 'text',
                'order' => 185,
                'group' => 'Media',
            )
        ));

        if (Schema::hasTable('user_reports')) {
            Schema::table('user_reports', function (Blueprint $table) {
                $table->bigInteger('message_id')->after('post_id')->nullable();
                $table->index('message_id');
                $table->bigInteger('stream_id')->after('message_id')->nullable();
                $table->index('stream_id');
            });
        }


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('settings')
            ->where('key', 'profiles.disable_profile_offers')
            ->delete();

        DB::table('settings')
            ->where('key', 'media.coconut_video_region')
            ->delete();

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('message_id');
            $table->dropColumn('stream_id');
        });

    }
}
