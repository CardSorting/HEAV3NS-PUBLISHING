<?php

namespace App\Services;

use App\Model\Attachment;
use App\Model\ReelComment;
use App\Model\User;
use App\Model\UserReport;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserReportContentDeletionService
{
    public function target(UserReport $report): ?Model
    {
        return match (true) {
            (bool) $report->reel_comment_id => $report->reportedReelComment,
            (bool) $report->post_id => $report->reportedPost,
            (bool) $report->message_id => $report->reportedMessage,
            (bool) $report->stream_id => $report->reportedStream,
            (bool) $report->story_id => $report->reportedStory,
            (bool) $report->reel_id => $report->reportedReel,
            default => null,
        };
    }

    public function canDelete(?User $administrator, UserReport $report): bool
    {
        $target = $this->target($report);

        if (!$administrator || !$target) {
            return false;
        }

        if ($target instanceof ReelComment) {
            return $administrator->can('Delete:Reel');
        }

        return $administrator->can('delete', $target);
    }

    public function targetLabel(UserReport $report): string
    {
        return match (true) {
            (bool) $report->reel_comment_id => 'reel comment',
            (bool) $report->post_id => 'post',
            (bool) $report->message_id => 'message',
            (bool) $report->stream_id => 'stream',
            (bool) $report->story_id => 'story',
            (bool) $report->reel_id => 'reel',
            default => 'content',
        };
    }

    public function delete(UserReport $report, ?int $administratorId = null): void
    {
        $target = $this->target($report);

        if (!$target) {
            throw new DomainException('The reported content no longer exists.');
        }

        $reference = $this->reference($report);
        $targetLabel = $this->targetLabel($report);

        DB::transaction(function () use ($target, $reference, $targetLabel, $administratorId): void {
            $reports = $this->relatedReports($reference)
                ->lockForUpdate()
                ->get();

            foreach ($reports as $relatedReport) {
                $this->markSolved($relatedReport, $reference, $targetLabel, $administratorId);
            }

            if (method_exists($target, 'attachments')) {
                $target->attachments()->get()->each(
                    fn (Attachment $attachment) => $attachment->delete(),
                );
            }

            $target->delete();
        });
    }

    /**
     * @return array{column: string, id: int, clear: list<string>}
     */
    protected function reference(UserReport $report): array
    {
        return match (true) {
            (bool) $report->reel_comment_id => [
                'column' => 'reel_comment_id',
                'id' => (int) $report->reel_comment_id,
                'clear' => ['reel_comment_id', 'reel_id'],
            ],
            (bool) $report->post_id => ['column' => 'post_id', 'id' => (int) $report->post_id, 'clear' => ['post_id']],
            (bool) $report->message_id => ['column' => 'message_id', 'id' => (int) $report->message_id, 'clear' => ['message_id']],
            (bool) $report->stream_id => ['column' => 'stream_id', 'id' => (int) $report->stream_id, 'clear' => ['stream_id']],
            (bool) $report->story_id => ['column' => 'story_id', 'id' => (int) $report->story_id, 'clear' => ['story_id']],
            (bool) $report->reel_id => ['column' => 'reel_id', 'id' => (int) $report->reel_id, 'clear' => ['reel_id', 'reel_comment_id']],
            default => throw new DomainException('This report does not reference deletable content.'),
        };
    }

    /**
     * @param  array{column: string, id: int, clear: list<string>}  $reference
     * @return Builder<UserReport>
     */
    protected function relatedReports(array $reference): Builder
    {
        return UserReport::query()->where($reference['column'], $reference['id']);
    }

    /**
     * @param  array{column: string, id: int, clear: list<string>}  $reference
     */
    protected function markSolved(
        UserReport $report,
        array $reference,
        string $targetLabel,
        ?int $administratorId,
    ): void {
        $auditLine = sprintf(
            '[Moderation] Deleted reported %s #%d by administrator #%s on %s.',
            $targetLabel,
            $reference['id'],
            $administratorId ?: 'unknown',
            now()->toDateTimeString(),
        );
        $details = trim((string) $report->details);
        $attributes = [
            'status' => UserReport::SOLVED_STATUS,
            'details' => $details === '' ? $auditLine : $details."\n\n".$auditLine,
        ];

        foreach ($reference['clear'] as $column) {
            $attributes[$column] = null;
        }

        $report->update($attributes);
    }
}
