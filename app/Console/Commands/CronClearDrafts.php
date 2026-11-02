<?php

namespace App\Console\Commands;

use App\Model\Attachment;
use App\Model\ReleaseForm;
use App\Model\UserVerify;
use App\Providers\AttachmentServiceProvider;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CronClearDrafts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:clear_draft_files
        {--dry-run : Report draft attachments without deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears un-attached attachments';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Clears old zombie attachments (never uploaded drafts).
     *
     * @return mixed
     */
    public function handle()
    {
        $protectedFilenames = $this->getProtectedAttachmentFilenames();
        $isDryRun = (bool) $this->option('dry-run');

        $attachments = Attachment::where([
            'sound_id' => null,
        ])
            ->whereNull('vaulted_at')
            ->whereDoesntHave('placements')
            ->whereDoesntHave('transcoding', fn ($query) => $query->whereIn('status', ['queued', 'processing']))
            ->where('filename', 'not like', 'users/verifications/%')
            ->where('filename', 'not like', 'users/release-forms/%')
            ->when(!empty($protectedFilenames), function ($query) use ($protectedFilenames) {
                $query->whereNotIn('filename', $protectedFilenames);
            })
            ->where('created_at', '<=', Carbon::now()->subDay()->toDateTimeString())
            ->orderBy('created_at')
            ->get();

        $deletedRows = 0;

        if (!$isDryRun) {
            foreach($attachments as $attachment){
                try {
                    app(\App\Services\Media\AttachmentLifecycleService::class)->deleteUnused($attachment, (int) $attachment->user_id);
                    $deletedRows++;
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    // The attachment may have become protected after the initial scan.
                }
            }
        }

        $this->reportCleanup($attachments, $protectedFilenames, $deletedRows, $isDryRun);

        // TODO: Add removal of /posts/videos/tmp

        return 0;
    }

    protected function reportCleanup($attachments, array $protectedFilenames, int $deletedRows, bool $isDryRun): void
    {
        $total = $attachments->count();
        $action = $isDryRun ? 'would be deleted' : 'deleted';
        $message = '[*]['.date('H:i:s')."] Zombie draft assets {$action}. Total files: {$total}.";

        $this->info($message);
        $this->line('DB rows '.($isDryRun ? 'matched' : 'deleted').": ".($isDryRun ? $total : $deletedRows).'.');
        $this->line('Protected external filenames skipped: '.count($protectedFilenames).'.');
        $this->line('By type: '.$this->formatBreakdown($attachments->groupBy('attachmentType')->map->count()->all()));
        $this->line('By driver: '.$this->formatBreakdown($attachments->groupBy('driver')->map->count()->all()));
        $this->line('By location: '.$this->formatBreakdown($attachments->groupBy(function ($attachment) {
            return $this->getAttachmentLocation($attachment->filename);
        })->map->count()->all()));

        if ($this->output->isVerbose() && $total > 0) {
            $this->table(
                ['ID', 'User', 'Type', 'Driver', 'Created at', 'Filename'],
                $attachments->map(function ($attachment) {
                    return [
                        $attachment->id,
                        $attachment->user_id,
                        $attachment->attachmentType ?: $attachment->type,
                        $attachment->driver,
                        optional($attachment->created_at)->toDateTimeString(),
                        $attachment->filename,
                    ];
                })->all()
            );
        }

        Log::channel('cronjobs')->info($message, [
            'dry_run' => $isDryRun,
            'matched' => $total,
            'deleted_rows' => $isDryRun ? 0 : $deletedRows,
            'protected_filenames' => count($protectedFilenames),
            'by_type' => $attachments->groupBy('attachmentType')->map->count()->all(),
            'by_driver' => $attachments->groupBy('driver')->map->count()->all(),
            'by_location' => $attachments->groupBy(function ($attachment) {
                return $this->getAttachmentLocation($attachment->filename);
            })->map->count()->all(),
            'attachment_ids' => $attachments->pluck('id')->values()->all(),
        ]);
    }

    protected function formatBreakdown(array $items): string
    {
        if (empty($items)) {
            return 'none';
        }

        ksort($items);

        return collect($items)
            ->map(fn ($count, $name) => ($name === '' ? 'unknown' : $name).'='.$count)
            ->implode(', ');
    }

    protected function getAttachmentLocation(?string $filename): string
    {
        $filename = trim((string) $filename, '/');
        if ($filename === '') {
            return 'unknown';
        }

        return explode('/', $filename)[0] ?: 'unknown';
    }

    protected function getProtectedAttachmentFilenames(): array
    {
        return collect()
            ->merge($this->decodeFilesFromRecords(UserVerify::query()->pluck('files')->all()))
            ->merge($this->decodeFilesFromRecords(ReleaseForm::query()->pluck('files')->all()))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function decodeFilesFromRecords(array $records): array
    {
        return collect($records)
            ->flatMap(function ($files) {
                if (is_array($files)) {
                    return $files;
                }

                if (!is_string($files) || trim($files) === '') {
                    return [];
                }

                $decoded = json_decode($files, true);

                return is_array($decoded) ? $decoded : [];
            })
            ->filter(fn ($file) => is_string($file) && $file !== '')
            ->all();
    }
}
