<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class V820 extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            'streams.allow_streams',
            'streams.allow_free_streams',
            'streams.max_live_duration',
            'streams.key',
            'streams.zone_id',
            'streams.encoder',
            'streams.allow_dvr',
            'streams.allow_mux',
            'streams.allow_360p',
            'streams.allow_480p',
            'streams.allow_576p',
            'streams.allow_720p',
            'streams.allow_1080p',
        ];

        $excluded = [
            'streams.allow_streams',
            'streams.allow_free_streams',
            'streams.max_live_duration',
        ];

        $order = 10;

        foreach ($settings as $key) {
            $newKey = in_array($key, $excluded)
                ? $key
                : 'streams.pushr_' . substr($key, strlen('streams.'));

            DB::table('settings')
                ->where('key', $key)
                ->update([
                    'key' => $newKey,
                    'order' => $order,
                ]);

            $order += 10;
        }


        DB::table('settings')->insert(
            array(
                'key' => 'streams.streaming_driver',
                'display_name' => 'Streaming driver',
                'value' => 'none',
                'details' => '{
"default" : "pusher",
"options" : {
"none": "None",
"pushr": "PushrCDN",
"livekit": "LiveKit"
}
}',
                'type' => 'select_dropdown',
                'order' => 1,
                'group' => 'Streams',
            )
        );

        if(getSetting('streams.allow_streams') && getSetting('streams.pushr_key')) {
            DB::table('settings')
                ->where('key', 'streams.streaming_driver')
                ->update([
                    'value' => 'pushr',
                ]);
        }

        DB::table('settings')
            ->whereIn('key', [
                "streams.allow_streams",
            ])
            ->delete();

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'streams.livekit_api_key',
                    'display_name' => 'LiveKit API Key',
                    'value' => '',
                    'type' => 'text',
                    'order' => 140,
                    'group' => 'Streams',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'streams.livekit_api_secret',
                    'display_name' => 'LiveKit API Secret',
                    'value' => '',
                    'type' => 'text',
                    'order' => 150,
                    'group' => 'Streams',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'streams.livekit_ws_url',
                    'display_name' => 'LiveKit WS Url',
                    'value' => '',
                    'type' => 'text',
                    'order' => 160,
                    'group' => 'Streams',
                )
            )
        );

        Schema::table('streams', function (Blueprint $table) {
            $table->integer('driver')->after('id')->default(1);
        });

        Schema::table('streams', function (Blueprint $table) {
            // Make Pushr-specific fields nullable
            $table->unsignedBigInteger('pushr_id')->unsigned()->nullable()->change();
            $table->string('rtmp_key')->nullable()->change();
            $table->string('rtmp_server')->nullable()->change();
            $table->string('hls_link')->nullable()->change();
            $table->string('vod_link')->nullable()->change();
        });



        DB::table('settings')
            ->whereIn('key', [
                "ai.open_ai_model",
            ])
            ->delete();

        DB::table('settings')->insert([
            'key' => 'ai.open_ai_model',
            'display_name' => 'OpenAI Model',
            'value' => 'o4-mini',
            'details' => '{
        "default": "o4-mini",
        "options": {
            "o3": "OpenAI o3",
            "o4-mini": "OpenAI o4-mini",
            "gpt-4o": "GPT-4o",
            "gpt-3.5-turbo": "GPT-3.5 Turbo"
        },
        "description": "Select the OpenAI model to be used. For more details and pricing, visit https://platform.openai.com/docs/models."
    }',
            'type' => 'select_dropdown',
            'order' => 22,
            'group' => 'AI',
        ]);

        DB::table('settings')
            ->where('key', 'compliance.enforce_tos_check_on_id_verify')
            ->update([
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "If enabled, a TOS & Creator agreement checkbox will be shown on ID-verify page. CCBill compliance requirement. "
                        }',
            ]);

        DB::table('settings')
            ->where('key', 'site.timezone')
            ->update([
                'order' => '310',
            ]);


        DB::table('settings')
            ->where('key', 'feed.disable_posts_text_preview')
            ->update([
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, posts (and messages) will have their text content locked behind the paywall as well."
                        }',
            ]);

        DB::table('settings')
            ->where('key', 'compliance.allow_text_only_ppv')
            ->update([
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "If enabled, creators will be allowed to sell text-only PPV messages & posts (no media requirements) "
                        }',
                    'type' => 'checkbox',
                    'order' => 1200,
                    'group' => 'Compliance',
            ]);

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'site.enable_smooth_page_change_transitions',
                    'display_name' => 'Enable smooth poage change transitions',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "If enabled, transitions between server-side rendered pages will be smoothed out. "
                        }',
                    'type' => 'checkbox',
                    'order' => 305,
                    'group' => 'Site',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'compliance.enforce_media_agreement_on_id_verify',
                    'display_name' => 'Enforce media agreement on ID-verify page',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description" : "If enabled, a media-agreement checkbox will be shown on ID-verify page. CCBill compliance requirement. "
                        }',
                    'type' => 'checkbox',
                    'order' => 1220,
                    'group' => 'Compliance',
                )
            )
        );

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $settings = [
            'streams.allow_streams',
            'streams.allow_free_streams',
            'streams.max_live_duration',
            'streams.pushr_key',
            'streams.pushr_zone_id',
            'streams.pushr_encoder',
            'streams.pushr_allow_dvr',
            'streams.pushr_allow_mux',
            'streams.pushr_allow_360p',
            'streams.pushr_allow_480p',
            'streams.pushr_allow_576p',
            'streams.pushr_allow_720p',
            'streams.pushr_allow_1080p',
        ];

        $order = 1;

        foreach ($settings as $key) {
            $originalKey = str_starts_with($key, 'streams.pushr_')
                ? 'streams.' . substr($key, strlen('streams.pushr_'))
                : $key;

            DB::table('settings')
                ->where('key', $key)
                ->update([
                    'key' => $originalKey,
                    'order' => $order,
                ]);

            $order += 1;
        }

        DB::table('settings')
            ->whereIn('key', [
                "streams.streaming_driver",
                "streams.livekit_api_key",
                "streams.livekit_api_secret",
                "streams.livekit_ws_url",
                "compliance.enforce_media_agreement_on_id_verify",
                "site.enable_smooth_page_change_transitions",
            ])
            ->delete();

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'streams.allow_streams',
                    'display_name' => 'Allow streams',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        }',
                    'type' => 'checkbox',
                    'order' => 5,
                    'group' => 'Streams',
                ),
            )
        );

        Schema::table('streams', function (Blueprint $table) {
            $table->dropColumn('driver');
        });



//        Schema::table('streams', function (Blueprint $table) {
//            // Revert back to NOT NULL (use with caution, assumes no nulls exist)
//            $table->unsignedBigInteger('pushr_id')->nullable(false)->change();
//            $table->string('rtmp_key')->nullable(false)->change();
//            $table->string('rtmp_server')->nullable(false)->change();
//            $table->string('hls_link')->nullable(false)->change();
//            $table->string('vod_link')->nullable(false)->change();
//        });

    }
}
