<?php

namespace App\Services\Moderation;

use App\Model\MediaModerationItem;

class ModerationDecisionService
{
    public function decide(float $maxScore, array $policy): string
    {
        $reviewThreshold = max(0, min(1, (float) ($policy['review_threshold'] ?? 0.65)));
        $violationThreshold = max($reviewThreshold, min(1, (float) ($policy['violation_threshold'] ?? 0.90)));

        if ($maxScore >= $violationThreshold) {
            return MediaModerationItem::DECISION_VIOLATION;
        }

        if ($maxScore >= $reviewThreshold) {
            return MediaModerationItem::DECISION_REVIEW;
        }

        return MediaModerationItem::DECISION_SAFE;
    }
}
