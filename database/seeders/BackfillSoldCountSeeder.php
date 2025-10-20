<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class BackfillSoldCountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting sold count backfill...');
        
        // Reset all total_sold to 0 first
        Product::query()->update(['total_sold' => 0]);
        $this->command->info('Reset all product total_sold to 0');
        
        // Calculate sold count from completed orders
        $soldCounts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.status', 'completed')
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('order_items.product_id')
            ->get();
        
        $updated = 0;
        foreach ($soldCounts as $sold) {
            $product = Product::find($sold->product_id);
            if ($product) {
                $product->update(['total_sold' => $sold->total_sold]);
                $updated++;
                $this->command->info("Updated {$product->product_name}: {$sold->total_sold} sold");
            }
        }
        
        $this->command->info("Backfill completed! Updated {$updated} products.");
    }
}
