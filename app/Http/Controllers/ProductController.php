<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Services\ProductRelevanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * List products (public).
     */
    public function index(Request $request)
    {
        $query = Product::with('seller'); // Eager-load seller

        // Only show approved products on homepage (or products without status for backward compatibility)
        $query->where(function($q) {
            $q->where('status', 'approved')
              ->orWhereNull('status'); // Show products without status for backward compatibility
        });

        // 🔍 NEW: Advanced filtering
        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        if ($request->has('search') && $request->search) {
            $query->where('product_name', 'like', '%' . $request->search . '%');
        }

        // Price range filtering
        if ($request->has('min_price') && $request->min_price) {
            $query->where('price_per_kg', '>=', $request->min_price);
        }

        if ($request->has('max_price') && $request->max_price) {
            $query->where('price_per_kg', '<=', $request->max_price);
        }

        // Rating filtering
        if ($request->has('min_rating') && $request->min_rating) {
            $query->where('avg_rating', '>=', $request->min_rating);
        }

        // Stock filtering
        if ($request->has('in_stock_only') && $request->in_stock_only) {
            $query->where('stock_kg', '>', 0);
        }

        // 🔍 NEW: Sorting
        if ($request->has('sort_by') && $request->sort_by) {
            switch ($request->sort_by) {
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'price_low':
                    $query->orderBy('price_per_kg', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price_per_kg', 'desc');
                    break;
                case 'rating':
                    $query->orderBy('avg_rating', 'desc');
                    break;
                case 'most_sold':
                    $query->orderBy('total_sold', 'desc');
                    break;
                case 'name':
                    $query->orderBy('product_name', 'asc');
                    break;
                case 'relevance':
                    // Relevance sorting will be handled after fetching products
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }
        } else {
            $query->orderBy('created_at', 'desc'); // Default sorting
        }

        // Handle relevance-based search
        if ($request->has('search') && $request->search && 
            ($request->sort_by === 'relevance' || !$request->has('sort_by'))) {
            // Fetch products first, then calculate relevance scores
            $products = $query->get();
            $relevanceService = new ProductRelevanceService();
            $products = $relevanceService->calculateAndSortByRelevance($products, $request->search);
        } else {
            // Normal query execution
            $products = $query->get();
        }

        $products = $products->map(function ($p) {
            // ✅ Get the full image URL using the model method
            $imageUrl = $p->full_image_url;

            $premiumStock = (float) ($p->premium_stock_kg ?? 0);
            $typeAStock = (float) ($p->type_a_stock_kg ?? 0);
            $typeBStock = (float) ($p->type_b_stock_kg ?? 0);
            $totalVariationStock = $premiumStock + $typeAStock + $typeBStock;
            $stockKg = $totalVariationStock > 0 ? $totalVariationStock : (float) ($p->stock_kg ?? 0);

            // Get vegetable slug and available units
            $vegetableSlug = $p->getVegetableSlug();
            $availableUnits = \App\Models\UnitConversion::getAvailableUnits($vegetableSlug);

            return [
                'product_id' => $p->product_id,
                'product_name' => $p->product_name,
                'image_url' => $imageUrl, // ✅ Full URL
                'fixed_image_url' => $imageUrl, // ✅ Full URL
                'stock_kg' => $stockKg,
                'price_per_kg' => $p->price_per_kg,
                'available_units' => $availableUnits, // ✅ Correct units based on vegetable type
                'vegetable_slug' => $vegetableSlug, // ✅ For frontend reference
                'description' => $p->description,
                'category' => $p->category,
                'seller_name' => $p->seller?->shop_name ?? 'Unknown Seller', // ✅ shop_name from sellers table
                'seller_id' => $p->seller_id,
                'updated_at' => $p->updated_at, // Add for cache busting
                // Analytics fields
                'total_sold' => $p->total_sold ?? 0,
                'avg_rating' => $p->avg_rating ?? 0,
                'ratings_count' => $p->ratings_count ?? 0,
                'relevance_score' => $p->relevance_score ?? null,
            ];
        });

        return response()->json([
            'message' => 'Products fetched successfully',
            'data' => $products
        ]);
    }

    /**
     * Show a single product.
     */
    public function show($id)
    {
        $product = Product::with(['user', 'seller.user'])->findOrFail($id);
        
        
        $imageUrl = $product->full_image_url; // ✅ Use model method for full URL
        
        // Get vegetable slug for reference
        $vegetableSlug = $product->getVegetableSlug();
        
        // Ensure analytics fields are included (same as index method)
        $product->total_sold = $product->total_sold ?? 0;
        $product->avg_rating = $product->avg_rating ?? 0;
        $product->ratings_count = $product->ratings_count ?? 0;
        
        // Use the actual available_units from the database (don't override)
        // If no units are set, fallback to default units for the vegetable type
        if (!$product->available_units || empty($product->available_units)) {
            $availableUnits = \App\Models\UnitConversion::getAvailableUnits($vegetableSlug);
            $product->available_units = $availableUnits;
        }
        $product->vegetable_slug = $vegetableSlug;

        // Variation prices and stocks are already loaded from the database
        
        // Add debug info for button state calculation
        $product->debug_info = [
            'premium_stock_kg' => (float)($product->premium_stock_kg ?? 0),
            'type_a_stock_kg' => (float)($product->type_a_stock_kg ?? 0),
            'type_b_stock_kg' => (float)($product->type_b_stock_kg ?? 0),
            'main_stock_kg' => (float)($product->stock_kg ?? 0),
            'total_variation_stock' => (float)($product->premium_stock_kg ?? 0) + (float)($product->type_a_stock_kg ?? 0) + (float)($product->type_b_stock_kg ?? 0),
            'available_units' => $product->available_units
        ];

        return response()->json([
            'message' => 'Product fetched successfully',
            'data' => $product
        ]);
    }

    /**
     * Create a product (seller only).
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user->is_seller) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'stock_kg' => 'required|numeric|min:0',
            'price_per_kg' => 'required|numeric|min:0',
            'available_units' => 'required|string', // JSON string
            'pieces_per_bundle' => 'nullable|integer|min:1',
            'variation_type' => 'nullable|string|max:255',
            'unit_pricing' => 'nullable|string',
            'images.*' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            
            // Variation prices
            'premium_price_per_kg' => 'nullable|numeric|min:0',
            'type_a_price_per_kg' => 'nullable|numeric|min:0',
            'type_b_price_per_kg' => 'nullable|numeric|min:0',
            
            // Variation stocks
            'premium_stock_kg' => 'nullable|numeric|min:0',
            'type_a_stock_kg' => 'nullable|numeric|min:0',
            'type_b_stock_kg' => 'nullable|numeric|min:0',
        ]);

        // Parse available_units from JSON string
        $availableUnits = json_decode($validated['available_units'], true);
        if (!$availableUnits || !is_array($availableUnits) || empty($availableUnits)) {
            return response()->json(['error' => 'Available units must be a valid array with at least one unit'], 422);
        }

        // Validate pieces_per_bundle if tali is in available units
        if (in_array('tali', $availableUnits) && empty($validated['pieces_per_bundle'])) {
            return response()->json(['error' => 'Pieces per bundle is required when tali unit is selected'], 422);
        }

        if (array_key_exists('unit_pricing', $validated) && $validated['unit_pricing'] !== null && $validated['unit_pricing'] !== '') {
            $unitPricing = json_decode($validated['unit_pricing'], true);
            if (!is_array($unitPricing)) {
                return response()->json(['error' => 'Unit pricing must be a valid JSON structure'], 422);
            }
            $validated['unit_pricing'] = $unitPricing;
        } else {
            unset($validated['unit_pricing']);
        }

        // Handle multiple images
        $imagePaths = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                if ($image) {
                    $path = $image->store('products', 'public');
                    $imagePaths[] = $path;
                }
            }
        }
        
        // Use the first image as the main image_url for backward compatibility
        if (!empty($imagePaths)) {
            $validated['image_url'] = $imagePaths[0];
            // Store additional images (skip the first one as it's already in image_url)
            if (count($imagePaths) > 1) {
                $validated['additional_images'] = array_slice($imagePaths, 1);
            }
        }

        $validated['seller_id'] = $user->id;
        
        // Convert available_units back to array for storage
        $validated['available_units'] = $availableUnits;

        $product = Product::create($validated);
        $imageUrl = $product->full_image_url; // ✅ Use model method for full URL

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    /**
     * Update a product (seller only).
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $product = Product::findOrFail($id);

        // Debug logging for product update
        \Log::info('Product Update Request', [
            'product_id' => $id,
            'method' => 'PUT',
            'user_id' => $user->id,
            'seller_id' => $product->seller_id,
            'product_name' => $product->product_name,
            'request_data' => $request->except(['images'])
        ]);

        if ($product->seller_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'stock_kg' => 'sometimes|numeric|min:0',
            'price_per_kg' => 'sometimes|numeric|min:0',
            'available_units' => 'sometimes|string', // JSON string
            'pieces_per_bundle' => 'nullable|integer|min:1',
            'image_url' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'unit_pricing' => 'nullable|string',
            
            // Variation prices
            'premium_price_per_kg' => 'nullable|numeric|min:0',
            'type_a_price_per_kg' => 'nullable|numeric|min:0',
            'type_b_price_per_kg' => 'nullable|numeric|min:0',
            
            // Variation stocks
            'premium_stock_kg' => 'nullable|numeric|min:0',
            'type_a_stock_kg' => 'nullable|numeric|min:0',
            'type_b_stock_kg' => 'nullable|numeric|min:0',
        ]);

        // Parse available_units from JSON string if provided
        if (isset($validated['available_units'])) {
            $availableUnits = json_decode($validated['available_units'], true);
            if (!$availableUnits || !is_array($availableUnits) || empty($availableUnits)) {
                return response()->json(['error' => 'Available units must be a valid array with at least one unit'], 422);
            }
            
            // Validate pieces_per_bundle if tali is in available units
            if (in_array('tali', $availableUnits) && empty($validated['pieces_per_bundle'])) {
                return response()->json(['error' => 'Pieces per bundle is required when tali unit is selected'], 422);
            }
            
            $validated['available_units'] = $availableUnits;
        }

        if (isset($validated['unit_pricing'])) {
            $unitPricing = json_decode($validated['unit_pricing'], true);
            if (!is_array($unitPricing)) {
                return response()->json(['error' => 'Unit pricing must be a valid JSON structure'], 422);
            }
            $validated['unit_pricing'] = $unitPricing;
        }


        if ($request->hasFile('image_url')) {
            // 🔥 Delete old image if exists
            if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
                Storage::disk('public')->delete($product->image_url);
            }

            $path = $request->file('image_url')->store('products', 'public');
            $validated['image_url'] = $path;
        } elseif ($request->has('image_url') && $request->image_url === "") {
            // If explicitly cleared
            if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
                Storage::disk('public')->delete($product->image_url);
            }
            $validated['image_url'] = null;
        }

        $product->update($validated);
        
        // Debug logging to confirm UPDATE operation
        \Log::info('Product Update Completed', [
            'product_id' => $product->id,
            'operation' => 'UPDATE',
            'updated_fields' => array_keys($validated),
            'new_product_name' => $product->product_name,
            'new_stock_kg' => $product->stock_kg,
            'new_price_per_kg' => $product->price_per_kg
        ]);
        
        $imageUrl = $product->image_url; // ✅ Use model accessor
        
        // Construct full URL for frontend
        if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
            $baseUrl = request()->getSchemeAndHttpHost();
            $imageUrl = $baseUrl . '/' . $imageUrl;
        }
        
        $product->full_image_url = $imageUrl;
        $product->fixed_image_url = $imageUrl;

        // ✅ Match AdminProductController response format
        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Delete a product (seller only).
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $product = Product::findOrFail($id);

        if ($product->seller_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // 🔥 Delete product image if exists
        if ($product->image_url && Storage::disk('public')->exists($product->image_url)) {
            Storage::disk('public')->delete($product->image_url);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully']);
    }

    /**
     * List current seller’s products.
     */
    public function myProducts()
    {
        $user = Auth::user();

        if (!$user->is_seller) {
            return response()->json(['error' => 'Only sellers can view their products'], 403);
        }

        $products = Product::where('seller_id', $user->id)->get()->map(function ($p) {
            $imageUrl = $p->image_url; // ✅ Use model accessor
            
            // Construct full URL for frontend
            if ($imageUrl && !str_starts_with($imageUrl, 'http')) {
                $baseUrl = request()->getSchemeAndHttpHost();
                $imageUrl = $baseUrl . '/' . $imageUrl;
            }
            
            $p->full_image_url = $imageUrl;
            $p->fixed_image_url = $imageUrl;
            return $p;
        });

        return response()->json([
            'message' => 'My products fetched successfully',
            'data' => $products
        ]);
    }


    /**
     * 🔥 Admin: Get products by user (for admin-user-products page).
     */
    public function getUserProducts($sellerId)
    {
        $products = Product::where('seller_id', $sellerId)->get()->map(function ($p) {
            $imageUrl = $p->image_url; // ✅ Use model accessor
            $p->full_image_url = $imageUrl;
            $p->fixed_image_url = $imageUrl;
            return $p;
        });

        return response()->json([
            'message' => 'User products fetched successfully',
            'data' => $products
        ]);
    }

    /**
     * Check if a product is eligible for preorder
     */
    public function checkPreorderEligibility($id)
    {
        $product = Product::findOrFail($id);

        // Determine if product can be preordered
        $isEligible = false;
        $reason = '';

        // Product is eligible for preorder if:
        // 1. Stock is 0 or low (< 10)
        // 2. Product is marked as allowing preorders (if such field exists)
        // 3. Product is not discontinued

        if ($product->stock == 0) {
            $isEligible = true;
            $reason = 'Product is currently out of stock';
        } elseif ($product->stock > 0 && $product->stock < 10) {
            $isEligible = true;
            $reason = 'Product has low stock - preorder to secure your order';
        } else {
            $isEligible = false;
            $reason = 'Product is currently in stock';
        }

        // Check if product allows preorders (if field exists)
        if (isset($product->allow_preorder) && !$product->allow_preorder) {
            $isEligible = false;
            $reason = 'This product does not accept preorders';
        }

        return response()->json([
            'eligible' => $isEligible,
            'reason' => $reason,
            'current_stock' => $product->stock,
            'product' => [
                'id' => $product->product_id,
                'name' => $product->product_name,
                'price' => $product->price,
                'stock' => $product->stock,
            ]
        ]);
    }
}