<?php

use Illuminate\Database\Seeder;
use Database\Seeders\InsertCountries;
use Database\Seeders\PublicPagesTableSeeder;
use Database\Seeders\PermissionsTableSeeder;
use Database\Seeders\RolesTableSeeder;
use Database\Seeders\ModelHasPermissionsTableSeeder;
use Database\Seeders\ModelHasRolesTableSeeder;
use Database\Seeders\RoleHasPermissionsTableSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $this->call(InsertCountries::class);
        $this->call(PublicPagesTableSeeder::class);
        $this->call(PermissionsTableSeeder::class);
        $this->call(RolesTableSeeder::class);
        $this->call(ModelHasPermissionsTableSeeder::class);
        $this->call(RoleHasPermissionsTableSeeder::class);
        $this->call(Database\Seeders\ConversionTrackingPermissionsSeeder::class);
        $this->call(Database\Seeders\SystemHealthPermissionsSeeder::class);
        $this->call(Database\Seeders\BanUserPermissionsSeeder::class);
        $this->call(Database\Seeders\ContentReviewPermissionsSeeder::class);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
