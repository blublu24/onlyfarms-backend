<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderDeliveredNotification implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public $order;
    public $buyerId;

    /**
     * Create a new event instance.
     */
    public function __construct(Order $order, int $buyerId)
    {
        $this->order = $order;
        $this->buyerId = $buyerId;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->buyerId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.delivered';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->order->id,
            'order_id' => $this->order->id,
            'status' => $this->order->status,
            'delivery_address' => $this->order->delivery_address,
            'total' => $this->order->total,
            'created_at' => $this->order->created_at->toISOString(),
            'type' => 'delivery',
            'title' => 'Order Delivered! 🚚',
            'message' => "Your order #{$this->order->id} has been delivered successfully!",
            'action_url' => "/FinalReceiptPage?orderId={$this->order->id}",
        ];
    }
}
