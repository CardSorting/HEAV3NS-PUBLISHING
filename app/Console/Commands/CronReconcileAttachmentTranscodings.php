<?php

namespace App\Console\Commands;

use App\Services\Media\AttachmentTranscodingReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CronReconcileAttachmentTranscodings extends Command
{
    protected $signature = 'cron:reconcile_attachment_transcodings {--limit=50}';

    protected $description = 'Mark interrupted non-durable video processing operations as failed.';

    public function handle(AttachmentTranscodingReconciliationService $reconciler): int
    {
        if (!$reconciler->shouldRun()) {
            return self::SUCCESS;
        }

        $reconciled = $reconciler->reconcile((int) $this->option('limit'));
        if ($reconciled > 0) {
            Log::channel('cronjobs')->info("Marked {$reconciled} interrupted video processing operation(s) as failed.");
        }

        return self::SUCCESS;
    }
}
