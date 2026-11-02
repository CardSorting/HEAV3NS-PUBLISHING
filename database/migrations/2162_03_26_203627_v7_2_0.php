<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V720 extends Migration
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
                'key' => 'feed.disable_posts_text_preview',
                'display_name' => 'Disable posts text preview',
                'value' => 0,
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, posts will have their attached text locked behind the paywall as well."
                        }',
                'type' => 'checkbox',
                'order' => 110,
                'group' => 'Feed',
            )
        ));


        DB::table('settings')
            ->where('key', 'feed.disable_right_click')
            ->update([
                'key' => 'media.disable_media_right_click',
                'group' => 'Media',
                'display_name' => 'Disable right click on media assets',
                'details'=>'{
                        "description": "If enabled, right click on attachments (posts & messages) will be disabled."
                    }',
                'order' => 175
            ]);


        Schema::table('payment_requests', function (Blueprint $table) {
            $table->longText('message')->nullable()->change();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {


        Schema::table('payment_requests', function (Blueprint $table) {
            $table->string('message')->nullable()->change();
        });

        DB::table('settings')
            ->whereIn('key', [
                'feed.disable_posts_text_preview',
            ])
            ->delete();

        DB::table('settings')
            ->where('key', 'media.disable_media_right_click')
            ->update([
                'key' => 'feed.disable_right_click',
                'group' => 'Feed',
            ]);


    }
};
