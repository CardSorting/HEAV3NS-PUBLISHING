<?php

namespace App\Services;

use App\Http\Controllers\MessengerController;
use App\Model\Transaction;
use App\Model\UserMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatTipMessages
{
    public function record(int $transactionId): ?UserMessage
    {
        return DB::transaction(function () use ($transactionId): ?UserMessage {
            $transaction = Transaction::query()->lockForUpdate()->find($transactionId);
            if (!$transaction || $transaction->type !== Transaction::CHAT_TIP_TYPE
                || $transaction->status !== Transaction::APPROVED_STATUS
                || !$transaction->sender_user_id || !$transaction->recipient_user_id
                || (int) $transaction->sender_user_id === (int) $transaction->recipient_user_id) {
                return null;
            }

            $existing = UserMessage::where('tip_transaction_id', $transactionId)->first();
            if ($existing) {
                return $existing;
            }

            $senderId = (int) $transaction->sender_user_id;
            $receiverId = (int) $transaction->recipient_user_id;
            $isNewConversation = !UserMessage::query()->where(function ($query) use ($senderId, $receiverId) {
                $query->where('sender_id', $senderId)->where('receiver_id', $receiverId);
            })->orWhere(function ($query) use ($senderId, $receiverId) {
                $query->where('sender_id', $receiverId)->where('receiver_id', $senderId);
            })->exists();

            $message = new UserMessage([
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message' => null,
                'price' => 0,
            ]);
            // Server-only linkage: ordinary message payloads cannot assign a transaction.
            $message->tip_transaction_id = $transactionId;
            $message->save();

            DB::afterCommit(function () use ($message, $isNewConversation): void {
                try {
                    app(MessengerController::class)->broadcastTipMessage((int) $message->id, $isNewConversation);
                } catch (\Throwable $exception) {
                    Log::error('A committed Messenger tip could not be broadcast.', ['message_id' => $message->id]);
                }
            });

            return $message;
        });
    }
}
