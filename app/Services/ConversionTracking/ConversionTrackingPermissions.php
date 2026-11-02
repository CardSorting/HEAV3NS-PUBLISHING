<?php

namespace App\Services\ConversionTracking;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConversionTrackingPermissions
{
    public const ALL = [
        'ViewAny:ConversionDelivery', 'View:ConversionDelivery', 'Create:ConversionDelivery',
        'Update:ConversionDelivery', 'Delete:ConversionDelivery',
    ];
    public const READ_ONLY = ['ViewAny:ConversionDelivery', 'View:ConversionDelivery'];

    /** Add only this resource's permissions; never replace unrelated role permissions. */
    public static function grant(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_has_permissions')) {
            return;
        }
        foreach (self::ALL as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name, 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $permissions = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::ALL)->pluck('id', 'name');
        $roles = DB::table('roles')->where('guard_name', 'web')
            ->where(fn ($query) => $query->whereIn('id', [1, 3])->orWhereIn('name', ['admin', 'demo']))
            ->get(['id', 'name']);

        foreach ($roles as $role) {
            $readOnly = (int) $role->id === 3 || $role->name === 'demo';
            foreach ($readOnly ? self::READ_ONLY : self::ALL as $name) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'role_id' => $role->id, 'permission_id' => $permissions[$name],
                ]);
            }
            if ($readOnly) {
                // Keep the built-in demo role read-only even if a previous seed granted all permissions.
                DB::table('role_has_permissions')->where('role_id', $role->id)
                    ->whereIn('permission_id', $permissions->except(self::READ_ONLY)->values())->delete();
            }
        }
        Artisan::call('permission:cache-reset');
    }

    public static function remove(): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', self::ALL)->pluck('id');
        foreach (['role_has_permissions', 'model_has_permissions'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereIn('permission_id', $ids)->delete();
            }
        }
        DB::table('permissions')->whereIn('id', $ids)->delete();
        Artisan::call('permission:cache-reset');
    }
}
