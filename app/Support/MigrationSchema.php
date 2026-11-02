<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Minimal existence checks for migrations and their shared helpers; never cache changing schemas. */
class MigrationSchema
{
    public static function hasTable(string $table): bool
    {
        $connection = DB::connection();
        if (!in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return Schema::getFacadeRoot()->hasTable($table);
        }

        return DB::selectOne(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
            [$connection->getTablePrefix().$table]
        ) !== null;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $connection = DB::connection();
        if (!in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return Schema::getFacadeRoot()->hasColumn($table, $column);
        }

        return DB::selectOne(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$connection->getTablePrefix().$table, $column]
        ) !== null;
    }
}
