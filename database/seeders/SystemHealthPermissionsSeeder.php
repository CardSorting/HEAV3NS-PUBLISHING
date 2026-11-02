<?php

namespace Database\Seeders;

use App\Support\MigrationSchema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class SystemHealthPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (!MigrationSchema::hasTable('permissions') || !MigrationSchema::hasTable('roles') || !MigrationSchema::hasTable('role_has_permissions')) {
            return;
        }

        // Query builder writes avoid Eloquent guarded-column introspection, which older MySQL/MariaDB servers reject.
        $adminId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'web')->value('id');
        foreach (['View:SystemHealth', \App\Services\SiteMaintenanceService::PERMISSION] as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $permissionId = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->value('id');
            if ($adminId) {
                DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $adminId, 'permission_id' => $permissionId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
