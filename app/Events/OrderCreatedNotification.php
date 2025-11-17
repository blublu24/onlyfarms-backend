<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class OrderCreatedNotification implements ShouldBroadcastNow
{
    use SerializesModels;

    /**
     * The user ID of the seller that should receive the notification.
     */
    public int $recipientUserId;

    /**
     * The payload to broadcast.
     */
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
        return 'order.created';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}

