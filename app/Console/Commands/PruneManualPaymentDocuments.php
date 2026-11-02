<?php

namespace App\Console\Commands;

use App\Services\ManualPayments\ManualPaymentDocuments;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneManualPaymentDocuments extends Command
{
    protected $signature = 'manual-payments:prune-documents';

    protected $description = 'Remove abandoned uploads older than 24 hours and orphaned request documents';

    public function handle(ManualPaymentDocuments $documents): int
    {
        if (Schema::hasTable('manual_payment_documents')) {
            $this->info('Removed '.$documents->prune().' unused request documents.');
        }

        return self::SUCCESS;
    }
}
