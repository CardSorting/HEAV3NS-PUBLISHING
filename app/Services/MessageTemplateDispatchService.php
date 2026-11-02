<?php

namespace App\Services;

use App\Http\Controllers\MessengerController;
use App\Model\MessageTemplate;
use App\Model\User;
use App\Model\UserMessage;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageTemplateDispatchService
{
    public function dispatchForTrigger(int $creatorId, int $recipientId, string $triggerType): ?array
    {
        try {
            return $this->dispatchTemplate($creatorId, $recipientId, $triggerType);
        } catch (\Throwable $exception) {
            Log::error('Failed dispatching message template', [
                'creator_id' => $creatorId,
                'recipient_id' => $recipientId,
                'trigger_type' => $triggerType,
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    protected function dispatchTemplate(int $creatorId, int $recipientId, string $triggerType): ?array
    {
        if ($creatorId === $recipientId || !in_array($triggerType, MessageTemplate::TRIGGER_TYPES, true)) {
            return null;
        }

        $creator = User::where('id', $creatorId)->first();
        $recipient = User::where('id', $recipientId)->first();

        if (!$creator || !$recipient || $creator->isBanned() || $recipient->isBanned()
            || !$this->recipientHasTemplateAccess($creator, $recipient)) {
            return null;
        }

        return DB::transaction(function () use ($creatorId, $recipientId, $triggerType, $creator): ?array {
            $template = MessageTemplate::with('attachments')
                ->where('user_id', $creatorId)
                ->where('trigger_type', $triggerType)
                ->where('enabled', true)
                ->lockForUpdate()
                ->first();

            if (!$template || (!$this->hasText($template) && !$template->attachments->count())) {
                return null;
            }

            if ((float) $template->price > 0 && !$template->attachments->count() && !getSetting('compliance.allow_text_only_ppv')) {
                return null;
            }

            if (UserMessage::where('sender_id', $creatorId)
                ->where('receiver_id', $recipientId)
                ->where('message_template_id', $template->id)
                ->exists()
            ) {
                return null;
            }

            if (
                GenericHelperServiceProvider::hasUserBlocked($recipientId, $creatorId) ||
                GenericHelperServiceProvider::hasUserBlocked($creatorId, $recipientId)
            ) {
                return null;
            }

            return app(MessengerController::class)->sendUserMessage([
                'senderID' => $creatorId,
                'receiverID' => $recipientId,
                'messageValue' => $template->message,
                'messagePrice' => $template->price ?: 0,
                'story_id' => null,
                'sourceTemplate' => $template,
                'attachmentIds' => $template->attachments->pluck('id')->values()->all(),
                'skipContactFetch' => true,
                'senderUser' => $creator,
                'viewerID' => $recipientId,
            ]);
        }, 3);
    }

    protected function hasText(MessageTemplate $template): bool
    {
        return trim((string) $template->message) !== '';
    }

    protected function recipientHasTemplateAccess(User $creator, User $recipient): bool
    {
        if (\App\Providers\ProfileMonetizationServiceProvider::canReceiveProfileSubscriptions($creator)) {
            return PostsHelperServiceProvider::hasActiveSub($recipient->id, $creator->id);
        }

        return ListsHelperServiceProvider::isUserFollowing($recipient->id, $creator->id);
    }
}
