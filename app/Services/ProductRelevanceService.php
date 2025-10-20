<?php

namespace App\Services;

use App\Models\Product;

class ProductRelevanceService
{
    /**
     * Calculate relevance score for a product based on search query and multiple factors
     * 
     * Formula: text_match (30%) + sales_score (25%) + rating_score (25%) + stock_score (10%) + recency_score (10%)
     * 
     * @param Product $product
     * @param string $searchQuery
     * @return float
     */
    public function calculateRelevanceScore(Product $product, string $searchQuery): float
    {
        // Text match score (30%)
        $textScore = $this->getTextMatchScore($product, $searchQuery);
        
        // Sales score (25%) - normalize using log scale to prevent mega-seller dominance
        $maxSold = Product::max('total_sold') ?: 1;
        $salesScore = log($product->total_sold + 1) / log($maxSold + 1);
        
        // Rating score (25%) - use pre-calculated rating_weight
        $ratingScore = $product->rating_weight ?? 0;
        
        // Stock score (10%) - binary boost for in-stock products
        $stockScore = $product->stocks > 0 ? 1.0 : 0.0;
        
        // Recency score (10%) - newer products get slight boost (decays over 90 days)
        $daysOld = now()->diffInDays($product->created_at);
        $recencyScore = max(0, 1 - ($daysOld / 90));
        
        // Calculate final relevance score
        $relevanceScore = ($textScore * 0.30) + ($salesScore * 0.25) + 
                         ($ratingScore * 0.25) + ($stockScore * 0.10) + 
                         ($recencyScore * 0.10);
        
        return round($relevanceScore, 4);
    }
    
    /**
     * Calculate text match score based on how well the product matches the search query
     * 
     * @param Product $product
     * @param string $query
     * @return float
     */
    private function getTextMatchScore(Product $product, string $query): float
    {
        $query = strtolower(trim($query));
        
        // Exact match at start of product name = 1.0
        if (stripos($product->product_name, $query) === 0) {
            return 1.0;
        }
        
        // Contains in product name = 0.7
        if (stripos($product->product_name, $query) !== false) {
            return 0.7;
        }
        
        // Contains in description = 0.4
        if ($product->description && stripos($product->description, $query) !== false) {
            return 0.4;
        }
        
        // No match = 0.0
        return 0.0;
    }
    
    /**
     * Calculate relevance scores for multiple products and sort by score
     * 
     * @param \Illuminate\Database\Eloquent\Collection $products
     * @param string $searchQuery
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function calculateAndSortByRelevance($products, string $searchQuery)
    {
        return $products->map(function($product) use ($searchQuery) {
            $product->relevance_score = $this->calculateRelevanceScore($product, $searchQuery);
            return $product;
        })->sortByDesc('relevance_score');
    }
}
