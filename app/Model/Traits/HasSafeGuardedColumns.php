<?php

namespace App\Model\Traits;

/**
 * Laravel's default guarded-attribute check (GuardsAttributes::isGuardableColumn)
 * queries information_schema.columns via the schema builder, which always selects
 * generation_expression. MariaDB < 10.2 and MySQL < 5.7.6 don't expose that column,
 * so any model using $guarded (instead of $fillable or $guarded = []) fails on first
 * mass assignment. Mirror the trimmed information_schema query already used in
 * AttachmentPlacementMigration instead of the framework's native introspection.
 */
trait HasSafeGuardedColumns
{
    protected function isGuardableColumn($key)
    {
        if ($this->hasSetMutator($key) || $this->hasAttributeSetMutator($key) || $this->isClassCastable($key)) {
            return true;
        }

        if (!isset(static::$guardableColumns[get_class($this)])) {
            $connection = $this->getConnection();

            if ($connection->getDriverName() === 'sqlite') {
                $columns = $connection->getSchemaBuilder()->getColumnListing($this->getTable());
            } else {
                $query = $connection->table('information_schema.columns')
                    ->where('table_name', $connection->getTablePrefix().$this->getTable());
                $connection->getDriverName() === 'pgsql'
                    ? $query->whereRaw('table_schema = current_schema()')
                    : $query->whereRaw('table_schema = DATABASE()');
                // MySQL/MariaDB and PDO settings can return metadata labels in either case.
                $columns = $query->get(['column_name'])->map(
                    fn ($column) => array_change_key_case((array) $column, CASE_LOWER)['column_name']
                )->all();
            }

            if (empty($columns)) {
                return true;
            }

            static::$guardableColumns[get_class($this)] = $columns;
        }

        return in_array($key, static::$guardableColumns[get_class($this)]);
    }
}
