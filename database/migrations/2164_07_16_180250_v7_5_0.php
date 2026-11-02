<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V750 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        DB::table('settings')
            ->where('key', 'ai.open_ai_model')
            ->update([
                'value' => 'gpt-4o',
            ]);

        // Ordering fixes

        DB::table('settings')
            ->where('key', 'media.max_file_upload_size')
            ->update([
                'order' => '75',
            ]);

        DB::table('settings')
            ->where('key', 'media.use_chunked_uploads')
            ->update([
                'order' => '80',
            ]);

        DB::table('settings')
            ->where('key', 'media.upload_chunk_size')
            ->update([
                'order' => '90',
            ]);

        DB::table('settings')
            ->where('key', 'media.use_url_watermark')
            ->update([
                'details' => '        {
            "on" : "On",
"off" : "Off",
"checked" : false,
"description": "Adds profile url link as watermark to media. * Not supported for coconut transcoder."
}',
            ]);


        // New login page bg image setting
        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'site.login_page_background_image',
                    'display_name' => 'Login page background image',
                    'value' => '',
                    'details' => '{"description" : "The image to be used as a background image on the login and register page."}',
                    'type' => 'file',
                    'order' => 67,
                    'group' => 'Site',
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
                'site.login_page_background_image',
            ])
            ->delete();
    }
};
