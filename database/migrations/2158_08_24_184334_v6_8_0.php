<?php

use App\Model\Attachment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V680 extends Migration
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
                'key' => 'media.transcoding_driver',
                'display_name' => 'Transcoding driver',
                'value' => 'none',
                'details' => '{
"default" : "pusher",
"options" : {
"none": "None",
"ffmpeg": "FFmpeg",
"coconut": "Coconut"
}
}',
                'type' => 'select_dropdown',
                'order' => 3,
                'group' => 'Media',
            )
        );

        if(getSetting('media.enable_ffmpeg')){
            DB::table('settings')
                ->where('key', 'media.transcoding_driver')
                ->update([
                    'value'=>'ffmpeg',
                ]);
        }

        DB::table('settings')
            ->whereIn('key', [
                'media.enable_ffmpeg',
            ])
            ->delete();


        DB::table('settings')->insert(array(
            array(
                'key' => 'media.coconut_api_key',
                'display_name' => 'Coconut API Key',
                'value' => '',
                'details' => '',
                'type' => 'text',
                'order' => 1150,
                'group' => 'Media',
            )
        ));

        if (Schema::hasTable('attachments')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->string('coconut_id')->after('message_id')->nullable();
                $table->index('coconut_id');
                $table->boolean('has_thumbnail')->after('coconut_id')->nullable();
            });
        }
        Attachment::whereIn('type', ["jpeg", "png", "jpg"])->update(['has_thumbnail' => 1]);

        // Renaming social media to social links
        DB::table('settings')
            ->where('key', 'social-media.facebook_url')
            ->update([
                'key' => 'social-links.facebook_url',
                'group' => 'Social links'
            ]);

        DB::table('settings')
            ->where('key', 'social-media.instagram_url')
            ->update([
                'key' => 'social-links.instagram_url',
                'group' => 'Social links'
            ]);

        DB::table('settings')
            ->where('key', 'social-media.reddit_url')
            ->update([
                'key' => 'social-links.reddit_url',
                'group' => 'Social links'
            ]);

        DB::table('settings')
            ->where('key', 'social-media.telegram_link')
            ->update([
                'key' => 'social-links.telegram_link',
                'group' => 'Social links'
            ]);

        DB::table('settings')
            ->where('key', 'social-media.tiktok_url')
            ->update([
                'key' => 'social-links.tiktok_url',
                'group' => 'Social links'
            ]);

        DB::table('settings')
            ->where('key', 'social-media.twitter_url')
            ->update([
                'key' => 'social-links.twitter_url',
                'group' => 'Social links'
            ]);

        DB::table('settings')
            ->where('key', 'social-media.whatsapp_url')
            ->update([
                'key' => 'social-links.whatsapp_url',
                'group' => 'Social links'
            ]);

        DB::table('settings')
            ->where('key', 'social-media.youtube_url')
            ->update([
                'key' => 'social-links.youtube_url',
                'group' => 'Social links'
            ]);

        DB::table('settings')
            ->where('key', 'media.apply_watermark')
            ->update([
                'details' => '{
"on" : "On",
"off" : "Off",
"checked" : true,
"description": "For images, GD library is required. For videos, either ffmpeg or coconut transcoders."
}',
            ]);

        DB::table('settings')
            ->where('key', 'media.use_url_watermark')
            ->update([
                'details' => '{
"on" : "On",
"off" : "Off",
"checked" : false,
"description": "* Not supported for coconut transcoder."
}',
            ]);

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'media.coconut_audio_encoder',
                    'display_name' => 'Coconut Audio encoder',
                    'value' => 'aac',
                    'details' => '{
"default" : "aac",
"options" : {
"aac": "AAC Encoder",
"mp3": "MP3 Encoder"
},
"description": "AAC is recommended for best compatibility."
}',
                    'type' => 'select_dropdown',
                    'order' => 1250,
                    'group' => 'Media'
                ),
            )
        );

        DB::table('settings')->insert(
            array (
                'key' => 'media.coconut_video_conversion_quality_preset',
                'display_name' => 'Coconut video quality preset',
                'details' => '{
"description" : "Going for better quality will reduce the processing time but increase the file size, next to it\'s original size.",
"default" : "coconut_balanced",
"options" : {
"coconut_size": "Size optimized",
"coconut_balanced": "Balanced profile",
"coconut_quality": "Quality optimized"
}
}',
                'value' => 'coconut_balanced',
                'type' => 'radio_btn',
                'order' => 1260,
                'group' => 'Media'
            )
        );

        DB::table('settings')->insert(array (
            array (
                'key' => 'media.coconut_enforce_mp4_conversion',
                'display_name' => 'Enforce mp4 videos re-conversion',
                'value' => '1',
                'details' => '{
"on" : "On",
"off" : "Off",
"checked" : true,
"description": "Allows you skip mp4 re-conversion to platform standards, reducing costs. Recommended value: On."
}',
                'type' => 'checkbox',
                'order' => 1270,
                'group' => 'Media',
            )));


        // Media settings re-ordering
        $keysToUpdate = [
            "media.transcoding_driver",
            "media.ffmpeg_path",
            "media.ffprobe_path",
            "media.enforce_mp4_conversion",
            "media.ffmpeg_video_conversion_quality_preset",
            "media.ffmpeg_audio_encoder",
            "media.allowed_file_extensions",
            "media.upload_chunk_size",
            "media.use_chunked_uploads",
            "media.max_videos_length",
            "media.max_file_upload_size",
            "media.apply_watermark",
            "media.watermark_image",
            "media.use_url_watermark",
            "media.users_covers_size",
            "media.users_avatars_size",
            "media.max_avatar_cover_file_size",
            "media.coconut_api_key",
            "media.coconut_audio_encoder",
            "media.coconut_video_conversion_quality_preset",
            "media.coconut_enforce_mp4_conversion",
        ];

        $orderIncremented = 0;
        foreach ($keysToUpdate as $key) {
            $orderIncremented += 10;
            $sqlStatement = "UPDATE `settings` SET `order` = '{$orderIncremented}' WHERE `key` = '{$key}';";
            DB::statement($sqlStatement);
        }

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'media.enable_ffmpeg',
                    'display_name' => 'Enable FFmpeg',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If disabled, FFmpeg won\'t convert any videos, only mp4 videos will be allowed for upload."
                        }',
                    'type' => 'checkbox',
                    'order' => 5,
                    'group' => 'Media',
                )
            )
        );
        DB::table('settings')
            ->where('key', 'media.transcoding_driver')
            ->delete();

        DB::table('settings')
            ->where('key', 'media.coconut_api_key')
            ->delete();


        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('coconut_id');
            $table->dropColumn('has_thumbnail');
        });

        DB::table('settings')
            ->where('key', 'social-links.facebook_url')
            ->update([
                'key' => 'social-media.facebook_url',
                'group' => 'Social media'
            ]);

        DB::table('settings')
            ->where('key', 'social-links.instagram_url')
            ->update([
                'key' => 'social-media.instagram_url',
                'group' => 'Social media'
            ]);

        DB::table('settings')
            ->where('key', 'social-links.reddit_url')
            ->update([
                'key' => 'social-media.reddit_url',
                'group' => 'Social media'
            ]);

        DB::table('settings')
            ->where('key', 'social-links.telegram_link')
            ->update([
                'key' => 'social-media.telegram_link',
                'group' => 'Social media'
            ]);

        DB::table('settings')
            ->where('key', 'social-links.tiktok_url')
            ->update([
                'key' => 'social-media.tiktok_url',
                'group' => 'Social media'
            ]);

        DB::table('settings')
            ->where('key', 'social-links.twitter_url')
            ->update([
                'key' => 'social-media.twitter_url',
                'group' => 'Social media'
            ]);

        DB::table('settings')
            ->where('key', 'social-links.whatsapp_url')
            ->update([
                'key' => 'social-media.whatsapp_url',
                'group' => 'Social media'
            ]);

        DB::table('settings')
            ->where('key', 'social-links.youtube_url')
            ->update([
                'key' => 'social-media.youtube_url',
                'group' => 'Social media'
            ]);


        DB::table('settings')
            ->whereIn('key', [
                "media.coconut_audio_encoder",
                "media.coconut_video_conversion_quality_preset",
                "media.coconut_enforce_mp4_conversion"
            ])
            ->delete();


    }
}
