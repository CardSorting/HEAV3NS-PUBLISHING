<?php

namespace App\Console\Commands;

use App\Services\Media\AttachmentPlacementMigration;
use Illuminate\Console\Command;
use Throwable;

class MediaPlacementsPreflight extends Command
{
    protected $signature = 'media:placements-preflight
        {--check-files : Also read each social file from its configured storage driver to check existence}
        {--json : Print the report as JSON}';

    protected $description = 'Read-only validation of legacy social attachments before the Vault migration';

    public function handle(AttachmentPlacementMigration $migration): int
    {
        try {
            $report = $migration->preflight((bool) $this->option('check-files'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Read-only media placement preflight; no rows or files were changed.');
            $this->line('Social files: '.$report['social_assets'].'; multi-parent files: '.$report['multi_parent_assets']);
            $this->line('Exactly-one-parent enforcement: '.$report['constraint_mode']);
            $this->table(['Legacy parent column', 'Associations'], collect($report['associations'])->map(fn ($count, $column) => [$column, $count])->values()->all());
            foreach ($report['blocking_issues'] as $reason => $count) {
                $this->error($reason.': '.$count);
            }
            foreach ($report['warnings'] as $reason => $count) {
                $this->warn($reason.': '.$count);
            }
            if ($report['ownership_repairs'] !== []) {
                $this->line('Planned ownership corrections (applied only by the migration after all blockers are resolved):');
                $this->table(['Attachment ID', 'Previous owner', 'New owner', 'Authoritative parents'], array_map(fn ($repair) => [
                    $repair['attachment_id'], $repair['previous_user_id'] ?? 'NULL', $repair['new_user_id'],
                    json_encode($repair['parents'], JSON_UNESCAPED_SLASHES),
                ], $report['ownership_repairs']));
            }
            if ($report['samples'] !== []) {
                $this->table(['Reason', 'Record ID', 'Context', 'Blocking'], array_map(fn ($sample) => [
                    $sample['reason'], $sample['id'], $sample['context'], $sample['blocking'] ? 'yes' : 'no',
                ], $report['samples']));
            }
            if ($report['legacy_columns'] === []) {
                $this->info('No legacy social parent columns remain.');
            }
        }

        return $report['blocking_issues'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
