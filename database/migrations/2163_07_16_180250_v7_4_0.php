<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class V740 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('global_announcements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->longText('content');
            $table->boolean('is_published')->default(1);
            $table->boolean('is_dismissible')->default(1);
            $table->boolean('is_sticky');
            $table->boolean('is_global');
            $table->string('size');
            $table->dateTime('expiring_at')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert(array(
            array(
                'key' => 'payments.invoices_enabled',
                'display_name' => 'Enables invoices generation',
                'value' => 1,
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, will generate invoices for each payment in the platform."
                        }',
                'type' => 'checkbox',
                'order' => 20,
                'group' => 'Payments',
            )
        ));

        DB::table('settings')->insert(array(
            array(
                'key' => 'site.hide_stream_create_menu',
                'display_name' => 'Hide stream create page',
                'value' => 0,
                'details' => '{
                        "true" : "On",
                        "false" : "Off",
                        "checked" : false,
                        "description": "If enabled, the create stream module link will be hidden from the menus. Useful if running the site on one-creator mode."
                        }',
                'type' => 'checkbox',
                'order' => 187,
                'group' => 'Site',
            )
        ));

        DB::table('settings')
            ->where('key', 'site.hide_identity_checks')
            ->update(['details' => '{
"on" : "On",
"off" : "Off",
"checked" : false,
"description" : "If enabled, the users ID check module link will be hidden from the menus. Useful if running the site on one-creator mode."
}',
                'display_name' => 'Hide identity checks page',
            ]);

        DB::table('settings')
            ->where('key', 'site.hide_create_post_menu')
            ->update([
                'display_name' => 'Hide post create page',
            ]);

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('global_announcements');
        DB::table('settings')
            ->whereIn('key', [
                'payments.invoices_enabled',
                'site.hide_stream_create_menu',
            ])
            ->delete();
    }
};
