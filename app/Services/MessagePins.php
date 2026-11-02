<?php

namespace App\Services;

use App\Model\User;
use App\Model\UserMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessagePins
{
    public const LIMIT = 3;

    /** @return Builder<UserMessage> */
    public function conversation(int $firstId, int $secondId): Builder
    {
        return UserMessage::query()->where(function ($query) use ($firstId, $secondId) {
            $query->where(function ($direction) use ($firstId, $secondId) {
                $direction->where('sender_id', $firstId)->where('receiver_id', $secondId);
            })->orWhere(function ($direction) use ($firstId, $secondId) {
                $direction->where('sender_id', $secondId)->where('receiver_id', $firstId);
            });
        });
    }

    public function set(UserMessage $message, int $actorId, bool $pinned): UserMessage
    {
        abort_if($actorId !== (int) $message->sender_id && $actorId !== (int) $message->receiver_id, 403);

        return DB::transaction(function () use ($message, $actorId, $pinned): UserMessage {
            // There is no conversation row. Lock participants consistently so both directions share the limit.
            User::query()->whereIn('id', [$message->sender_id, $message->receiver_id])
                ->orderBy('id')->lockForUpdate()->get(['id']);
            $current = UserMessage::query()->lockForUpdate()->findOrFail($message->id);
            abort_unless((int) $current->sender_id === (int) $message->sender_id
                && (int) $current->receiver_id === (int) $message->receiver_id, 409);

            if (($current->pinned_at !== null) === $pinned) {
                return $current;
            }
            if ($pinned && $this->conversation((int) $current->sender_id, (int) $current->receiver_id)
                ->whereNotNull('pinned_at')->count() >= self::LIMIT) {
                throw ValidationException::withMessages([
                    'pinned' => __('You can pin up to :count messages in this conversation.', ['count' => self::LIMIT]),
                ]);
            }

            $current->pinned_at = $pinned ? now() : null;
            $current->pinned_by = $pinned ? $actorId : null;
            $current->save();

            return $current;
        }, 3);
    }
}
