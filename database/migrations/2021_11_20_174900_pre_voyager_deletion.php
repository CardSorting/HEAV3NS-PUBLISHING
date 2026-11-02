<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PreVoyagerDeletion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create fake voyager settings table, so it can be migrated during the installation process
        // EG: During some later migration, we're using getSetting(), which will still rely on fake-voyager code/table until we fully migrate
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->increments('id');
                $table->string('key', 191)->unique();
                $table->string('display_name', 191);
                $table->text('value')->nullable();
                $table->text('details')->nullable();
                $table->string('type', 191);
                $table->integer('order')->default(1);
                $table->string('group', 191)->nullable();
            });
        }

        // Voyager's user settings field will be kept I suppose
        Schema::table('users', function ($table) {
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('email')->default('users/default.png');
            }
            if (!Schema::hasColumn('users', 'role_id')) {
                $table->bigInteger('role_id')->nullable()->after('id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'settings')) {
                $table->text('settings')->nullable()->default(null)->after('remember_token');
            }
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settings');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('settings');
            $table->dropColumn('role_id');
        });
    }
}
