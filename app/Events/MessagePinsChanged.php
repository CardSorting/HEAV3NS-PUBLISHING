<?php

namespace App\Events;

use App\Model\UserMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MessagePinsChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public int $messageId;

    public string $channelName;

    public function __construct(UserMessage $message)
    {
        $this->messageId = (int) $message->id;
        $this->channelName = 'chat-channel-'.min($message->sender_id, $message->receiver_id).'-'.max($message->sender_id, $message->receiver_id);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->channelName);
    }

    public function broadcastAs(): string
    {
        return 'message-pins-changed';
    }
}
