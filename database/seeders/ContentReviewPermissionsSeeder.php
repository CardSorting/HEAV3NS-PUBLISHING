<?php

namespace Database\Seeders;

use App\Support\MigrationSchema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class ContentReviewPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (!MigrationSchema::hasTable('permissions') || !MigrationSchema::hasTable('roles') || !MigrationSchema::hasTable('role_has_permissions')) {
            return;
        }

        foreach (['ViewAny:ContentReview', 'View:ContentReview', 'Update:ContentReview'] as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            $roles = DB::table('roles')->where('guard_name', 'web')
                ->whereIn('name', $name === 'Update:ContentReview' ? ['admin'] : ['admin', 'demo'])->pluck('id');
            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
