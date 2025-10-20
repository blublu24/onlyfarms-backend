<?php

namespace App\Events;

use App\Models\Preorder;
use App\Models\Harvest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PreorderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $preorder;
    public $harvest;

    /**
     * Create a new event instance.
     */
    public function __construct(Preorder $preorder, Harvest $harvest)
    {
        $this->preorder = $preorder->load(['product', 'consumer', 'seller']);
        $this->harvest = $harvest;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('preorder.matched'),
            new PrivateChannel('user.' . $this->preorder->consumer_id), // Notify buyer
            new PrivateChannel('user.' . $this->preorder->seller_id), // Notify seller
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'preorder_id' => $this->preorder->id,
            'consumer_id' => $this->preorder->consumer_id,
            'seller_id' => $this->preorder->seller_id,
            'product_id' => $this->preorder->product_id,
            'product_name' => $this->preorder->product->product_name ?? 'Unknown',
            'variation_type' => $this->preorder->variation_type,
            'unit_key' => $this->preorder->unit_key,
            'quantity' => $this->preorder->quantity,
            'allocated_qty' => $this->preorder->allocated_qty,
            'status' => $this->preorder->status,
            'harvest_id' => $this->harvest->id,
            'harvest_date' => $this->harvest->harvested_at,
            'matched_at' => $this->preorder->matched_at,
            'message' => 'Your preorder has been matched to a harvest and is ready for fulfillment!'
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'preorder.matched';
    }
}