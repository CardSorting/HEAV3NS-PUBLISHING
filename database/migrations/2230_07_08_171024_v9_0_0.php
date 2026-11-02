<?php

use App\Model\User;
use App\Providers\InstallerServiceProvider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class V900 extends Migration
{
    /**
     * Post-admin panel migrations.
     */
    public function up(): void
    {

        if (app()->bound('pretend_migration')) {
            return;
        }

        // Resetting breads per latest changes
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Artisan::call('db:seed', ['--force'=>true, '--class'=>'Database\Seeders\PermissionsTableSeeder']);
        Artisan::call('db:seed', ['--force'=>true, '--class'=>'Database\Seeders\RolesTableSeeder']);
        Artisan::call('db:seed', ['--force'=>true, '--class'=>'Database\Seeders\ModelHasPermissionsTableSeeder']);
        Artisan::call('db:seed', ['--force'=>true, '--class'=>'Database\Seeders\RoleHasPermissionsTableSeeder']);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Updating spatie roles from old ones
        foreach (User::cursor() as $user) {
            $role = Role::find($user->role_id);
            if ($role && !$user->hasRole($role->name)) {
                $user->syncRoles([$role->name]);
            }
        }

        // New settings table
        Schema::rename('settings', 'settings_old');
        Schema::rename('settings_new', 'settings');

        // Old voyager schema
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::dropIfExists('data_types');
        Schema::dropIfExists('data_rows');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('user_roles');
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // This one runs in --pretend if the pretend_migration env var is not set
        InstallerServiceProvider::appendToEnv('ADMIN_VERSION="v2"');
        // Later migrations in this request must see the completed settings cutover.
        Config::set('settings.admin_version', 'v2');
        Config::set('settings.repositories.database.table', 'settings');
        Artisan::call('optimize:clear');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        if (config("settings.admin_version") === 'v1') {
//            Config::set('settings.repositories.database.table', 'settings_old');
//        }
        Config::set('settings.admin_version', 'v1');
        Config::set('settings.repositories.database.table', 'settings_new');
        Schema::rename('settings', 'settings_new');
        Schema::rename('settings_old', 'settings');
        InstallerServiceProvider::appendToEnv('ADMIN_VERSION="v1"');
        Artisan::call('optimize:clear');
    }
}
