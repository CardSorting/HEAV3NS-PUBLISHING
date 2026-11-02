<?php

namespace App\Services\Media;

use App\Providers\AttachmentServiceProvider;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/** Query-builder-only migration support: library metadata must not change media timestamps. */
class AttachmentPlacementMigration
{
    public const PARENTS = [
        'post_id' => ['table' => 'posts', 'owner' => 'user_id'],
        'message_id' => ['table' => 'user_messages', 'owner' => 'sender_id'],
        'message_template_id' => ['table' => 'message_templates', 'owner' => 'user_id'],
        'story_id' => ['table' => 'stories', 'owner' => 'user_id'],
        'reel_id' => ['table' => 'reels', 'owner' => 'user_id'],
    ];

    // Keep this explicit: the legacy type helper treats unknown extensions as images.
    private const SOCIAL_MEDIA_TYPES = [
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'avi', 'mp4', 'wmw', 'mpeg', 'm4v', 'moov', 'mov', 'mkv', 'wmv', 'asf',
        'mp3', 'wav', 'ogg', 'm4a', 'webm',
    ];

    private Connection $database;

    public function __construct(?ConnectionInterface $database = null)
    {
        /** @var Connection $connection */
        $connection = $database ?? DB::connection();
        $this->database = $connection;
    }

    public function up(): void
    {
        $this->backfill();
        $this->dropLegacyColumns();
    }

    /** No storage reads unless explicitly requested; missing files are reported without erasing their references. */
    public function preflight(bool $checkFiles = false): array
    {
        $columns = $this->legacyColumns();
        $report = [
            'legacy_columns' => $columns,
            'constraint_mode' => $this->supportsParentChecks() ? 'database_check_and_application' : 'application_validation',
            'social_assets' => 0,
            'multi_parent_assets' => 0,
            'associations' => array_fill_keys($columns, 0),
            'blocking_issues' => [],
            'warnings' => [],
            'samples' => [],
            'ownership_repairs' => [],
            'files_checked' => 0,
        ];

        if (!$this->schema()->hasTable('jobs')) {
            $this->issue($report, 'missing_cleanup_jobs_table', 'jobs');
        }

        if ($columns === []) {
            return $report;
        }

        $protectedFiles = $this->protectedFiles($report);
        $query = $this->database->table('attachments as a')->select('a.*')
            ->where(function ($query) use ($columns): void {
                foreach ($columns as $column) {
                    $query->orWhereNotNull('a.'.$column);
                }
            });
        if ($this->schema()->hasTable('attachment_placements')) {
            $query->addSelect(['_has_placements' => $this->database->table('attachment_placements as migrated_placement')
                ->selectRaw('1')->whereColumn('migrated_placement.attachment_id', 'a.id')->limit(1)]);
        }

        foreach ($columns as $column) {
            $parent = self::PARENTS[$column];
            if (!$this->schema()->hasTable($parent['table'])) {
                throw new RuntimeException('Missing parent table required for media migration: '.$parent['table']);
            }
            $alias = 'parent_'.$column;
            $query->leftJoin($parent['table'].' as '.$alias, $alias.'.id', '=', 'a.'.$column)
                ->leftJoin('users as owner_'.$column, 'owner_'.$column.'.id', '=', $alias.'.'.$parent['owner'])
                ->addSelect(
                    $alias.'.id as _exists_'.$column,
                    $alias.'.'.$parent['owner'].' as _owner_'.$column,
                    'owner_'.$column.'.id as _owner_exists_'.$column
                );
        }

        $query->chunkById(500, function ($attachments) use (&$report, $columns, $protectedFiles, $checkFiles): void {
            foreach ($attachments as $attachment) {
                $report['social_assets']++;
                $assignedParents = 0;
                foreach ($columns as $column) {
                    if ($attachment->{$column} === null) {
                        continue;
                    }
                    $assignedParents++;
                    $report['associations'][$column]++;
                }
                $repair = $this->inspectLegacyOwnership($attachment, $columns, $protectedFiles, $report);
                if ($repair !== null) {
                    $report['ownership_repairs'][] = $repair;
                    $this->issue(
                        $report,
                        'ownership_repair',
                        (string) $attachment->id,
                        ($repair['previous_user_id'] ?? 'NULL').' -> '.$repair['new_user_id'],
                        false
                    );
                }
                $report['multi_parent_assets'] += $assignedParents > 1 ? 1 : 0;
                if ($checkFiles) {
                    $report['files_checked']++;
                    try {
                        $disk = AttachmentServiceProvider::getStorageProviderName($attachment->driver);
                        if (!Storage::disk($disk)->exists($attachment->filename)) {
                            $this->issue($report, 'missing_file', (string) $attachment->id, '', false);
                        }
                    } catch (Throwable) {
                        $this->issue($report, 'storage_check_failed', (string) $attachment->id, '', false);
                    }
                }
            }
        }, 'a.id', 'id');

        return $report;
    }

    public function createSchema(): void
    {
        foreach (['vaulted_at', 'vault_archived_at'] as $column) {
            if (!$this->hasColumn('attachments', $column)) {
                $this->schema()->table('attachments', fn (Blueprint $table) => $table->timestamp($column)->nullable());
            }
        }
        if (!$this->schema()->hasIndex('attachments', 'att_owner_vault_idx')) {
            $this->schema()->table('attachments', fn (Blueprint $table) => $table->index(
                ['user_id', 'vault_archived_at', 'vaulted_at', 'id'],
                'att_owner_vault_idx',
            ));
        }

        if (!$this->schema()->hasTable('attachment_placements')) {
            if ($this->database->getDriverName() === 'sqlite') {
                // SQLite only accepts a table CHECK during CREATE, not through ADD CONSTRAINT.
                $this->createSqlitePlacements();
            } else {
                $this->schema()->create('attachment_placements', function (Blueprint $table): void {
                    $table->bigIncrements('id');
                    $table->uuid('attachment_id');
                    foreach (self::PARENTS as $column => $parent) {
                        $table->unsignedBigInteger($column)->nullable();
                        $table->foreign($column, 'ap_'.$column.'_fk')->references('id')->on($parent['table'])->cascadeOnDelete();
                    }
                    $table->unsignedInteger('position')->default(0);
                    $table->timestamps();
                    $table->foreign('attachment_id', 'ap_attachment_fk')->references('id')->on('attachments')->restrictOnDelete();
                });
            }
        }

        if (!$this->schema()->hasIndex('attachment_placements', 'ap_attachment_idx')) {
            $this->schema()->table('attachment_placements', fn (Blueprint $table) => $table->index('attachment_id', 'ap_attachment_idx'));
        }
        foreach (self::PARENTS as $column => $parent) {
            if (!$this->schema()->hasIndex('attachment_placements', 'ap_'.$column.'_asset_uq')) {
                $this->schema()->table('attachment_placements', fn (Blueprint $table) => $table->unique(
                    [$column, 'attachment_id'],
                    'ap_'.$column.'_asset_uq',
                ));
            }
            if (!$this->schema()->hasIndex('attachment_placements', 'ap_'.$column.'_position_idx')) {
                $this->schema()->table('attachment_placements', fn (Blueprint $table) => $table->index(
                    [$column, 'position', 'id'],
                    'ap_'.$column.'_position_idx',
                ));
            }
        }
        $this->addDatabaseChecks();
    }

    public function backfill(): void
    {
        $report = $this->preflight();
        $this->assertClean($report);
        $this->createSchema();
        $this->repairLegacyOwnership($report['ownership_repairs']);
        foreach ($this->legacyColumns() as $column) {
            $this->eachLegacyParent($column, function ($parentId) use ($column): void {
                $position = 0;
                $this->database->table('attachments')->where($column, $parentId)
                    ->orderBy('created_at')->orderBy('id')
                    ->chunk(500, function ($attachments) use ($column, $parentId, &$position): void {
                        $rows = [];
                        foreach ($attachments as $attachment) {
                            $rows[] = array_merge(array_fill_keys(array_keys(self::PARENTS), null), [
                                'attachment_id' => $attachment->id,
                                $column => $parentId,
                                'position' => $position++,
                                'created_at' => $attachment->created_at,
                                'updated_at' => $attachment->updated_at,
                            ]);
                        }
                        $this->database->transaction(function () use ($rows, $column, $attachments): void {
                            $this->database->table('attachment_placements')->upsert($rows, [$column, 'attachment_id'], ['position']);
                            $this->database->table('attachments')->whereIn('id', $attachments->pluck('id'))
                                ->whereNull('vaulted_at')
                                ->update(['vaulted_at' => $this->database->raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
                        });
                    });
            });
        }
    }

    /** Compare both directions and the recorded order before any legacy ownership can disappear. */
    public function verify(): void
    {
        $report = $this->preflight();
        $this->assertClean($report);
        if ($report['ownership_repairs'] !== []) {
            throw new RuntimeException('Attachment ownership repairs remain unapplied. Legacy ownership was not removed.');
        }
        if (!$this->schema()->hasTable('attachment_placements')) {
            throw new RuntimeException('The attachment placements table does not exist. Legacy ownership was not removed.');
        }
        if ($this->database->table('attachment_placements')->whereRaw('('.$this->parentCountExpression().') <> 1 OR position < 0')->exists()) {
            throw new RuntimeException('Invalid placement parents or position. Legacy ownership was not removed.');
        }
        foreach ($this->legacyColumns() as $column) {
            $missing = $this->database->table('attachments as a')
                ->leftJoin('attachment_placements as p', function ($join) use ($column): void {
                    $join->on('p.attachment_id', '=', 'a.id')->on('p.'.$column, '=', 'a.'.$column);
                })->whereNotNull('a.'.$column)->whereNull('p.id')->exists();
            $unexpected = $this->database->table('attachment_placements as p')
                ->leftJoin('attachments as a', 'a.id', '=', 'p.attachment_id')
                ->whereNotNull('p.'.$column)->where(function ($query) use ($column): void {
                    $query->whereNull('a.'.$column)->orWhereColumn('a.'.$column, '!=', 'p.'.$column);
                })->exists();
            if ($missing || $unexpected) {
                throw new RuntimeException('Placement associations do not match attachments.'.$column.'. Legacy ownership was not removed.');
            }
            $this->eachLegacyParent($column, function ($parentId) use ($column): void {
                $position = 0;
                $this->database->table('attachments as a')
                    ->join('attachment_placements as p', function ($join) use ($column): void {
                        $join->on('p.attachment_id', '=', 'a.id')->on('p.'.$column, '=', 'a.'.$column);
                    })->where('a.'.$column, $parentId)->select('p.position', 'a.vaulted_at')
                    ->orderBy('a.created_at')->orderBy('a.id')
                    ->chunk(500, function ($placements) use (&$position, $column): void {
                        foreach ($placements as $placement) {
                            if ((int) $placement->position !== $position++ || $placement->vaulted_at === null) {
                                throw new RuntimeException('Placement order or Vault membership is incomplete for '.$column.'. Legacy ownership was not removed.');
                            }
                        }
                    });
            });
        }
    }

    public function dropLegacyColumns(): void
    {
        $this->verify();
        $columns = $this->legacyColumns();
        if ($columns === []) {
            return;
        }
        $foreignKeys = array_filter($this->schema()->getForeignKeys('attachments'), fn ($foreign) => array_intersect($foreign['columns'], $columns) !== []);
        $this->schema()->table('attachments', function (Blueprint $table) use ($columns, $foreignKeys): void {
            foreach ($foreignKeys as $foreign) {
                $table->dropForeign($this->database->getDriverName() === 'sqlite' ? $foreign['columns'] : $foreign['name']);
            }
            $table->dropColumn($columns);
        });
    }

    public function supportsEnforcedChecks(): bool
    {
        $driver = $this->database->getDriverName();
        $version = in_array($driver, ['mysql', 'mariadb'], true)
            ? $this->databaseVersion()
            : '';

        return self::supportsChecksForVersion($driver, $version);
    }

    public static function supportsChecksForVersion(string $driver, string $version): bool
    {
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            return true;
        }
        if ($driver === 'mariadb' || stripos($version, 'MariaDB') !== false) {
            preg_match('/(\d+\.\d+\.\d+)(?=-MariaDB)/i', $version, $matches);
            return version_compare($matches[1] ?? $version, '10.2.1', '>=');
        }

        return $driver === 'mysql' && version_compare($version, '8.0.16', '>=');
    }

    public function supportsParentChecks(): bool
    {
        $driver = $this->database->getDriverName();
        $version = in_array($driver, ['mysql', 'mariadb'], true)
            ? $this->databaseVersion()
            : '';

        return self::supportsParentChecksForVersion($driver, $version);
    }

    public static function supportsParentChecksForVersion(string $driver, string $version): bool
    {
        // Oracle MySQL prohibits CHECK expressions on columns with FK referential actions.
        // Preserve ON DELETE CASCADE and use the approved application/model rule there.
        // https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html
        return self::supportsChecksForVersion($driver, $version)
            && ($driver !== 'mysql' || stripos($version, 'MariaDB') !== false);
    }

    private function databaseVersion(): string
    {
        $row = $this->database->selectOne('SELECT VERSION() AS version');

        return (string) array_change_key_case((array) $row, CASE_LOWER)['version'];
    }

    /** Only a unanimous, existing social owner can authorize this one-time legacy correction. */
    private function inspectLegacyOwnership(object $attachment, array $columns, array $protectedFiles, array &$report): ?array
    {
        $id = (string) $attachment->id;
        $issuesBefore = array_sum($report['blocking_issues']);
        if (($attachment->payment_request_id ?? null) !== null || ($attachment->sound_id ?? null) !== null
            || preg_match('~^(payment-request|sounds)/~', ltrim((string) $attachment->filename, '/'))) {
            $this->issue($report, 'operational_social_collision', $id);
        }
        if (isset($protectedFiles[(string) $attachment->filename])
            || preg_match('~^users/(verifications|release-forms)/~', ltrim((string) $attachment->filename, '/'))) {
            $this->issue($report, 'document_social_collision', $id);
        }
        if (!in_array((string) $attachment->type, self::SOCIAL_MEDIA_TYPES, true)) {
            $this->issue($report, 'unsupported_social_media_type', $id);
        }

        $parents = [];
        $owners = [];
        foreach ($columns as $column) {
            if ($attachment->{$column} === null) {
                continue;
            }
            if ($attachment->{'_exists_'.$column} === null) {
                $this->issue($report, 'missing_parent', $id, $column);
                continue;
            }
            if ($attachment->{'_owner_exists_'.$column} === null) {
                $this->issue($report, 'missing_parent_owner', $id, $column);
                continue;
            }
            $ownerId = (int) $attachment->{'_owner_'.$column};
            $owners[$ownerId] = $ownerId;
            $parents[$column] = ['id' => (int) $attachment->{$column}, 'owner_id' => $ownerId];
        }
        if (count($owners) > 1) {
            $this->issue($report, 'conflicting_parent_owners', $id, json_encode($parents));
        }
        if (array_sum($report['blocking_issues']) !== $issuesBefore || count($owners) !== 1) {
            return null;
        }
        $newOwner = reset($owners);
        if ($attachment->user_id !== null && (int) $attachment->user_id === $newOwner) {
            return null;
        }
        // Once saved to the Vault or placed, an asset must not be reassigned on retry.
        if (($attachment->vaulted_at ?? null) !== null || ($attachment->_has_placements ?? false)) {
            $this->issue($report, 'migrated_owner_mismatch', $id);
            return null;
        }

        return [
            'attachment_id' => $id,
            'previous_user_id' => $attachment->user_id === null ? null : (int) $attachment->user_id,
            'new_user_id' => $newOwner,
            'parents' => $parents,
        ];
    }

    private function repairLegacyOwnership(array $repairs): void
    {
        if ($repairs === []) {
            return;
        }
        $columns = $this->legacyColumns();
        $this->database->transaction(function () use ($repairs, $columns): void {
            $report = ['blocking_issues' => [], 'warnings' => [], 'samples' => []];
            $protectedFiles = $this->protectedFiles($report);
            $this->assertClean($report);
            foreach ($repairs as $repair) {
                $attachment = $this->database->table('attachments')->where('id', $repair['attachment_id'])->lockForUpdate()->first();
                if ($attachment === null) {
                    throw new RuntimeException('Attachment changed during ownership repair: '.$repair['attachment_id'].'. Keep writers paused and rerun the preflight.');
                }
                // Recheck authoritative parents under locks; never apply a stale preflight decision.
                foreach ($columns as $column) {
                    $parent = self::PARENTS[$column];
                    $record = $attachment->{$column} === null ? null : $this->database->table($parent['table'])
                        ->where('id', $attachment->{$column})->lockForUpdate()->first();
                    $ownerId = $record?->{$parent['owner']};
                    $owner = $ownerId === null ? null : $this->database->table('users')->where('id', $ownerId)->lockForUpdate()->first(['id']);
                    $attachment->{'_exists_'.$column} = $record?->id;
                    $attachment->{'_owner_'.$column} = $ownerId;
                    $attachment->{'_owner_exists_'.$column} = $owner?->id;
                }
                $attachment->_has_placements = $this->database->table('attachment_placements')
                    ->where('attachment_id', $repair['attachment_id'])->exists();
                $currentRepair = $this->inspectLegacyOwnership($attachment, $columns, $protectedFiles, $report);
                $this->assertClean($report);
                if ($currentRepair !== $repair) {
                    throw new RuntimeException('Attachment ownership or parents changed during repair: '.$repair['attachment_id'].'. Keep writers paused and rerun the preflight.');
                }
                $this->database->table('attachments')->where('id', $repair['attachment_id'])->update(['user_id' => $repair['new_user_id']]);
            }
        });
    }

    private function protectedFiles(array &$report): array
    {
        $files = [];
        foreach (['user_verifies', 'release_forms'] as $table) {
            if (!$this->schema()->hasTable($table)) {
                continue;
            }
            $this->database->table($table)->select(['id', 'files'])->chunkById(500, function ($records) use (&$files, &$report, $table): void {
                foreach ($records as $record) {
                    if ($record->files === null || $record->files === '') {
                        continue;
                    }
                    $decoded = is_array($record->files) ? $record->files : json_decode($record->files, true);
                    if (!is_array($decoded)) {
                        $this->issue($report, 'unreadable_document_references', (string) $record->id, $table);
                        continue;
                    }
                    foreach ($decoded as $filename) {
                        if (is_string($filename) && $filename !== '') {
                            $files[$filename] = true;
                        } elseif (!is_string($filename)) {
                            $this->issue($report, 'unreadable_document_references', (string) $record->id, $table);
                            break;
                        }
                    }
                }
            });
        }

        return $files;
    }

    private function issue(array &$report, string $reason, string $id, string $context = '', bool $blocking = true): void
    {
        $key = $blocking ? 'blocking_issues' : 'warnings';
        $report[$key][$reason] = ($report[$key][$reason] ?? 0) + 1;
        if (count($report['samples']) < 50) {
            $report['samples'][] = ['reason' => $reason, 'id' => $id, 'context' => $context, 'blocking' => $blocking];
        }
    }

    private function assertClean(array $report): void
    {
        if ($report['blocking_issues'] !== []) {
            throw new RuntimeException('Media Vault migration blocked: '.json_encode($report['blocking_issues']).'. Run media:placements-preflight for record samples. Legacy ownership was not removed.');
        }
    }

    private function eachLegacyParent(string $column, callable $callback): void
    {
        $this->database->table('attachments')->select($column)->whereNotNull($column)->distinct()
            ->chunkById(100, function ($parents) use ($column, $callback): void {
                foreach ($parents as $parent) {
                    $callback($parent->{$column});
                }
            }, $column);
    }

    private function legacyColumns(): array
    {
        if (!$this->schema()->hasTable('attachments')) {
            throw new RuntimeException('The attachments table must exist before migrating media placements.');
        }

        // Laravel's MySQL schema grammar selects generation_expression from
        // information_schema.columns. Older MySQL/MariaDB versions do not
        // expose that column, so use the smallest compatible query here.
        if ($this->database->getDriverName() === 'sqlite') {
            $columns = $this->schema()->getColumnListing('attachments');
        } else {
            $query = $this->database->table('information_schema.columns')
                ->where('table_name', 'attachments');
            $this->database->getDriverName() === 'pgsql'
                ? $query->whereRaw('table_schema = current_schema()')
                : $query->whereRaw('table_schema = DATABASE()');
            $columns = $query->get(['column_name'])->map(
                fn ($column) => array_change_key_case((array) $column, CASE_LOWER)['column_name']
            )->all();
        }

        return array_values(array_intersect(array_keys(self::PARENTS), $columns));
    }

    private function hasColumn(string $table, string $column): bool
    {
        if ($this->database->getDriverName() === 'sqlite') {
            return $this->schema()->hasColumn($table, $column);
        }

        $query = $this->database->table('information_schema.columns')
            ->where('table_name', $table)
            ->where('column_name', $column);
        $this->database->getDriverName() === 'pgsql'
            ? $query->whereRaw('table_schema = current_schema()')
            : $query->whereRaw('table_schema = DATABASE()');

        return $query->exists();
    }

    private function schema()
    {
        return $this->database->getSchemaBuilder();
    }

    private function parentCountExpression(): string
    {
        return implode(' + ', array_map(fn ($column) => 'CASE WHEN '.$column.' IS NOT NULL THEN 1 ELSE 0 END', array_keys(self::PARENTS)));
    }

    private function createSqlitePlacements(): void
    {
        $grammar = $this->database->getQueryGrammar();
        $parents = implode(', ', array_map(fn ($column) => $column.' INTEGER NULL', array_keys(self::PARENTS)));
        $foreignKeys = '';
        foreach (self::PARENTS as $column => $parent) {
            $foreignKeys .= ', FOREIGN KEY ('.$column.') REFERENCES '.$grammar->wrapTable($parent['table']).' (id) ON DELETE CASCADE';
        }
        $this->database->statement('CREATE TABLE '.$grammar->wrapTable('attachment_placements').' (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            attachment_id VARCHAR NOT NULL, '.$parents.',
            position INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME NULL, updated_at DATETIME NULL,
            CONSTRAINT ap_one_parent_chk CHECK ('.$this->parentCountExpression().' = 1),
            CONSTRAINT ap_position_chk CHECK (position >= 0),
            FOREIGN KEY (attachment_id) REFERENCES '.$grammar->wrapTable('attachments').' (id) ON DELETE RESTRICT'.$foreignKeys.'
        )');
    }

    private function addDatabaseChecks(): void
    {
        if ($this->database->getDriverName() === 'sqlite' || !$this->supportsEnforcedChecks()) {
            return;
        }
        $table = $this->database->getTablePrefix().'attachment_placements';
        foreach (['ap_one_parent_chk' => $this->parentCountExpression().' = 1', 'ap_position_chk' => 'position >= 0'] as $name => $expression) {
            if ($name === 'ap_one_parent_chk' && !$this->supportsParentChecks()) {
                continue;
            }
            $query = $this->database->table('information_schema.table_constraints')
                ->where('table_name', $table)->where('constraint_name', $name)->where('constraint_type', 'CHECK');
            $this->database->getDriverName() === 'pgsql'
                ? $query->whereRaw('table_schema = current_schema()')
                : $query->whereRaw('table_schema = DATABASE()');
            if (!$query->exists()) {
                $this->database->statement('ALTER TABLE '.$this->database->getQueryGrammar()->wrapTable('attachment_placements')
                    .' ADD CONSTRAINT '.$name.' CHECK ('.$expression.')');
            }
        }
    }
}
