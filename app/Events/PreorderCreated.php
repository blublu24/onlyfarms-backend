<?php

namespace App\Events;

use App\Models\Preorder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PreorderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $preorder;

    /**
     * Create a new event instance.
     */
    public function __construct(Preorder $preorder)
    {
        $this->preorder = $preorder->load(['product', 'consumer', 'seller']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('seller.' . $this->preorder->seller_id),
            new PrivateChannel('consumer.' . $this->preorder->consumer_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'preorder.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'preorder' => $this->preorder,
            'message' => 'New preorder received',
            'type' => 'preorder_created',
        ];
    }
}
