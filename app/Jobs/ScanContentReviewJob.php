<?php

namespace App\Jobs;

use App\Model\ContentReview;
use App\Services\Moderation\PublicationReviewScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ScanContentReviewJob implements ShouldQueue
{
    use Dispatchable;

    use InteractsWithQueue;

    use Queueable;

    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 240;

    public function __construct(public int $reviewId)
    {
    }

    public function handle(PublicationReviewScanner $scanner): void
    {
        $scanner->prepare($this->reviewId);
    }

    public function failed(?Throwable $exception): void
    {
        ContentReview::query()->whereKey($this->reviewId)->where('status', ContentReview::STATUS_PENDING)
            ->update(['attention_reason' => 'scan_interrupted']);
    }
}
