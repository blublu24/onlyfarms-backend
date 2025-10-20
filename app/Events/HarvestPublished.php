<?php

namespace App\Events;

use App\Models\Harvest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HarvestPublished implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $harvest;

    /**
     * Create a new event instance.
     */
    public function __construct(Harvest $harvest)
    {
        $this->harvest = $harvest->load(['cropSchedule.product', 'product']);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('harvest.published'),
            new PrivateChannel('seller.' . $this->harvest->cropSchedule->seller_id),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'harvest_id' => $this->harvest->id,
            'product_id' => $this->harvest->product_id,
            'variation_type' => $this->harvest->variation_type,
            'unit_key' => $this->harvest->unit_key,
            'actual_weight_kg' => $this->harvest->actual_weight_kg,
            'available_weight_kg' => $this->harvest->available_weight_kg,
            'quality_grade' => $this->harvest->quality_grade,
            'published_at' => $this->harvest->published_at,
            'product_name' => $this->harvest->product->product_name ?? 'Unknown',
            'seller_id' => $this->harvest->cropSchedule->seller_id,
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'harvest.published';
    }
}