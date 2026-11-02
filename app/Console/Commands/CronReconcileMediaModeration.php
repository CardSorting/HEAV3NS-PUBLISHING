<?php

namespace App\Console\Commands;

use App\Services\Moderation\ModerationAsyncReconciliationService;
use App\Services\Moderation\ModerationConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CronReconcileMediaModeration extends Command
{
    protected $signature = 'cron:reconcile_media_moderation {--limit=5}';

    protected $description = 'Recover asynchronous media moderation results whose provider callback was delayed or lost.';

    public function handle(
        ModerationAsyncReconciliationService $reconciler,
        ModerationConfiguration $configuration,
    ): int
    {
        if (!$configuration->asyncReconciliationEnabled()) {
            $this->info('Asynchronous media moderation reconciliation is disabled.');

            return self::SUCCESS;
        }

        $stats = $reconciler->reconcile(max(1, (int) $this->option('limit')));
        $message = "Checked {$stats['checked']} asynchronous moderation item(s): "
            ."{$stats['completed']} completed, {$stats['pending']} pending, "
            ."{$stats['failed']} failed, {$stats['errors']} status-check error(s).";

        if ($stats['checked'] > 0) {
            Log::channel('cronjobs')->info($message);
        }

        $this->info($message);

        return self::SUCCESS;
    }
}
