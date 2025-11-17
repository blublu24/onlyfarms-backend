<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ChatMessageNotification implements ShouldBroadcastNow
{
    use SerializesModels;

    public int $recipientUserId;
    public array $payload;

    public function __construct(int $recipientUserId, array $payload)
    {
        $this->recipientUserId = $recipientUserId;
        $this->payload = $payload;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->recipientUserId)];
    }

    public function broadcastAs(): string
    {
        return 'message.created';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

