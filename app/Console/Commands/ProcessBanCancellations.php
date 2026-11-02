<?php

namespace App\Console\Commands;

use App\Services\UserBanService;
use Illuminate\Console\Command;

class ProcessBanCancellations extends Command
{
    protected $signature = 'users:process-ban-cancellations {--limit=10}';

    protected $description = 'Process persisted subscription cancellations caused by account suspension.';

    public function handle(UserBanService $service): int
    {
        $result = $service->processPendingCancellations(max(1, min(50, (int) $this->option('limit'))));
        $this->info("Completed {$result['completed']} cancellations; {$result['review']} require provider review.");

        return self::SUCCESS;
    }
}
