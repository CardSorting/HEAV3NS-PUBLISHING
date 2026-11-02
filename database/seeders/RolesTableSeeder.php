<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    /**
     * Seed the built-in roles.
     *
     * @return void
     */
    public function run()
    {
        DB::table('roles')->delete();

        DB::table('roles')->insert([
            [
                'id' => 1,
                'name' => 'admin',
                'guard_name' => 'web',
                'created_at' => '2025-06-15 21:26:45',
                'updated_at' => '2025-06-15 21:26:45',
            ],
            [
                'id' => 2,
                'name' => 'user',
                'guard_name' => 'web',
                'created_at' => '2025-06-15 21:27:04',
                'updated_at' => '2025-06-15 21:27:04',
            ],
            [
                'id' => 3,
                'name' => 'demo',
                'guard_name' => 'web',
                'created_at' => '2025-07-28 11:57:56',
                'updated_at' => '2025-07-28 11:57:56',
            ],
        ]);
    }
}
