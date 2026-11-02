<?php

namespace App\Console\Commands;

use App\Services\ConversionTracking\PostbackDeliveryService;
use Illuminate\Console\Command;
use Throwable;

class DeliverConversions extends Command
{
    protected $signature = 'conversions:deliver {--limit=50 : Maximum due postbacks to process (1-500)}';

    protected $description = 'Deliver purchase postbacks, retry temporary failures, and discard ineligible pending browser conversions.';

    public function handle(PostbackDeliveryService $deliveries): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 500) {
            $this->error('The limit must be an integer between 1 and 500.');

            return self::FAILURE;
        }

        try {
            $pruned = $deliveries->pruneExpiredAttribution();
            $browserSkipped = $deliveries->invalidatePendingBrowser();
            $counts = $deliveries->deliverPending($limit);
        } catch (Throwable $exception) {
            // Connection details and configuration values must never appear in cron output.
            $this->error('Conversion delivery is unavailable. Verify that the database migration has run.');

            return self::FAILURE;
        }

        $this->info("Processed {$counts['processed']} postbacks: {$counts['sent']} sent, {$counts['pending']} awaiting retry, {$counts['failed']} failed, {$counts['skipped']} skipped.");
        if ($pruned > 0) {
            $this->info("Cleared {$pruned} expired or revoked click identifiers.");
        }
        if ($browserSkipped > 0) {
            $this->info("Skipped {$browserSkipped} ineligible pending browser conversions.");
        }

        return self::SUCCESS;
    }
}
