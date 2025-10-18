<?php

namespace App\Events;

use App\Models\Preorder;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PreorderFulfilled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $preorder;
    public $order;

    /**
     * Create a new event instance.
     */
    public function __construct(Preorder $preorder, Order $order)
    {
        $this->preorder = $preorder->load(['product', 'consumer', 'seller']);
        $this->order = $order->load(['items', 'user']);
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
        return 'preorder.fulfilled';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'preorder' => $this->preorder,
            'order' => $this->order,
            'message' => 'Your preorder has been fulfilled and is ready for delivery',
            'type' => 'preorder_fulfilled',
        ];
    }
}
