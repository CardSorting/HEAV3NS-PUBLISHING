<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V760 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        // caprcha driver
        DB::table('settings')->insert(
            array(
                'key' => 'security.captcha_driver',
                'display_name' => 'Captcha driver',
                'value' => 'none',
                'details' => '{
"default" : "pusher",
"options" : {
"none": "None",
"turnstile": "Turnstile",
"hcaptcha": "hCaptcha",
"recaptcha": "reCaptcha"
}
}',
                'type' => 'select_dropdown',
                'order' => 79,
                'group' => 'Security',
            )
        );



        // captcha drivers fields
        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'security.turnstile_site_key',
                    'display_name' => 'Turnstile Site Key',
                    'value' => '',
                    'type' => 'text',
                    'order' => 1230,
                    'group' => 'Security',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'security.turnstile_site_secret_key',
                    'display_name' => 'Turnstile Secret Key',
                    'value' => '',
                    'type' => 'text',
                    'order' => 1240,
                    'group' => 'Security',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'security.hcaptcha_site_key',
                    'display_name' => 'hCaptcha Site Key',
                    'value' => '',
                    'type' => 'text',
                    'order' => 1250,
                    'group' => 'Security',
                )
            )
        );

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'security.hcaptcha_site_secret_key',
                    'display_name' => 'hCaptcha Secret Key',
                    'value' => '',
                    'type' => 'text',
                    'order' => 1260,
                    'group' => 'Security',
                )
            )
        );

        // if recaptcha was on - set default driver to recaptcha and drop that column
        if(getSetting('security.recaptcha_enabled')){
            DB::table('settings')
                ->where('key', 'security.recaptcha_enabled')
                ->update([
                    'value' => 'reCaptcha',
                ]);
        }

        DB::table('settings')
            ->where('key', 'profiles.allow_profile_bio_markdown')
            ->update([
                'display_name' => 'Use markdown editor for profile descriptions',
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : true,
                        "description": "If enabled, users will be able to use a markdown editor in their profile bios."
                    }',
            ]);

        DB::table('settings')
            ->whereIn('key', [
                'security.recaptcha_enabled',
                'profiles.allow_profile_bio_markdown_links',
            ])
            ->delete();

        DB::table('settings')->insert(array(
            array(
                'key' => 'profiles.allow_hyperlinks',
                'display_name' => 'Allow hyperlinks',
                'value' => 0,
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, links will get hyperlinked in the posts, profile bio and in messenger."
                        }',
                'type' => 'checkbox',
                'order' => 38,
                'group' => 'Profiles',
            )
        ));

        DB::table('settings')->insert(array(
            array(
                'key' => 'media.use_blurred_previews_for_locked_posts',
                'display_name' => 'Use blurred previews for locked content',
                'value' => 0,
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, locked content will be using blurred previews. Video transocoding service is required for video files."
                        }',
                'type' => 'checkbox',
                'order' => 220,
                'group' => 'Media',
            )
        ));

        if (Schema::hasTable('attachments')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->boolean('has_blurred_preview')->after('has_thumbnail')->nullable();
            });
        }

        // Ordering fixes for Feed settings with spacing of 10
        DB::table('settings')
            ->where('key', 'feed.feed_posts_per_page')
            ->update([
                'order' => '10',
            ]);

        DB::table('settings')
            ->where('key', 'feed.min_post_description')
            ->update([
                'order' => '20',
            ]);

        DB::table('settings')
            ->where('key', 'feed.post_box_max_height')
            ->update([
                'order' => '30',
            ]);

        DB::table('settings')
            ->where('key', 'feed.allow_post_scheduling')
            ->update([
                'order' => '40',
            ]);

        DB::table('settings')
            ->where('key', 'feed.enable_post_description_excerpts')
            ->update([
                'order' => '50',
                'display_name' => 'Enable post excerpts'
            ]);

        DB::table('settings')
            ->where('key', 'feed.disable_posts_text_preview')
            ->update([
                'order' => '60',
            ]);

        DB::table('settings')
            ->where('key', 'feed.show_attachments_counts_for_ppv')
            ->update([
                'order' => '70',
            ]);

        DB::table('settings')
            ->where('key', 'feed.allow_gallery_zoom')
            ->update([
                'order' => '80',
                'display_name' => 'Allow zoom on post assets',
            ]);

        DB::table('settings')
            ->where('key', 'feed.hide_suggestions_slider')
            ->update([
                'order' => '90',
            ]);

//        DB::table('settings')
//            // ot dont do this but do inverse of that other one
//            ->where('key', 'feed.hide_suggestions_slider')
//            ->update([
//                'order' => '90',
//                'key' => 'feed.show_suggestions_slider',
//                'details' => '{
//                        "true" : "On",
//                        "false" : "Off",
//                        "checked" : true,
//                        "description": "If disabled, the users suggestion slider will be hidden from the feed page."
//                        }',
//                'value' => 1,
//                'display_name' => 'Show profiles suggestions slider'
//            ]);

        DB::table('settings')
            ->where('key', 'feed.suggestions_skip_empty_profiles')
            ->update([
                'order' => '100',
                'display_name' => 'Skip empty profiles'
            ]);

        DB::table('settings')
            ->where('key', 'feed.suggestions_skip_unverified_profiles')
            ->update([
                'order' => '110',
                'display_name' => 'Skip unverified profiles'
            ]);

        DB::table('settings')
            ->where('key', 'feed.suggestions_use_featured_users_list')
            ->update([
                'order' => '120',
                'display_name' => 'Override suggestions with featured users'
            ]);

        DB::table('settings')
            ->where('key', 'feed.feed_suggestions_autoplay')
            ->update([
                'order' => '130',
                'display_name' => 'Autoplay box slides'
            ]);

        DB::table('settings')
            ->where('key', 'feed.feed_suggestions_total_cards')
            ->update([
                'order' => '140',
                'display_name' => 'Total cards'
            ]);

        DB::table('settings')
            ->where('key', 'feed.feed_suggestions_card_per_page')
            ->update([
                'order' => '150',
                'display_name' => 'Cards per page'
            ]);

        DB::table('settings')
            ->where('key', 'feed.default_search_widget_filter')
            ->update([
                'order' => '220',
            ]);



        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'feed.expired_subs_widget_hide',
                    'display_name' => 'Hide the expired subscriptions box',
                    'value' => 1,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : true,
                        "description": "If enabled, the expired subscriptions slider will be hidden from the feed page."
                        }',
                    'type' => 'checkbox',
                    'order' => 170,
                    'group' => 'Feed',
                )
            )
        );

        DB::table('settings')->insert(array (
            array (
                'key' => 'feed.expired_subs_widget_autoplay',
                'display_name' => 'Autoplay box slides',
                'details' => '{
"on" : "On",
"off" : "Off",
"checked" : true,
}',
                'value' => '0',
                'type' => 'checkbox',
                'order' => 180,
                'group' => 'Feed',
            ),
                array (
                    'key' => 'feed.expired_subs_widget_card_per_page',
                    'display_name' => 'Cards per page',
                    'value' => '2',
                    'details' => NULL,
                    'type' => 'text',
                    'order' => 200,
                    'group' => 'Feed',
                ),
                array (
                    'key' => 'feed.expired_subs_widget_total_cards',
                    'display_name' => 'Total cards',
                    'value' => '8',
                    'details' => NULL,
                    'type' => 'text',
                    'order' => 190,
                    'group' => 'Feed',
                ),
        ));

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'feed.search_widget_hide',
                    'display_name' => 'Hide the search widget',
                    'value' => 0,
                    'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, the search widget will be hidden from the feed page."
                        }',
                    'type' => 'checkbox',
                    'order' => 210,
                    'group' => 'Feed',
                )
            )
        );

        // Merge the 'Social login' and 'Social links' groups into 'Social'
        DB::table('settings')
            ->whereIn('group', ['Social login', 'Social links'])
            ->update(['group' => 'Social']);

        // Update keys starting with 'social-login.' to 'social.'
        DB::table('settings')
            ->where('key', 'like', 'social-login.%')
            ->update([
                'key' => DB::raw("REPLACE(`key`, 'social-login.', 'social.')"),
            ]);

        // Update keys starting with 'social-links.' to 'social.'
        DB::table('settings')
            ->where('key', 'like', 'social-links.%')
            ->update([
                'key' => DB::raw("REPLACE(`key`, 'social-links.', 'social.')"),
            ]);

        DB::table('settings')
            ->whereIn('key', [
                'feed.show_attachments_counts_for_ppv',
            ])
            ->delete();
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
                'security.captcha_driver',
                'security.turnstile_site_key',
                'security.turnstile_site_secret_key',
                'security.hcaptcha_site_key',
                'security.hcaptcha_site_secret_key',
                'profiles.allow_hyperlinks',
                'media.use_blurred_previews_for_locked_posts',
                'feed.expired_subs_widget_autoplay',
                'feed.expired_subs_widget_card_per_page',
                'feed.expired_subs_widget_total_cards',
                'feed.expired_subs_widget_hide',
                'feed.search_widget_hide'
            ])
            ->delete();

        DB::table('settings')->insert(
            array(
                array (
                    'key' => 'security.recaptcha_enabled',
                    'display_name' => 'Enable Google reCAPTCHA',
                    'value' => NULL,
                    'details' => '{
"on" : "On",
"off" : "Off",
"checked" : false,
"description": "If enabled, it will be used on all public form pages."
}',
                    'type' => 'checkbox',
                    'order' => 1200,
                    'group' => 'Security',
                ),
            )
        );


        DB::table('settings')->insert(
        array(
            'key' => 'profiles.allow_profile_bio_markdown_links',
            'display_name' => 'Allow hyperlinks in the markdown formatting',
            'value' => '0',
            'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : true,
                        "description": "If enabled, users will be able to post links within their descriptions."
                    }',
            'type' => 'checkbox',
            'order' => 50,
            'group' => 'Profiles',
        )
        );

        DB::table('settings')
            ->where('key', 'profiles.allow_profile_bio_markdown')
            ->update([
                'display_name' => 'Allow users to use markdown in profile description',
            ]);

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn('has_blurred_preview');
        });

        DB::table('settings')
            ->where('key', 'feed.feed_posts_per_page')
            ->update([
                'order' => '2',
            ]);

        DB::table('settings')
            ->where('key', 'feed.min_post_description')
            ->update([
                'order' => '3',
            ]);

        DB::table('settings')
            ->where('key', 'feed.post_box_max_height')
            ->update([
                'order' => '4',
            ]);

        DB::table('settings')
            ->where('key', 'feed.allow_post_scheduling')
            ->update([
                'order' => '5',
            ]);

        DB::table('settings')
            ->where('key', 'feed.enable_post_description_excerpts')
            ->update([
                'order' => '5',
            ]);

        DB::table('settings')
            ->where('key', 'feed.allow_gallery_zoom')
            ->update([
                'order' => '7',
            ]);

        DB::table('settings')
            ->where('key', 'feed.hide_suggestions_slider')
            ->update([
                'order' => '35',
            ]);

        DB::table('settings')
            ->where('key', 'feed.suggestions_skip_empty_profiles')
            ->update([
                'order' => '40',
            ]);

        DB::table('settings')
            ->where('key', 'feed.suggestions_skip_unverified_profiles')
            ->update([
                'order' => '50',
            ]);

        DB::table('settings')
            ->where('key', 'feed.suggestions_use_featured_users_list')
            ->update([
                'order' => '60',
            ]);

        DB::table('settings')
            ->where('key', 'feed.feed_suggestions_autoplay')
            ->update([
                'order' => '70',
            ]);

        DB::table('settings')
            ->where('key', 'feed.feed_suggestions_total_cards')
            ->update([
                'order' => '80',
            ]);

        DB::table('settings')
            ->where('key', 'feed.feed_suggestions_card_per_page')
            ->update([
                'order' => '90',
            ]);

        DB::table('settings')
            ->where('key', 'feed.default_search_widget_filter')
            ->update([
                'order' => '100',
            ]);

        DB::table('settings')
            ->where('key', 'feed.disable_posts_text_preview')
            ->update([
                'order' => '110',
            ]);

        // Revert keys and groups for 'social-login' settings
        DB::table('settings')
            ->whereIn('key', [
                'social.facebook_client_id',
                'social.facebook_secret',
                'social.twitter_client_id',
                'social.twitter_secret',
                'social.google_client_id',
                'social.google_secret',
            ])
            ->update([
                'key' => DB::raw("REPLACE(`key`, 'social.', 'social-login.')"),
                'group' => 'Social login',
            ]);

        // Revert keys and groups for 'social-links' settings
        DB::table('settings')
            ->whereIn('key', [
                'social.facebook_url',
                'social.instagram_url',
                'social.twitter_url',
                'social.whatsapp_url',
                'social.tiktok_url',
                'social.youtube_url',
                'social.telegram_link',
                'social.reddit_url',
            ])
            ->update([
                'key' => DB::raw("REPLACE(`key`, 'social.', 'social-links.')"),
                'group' => 'Social links',
            ]);

        DB::table('settings')->insert(array (
            array (
                'key' => 'feed.show_attachments_counts_for_ppv',
                'display_name' => 'Show attachments counts for PPV posts',
                'details' => '{
"on" : "On",
"off" : "Off",
"checked" : true,
}',
                'value' => '0',
                'type' => 'checkbox',
                'order' => 70,
                'group' => 'Feed',
            )));

    }
};
