# Profile Page Error Fix

## Issue
Buyer's profile page was throwing an error:
```
ERROR  Error fetching sellers: {"error": "Authentication required", "message": "Unauthenticated."}
```

## Root Cause
The `fetchPreorderNotification` function in `profile.tsx` was trying to fetch seller information using `/sellers/{id}` endpoint, which either:
1. Doesn't exist or is protected
2. Requires authentication that wasn't being passed correctly
3. Wasn't the most efficient way to get seller info

## Solution
Made 3 fixes to handle this gracefully:

### 1. Frontend: Better Error Handling (`profile.tsx`)
- Added try-catch around the seller fetch to prevent page crashes
- Check if seller info is already in the order object (more efficient)
- Use fallback name "Seller" if fetch fails
- Improved response data parsing: `ordersResponse.data?.data || ordersResponse.data || []`

### 2. Backend: Add Seller Relationship to Order Model
- Added `seller_id` to fillable array in `Order.php`
- Added `seller()` relationship method:
  ```php
  public function seller(): BelongsTo
  {
      return $this->belongsTo(\App\Models\User::class, 'seller_id');
  }
  ```

### 3. Backend: Eager Load Seller in OrderController
- Updated `index()` method to eager load seller relationship:
  ```php
  $orders = Order::with(['items.product', 'seller'])
      ->where('user_id', $request->user()->id)
      ->latest()
      ->get();
  ```

## Benefits
1. ✅ No more authentication errors in profile page
2. ✅ More efficient data fetching (one query instead of multiple)
3. ✅ Seller name now available directly in order object
4. ✅ Graceful fallback if seller info unavailable
5. ✅ Profile page doesn't crash if there are errors

## How It Works Now
```typescript
// Frontend checks order data first (efficient)
if (preorderOrder.seller?.name || preorderOrder.seller?.business_name) {
  sellerName = preorderOrder.seller.business_name || preorderOrder.seller.name;
} else {
  // Only fetch if not already in order object
  try {
    const sellerResponse = await api.get(`/sellers/${preorderOrder.seller_id}`);
    sellerName = seller?.business_name || seller?.name || 'Seller';
  } catch (sellerError) {
    console.log('Could not fetch seller info, using default name');
    sellerName = 'Seller'; // Graceful fallback
  }
}
```

## Testing
1. ✅ Profile page loads without errors
2. ✅ Preorder notification shows seller name (if available)
3. ✅ Fallback name displayed if seller info not available
4. ✅ No authentication errors

