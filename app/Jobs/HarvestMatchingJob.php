<?php

namespace App\Jobs;

use App\Models\Harvest;
use App\Models\Preorder;
use App\Models\Product;
use App\Events\PreorderMatched;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HarvestMatchingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $harvest;

    /**
     * Create a new job instance.
     */
    public function __construct(Harvest $harvest)
    {
        $this->harvest = $harvest;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting harvest matching job', ['harvest_id' => $this->harvest->id]);

        try {
            DB::beginTransaction();

            // Find matching preorders using FIFO algorithm (business rule)
            $matchingPreorders = $this->findMatchingPreorders();

            if ($matchingPreorders->isEmpty()) {
                Log::info('No matching preorders found', ['harvest_id' => $this->harvest->id]);
                $this->harvest->markMatchingCompleted();
                DB::commit();
                return;
            }

            // Allocate harvest weight to preorders
            $this->allocateHarvestToPreorders($matchingPreorders);

            // Mark matching as completed
            $this->harvest->markMatchingCompleted();

            DB::commit();

            Log::info('Harvest matching completed successfully', [
                'harvest_id' => $this->harvest->id,
                'matched_preorders' => $matchingPreorders->count()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Harvest matching job failed', [
                'harvest_id' => $this->harvest->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Find matching preorders using FIFO algorithm
     */
    private function findMatchingPreorders()
    {
        return Preorder::where('product_id', $this->harvest->product_id)
            ->where('variation_type', $this->harvest->variation_type)
            ->where('unit_key', $this->harvest->unit_key)
            ->where('status', 'pending')
            ->whereNull('harvest_date_ref')
            ->orderBy('created_at', 'asc') // FIFO: First In, First Out
            ->orderBy('id', 'asc') // Secondary sort by ID for consistency
            ->get();
    }

    /**
     * Allocate harvest weight to preorders
     */
    private function allocateHarvestToPreorders($preorders)
    {
        $remainingWeight = $this->harvest->available_weight_kg;
        $matchedPreorders = collect();

        foreach ($preorders as $preorder) {
            if ($remainingWeight <= 0) {
                break;
            }

            // Calculate required weight based on quantity and unit weight (business rule)
            $requiredWeight = $preorder->quantity * $preorder->unit_weight_kg;

            // Check if we can fulfill this preorder
            if ($requiredWeight <= $remainingWeight) {
                // Full allocation
                $this->allocatePreorder($preorder, $requiredWeight);
                $remainingWeight -= $requiredWeight;
                $matchedPreorders->push($preorder);
                
                Log::info('Preorder fully allocated', [
                    'preorder_id' => $preorder->id,
                    'required_weight' => $requiredWeight,
                    'remaining_harvest_weight' => $remainingWeight
                ]);
            } else {
                // Partial allocation (business rule: allow partial fulfillment)
                $allocatedWeight = $remainingWeight;
                $this->allocatePreorder($preorder, $allocatedWeight);
                $remainingWeight = 0;
                $matchedPreorders->push($preorder);
                
                Log::info('Preorder partially allocated', [
                    'preorder_id' => $preorder->id,
                    'allocated_weight' => $allocatedWeight,
                    'required_weight' => $requiredWeight,
                    'partial_fulfillment' => true
                ]);
                break; // No more weight available
            }
        }

        // Update harvest allocated weight
        $this->harvest->allocated_weight_kg = $this->harvest->available_weight_kg - $remainingWeight;
        $this->harvest->available_weight_kg = $remainingWeight;
        $this->harvest->save();

        // Emit events for matched preorders
        foreach ($matchedPreorders as $preorder) {
            event(new PreorderMatched($preorder, $this->harvest));
        }
    }

    /**
     * Allocate a specific preorder with given weight
     */
    private function allocatePreorder(Preorder $preorder, float $allocatedWeight)
    {
        // Calculate the actual quantity that can be allocated based on weight
        $allocatedQuantity = $allocatedWeight / $preorder->unit_weight_kg;
        
        // Update preorder with allocation
        $preorder->allocated_qty = $allocatedWeight;
        $preorder->harvest_date_ref = $this->harvest->id;
        $preorder->status = 'reserved';
        $preorder->matched_at = now();
        $preorder->save();

        Log::info('Preorder allocated', [
            'preorder_id' => $preorder->id,
            'harvest_id' => $this->harvest->id,
            'allocated_weight' => $allocatedWeight,
            'allocated_quantity' => $allocatedQuantity,
            'unit_weight_kg' => $preorder->unit_weight_kg,
            'requested_quantity' => $preorder->quantity,
            'requested_weight' => $preorder->quantity * $preorder->unit_weight_kg
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Harvest matching job failed permanently', [
            'harvest_id' => $this->harvest->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}