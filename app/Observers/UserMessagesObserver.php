<?php

namespace App\Observers;

use App\Model\Attachment;
use App\Model\UserMessage;
use App\Events\MessagePinsChanged;
use App\Providers\AttachmentServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserMessagesObserver
{
    /**
     * Listen to the Attachment deleted event.
     *
     * @param  UserMessage  $userMessage
     * @return void
     */
    public function deleting(UserMessage $userMessage)
    {
        if ($userMessage->tip_transaction_id) {
            throw ValidationException::withMessages(['message' => __('Tip events cannot be edited or deleted.')]);
        }
        // The parent FK removes placements; shared bytes and the sender's Vault remain intact.
    }

    public function updating(UserMessage $userMessage): void
    {
        if ($userMessage->getOriginal('tip_transaction_id') && $userMessage->isDirty([
            'sender_id', 'receiver_id', 'message', 'price', 'tip_transaction_id', 'gif', 'story_id',
        ])) {
            throw ValidationException::withMessages(['message' => __('Tip events cannot be edited or deleted.')]);
        }
    }

    public function updated(UserMessage $message): void
    {
        if ($message->wasChanged(['pinned_at', 'pinned_by'])) {
            $this->publishPinChange($message);
        }
    }

    public function deleted(UserMessage $message): void
    {
        if ($message->pinned_at) {
            $this->publishPinChange($message);
        }
    }

    private function publishPinChange(UserMessage $message): void
    {
        $event = new MessagePinsChanged($message);
        DB::afterCommit(function () use ($event): void {
            try {
                broadcast($event)->toOthers();
            } catch (\Throwable $exception) {
                Log::error('A committed message pin change could not be broadcast.', ['message_id' => $event->messageId]);
            }
        });
    }
}
