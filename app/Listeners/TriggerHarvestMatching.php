<?php

namespace App\Listeners;

use App\Events\HarvestPublished;
use App\Jobs\HarvestMatchingJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class TriggerHarvestMatching implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(HarvestPublished $event): void
    {
        Log::info('Harvest published, triggering matching job', [
            'harvest_id' => $event->harvest->id,
            'product_id' => $event->harvest->product_id,
            'variation_type' => $event->harvest->variation_type,
            'unit_key' => $event->harvest->unit_key,
            'actual_weight_kg' => $event->harvest->actual_weight_kg
        ]);

        // Increment product stock for the specific variation
        $this->incrementProductStock($event->harvest);

        // Dispatch the matching job
        HarvestMatchingJob::dispatch($event->harvest);
    }

    /**
     * Increment product stock based on harvest variation and weight.
     */
    private function incrementProductStock($harvest): void
    {
        if (!$harvest->product_id || !$harvest->actual_weight_kg) {
            Log::warning('Cannot increment stock: missing product_id or actual_weight_kg', [
                'harvest_id' => $harvest->id
            ]);
            return;
        }

        $product = \App\Models\Product::find($harvest->product_id);
        
        if (!$product) {
            Log::warning('Cannot increment stock: product not found', [
                'harvest_id' => $harvest->id,
                'product_id' => $harvest->product_id
            ]);
            return;
        }

        $weightToAdd = $harvest->actual_weight_kg;
        $variationType = $harvest->variation_type ?? 'regular';

        // Increment the appropriate variation stock
        switch ($variationType) {
            case 'premium':
                $product->premium_stock_kg = ($product->premium_stock_kg ?? 0) + $weightToAdd;
                break;
            case 'type_a':
                $product->type_a_stock_kg = ($product->type_a_stock_kg ?? 0) + $weightToAdd;
                break;
            case 'type_b':
                $product->type_b_stock_kg = ($product->type_b_stock_kg ?? 0) + $weightToAdd;
                break;
            case 'regular':
            default:
                $product->stock_kg = ($product->stock_kg ?? 0) + $weightToAdd;
                break;
        }

        $product->save();

        Log::info('Product stock incremented', [
            'harvest_id' => $harvest->id,
            'product_id' => $product->product_id,
            'variation_type' => $variationType,
            'weight_added_kg' => $weightToAdd,
            'new_stock_kg' => $variationType === 'premium' ? $product->premium_stock_kg : 
                              ($variationType === 'type_a' ? $product->type_a_stock_kg : 
                              ($variationType === 'type_b' ? $product->type_b_stock_kg : $product->stock_kg))
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(HarvestPublished $event, \Throwable $exception): void
    {
        Log::error('Failed to trigger harvest matching job', [
            'harvest_id' => $event->harvest->id,
            'error' => $exception->getMessage()
        ]);
    }
}