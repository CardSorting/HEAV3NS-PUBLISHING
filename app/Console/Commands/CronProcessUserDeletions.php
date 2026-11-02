<?php

namespace App\Console\Commands;

use App\Services\UserDeletionRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CronProcessUserDeletions extends Command
{
    /**
     * @var string
     */
    protected $signature = 'cron:process_user_deletions {--limit=50}';

    /**
     * @var string
     */
    protected $description = 'Process eligible account deletion requests.';

    public function handle(UserDeletionRequestService $deletionRequestService): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $processed = $deletionRequestService->processEligibleRequests($limit);

        Log::channel('cronjobs')->info('[*]['.date('H:i:s')."] Processed {$processed} account deletion requests.\r\n");
        $this->info("Processed {$processed} account deletion requests.");

        return 0;
    }
}
