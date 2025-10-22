# Preorder Route Fix

## Issue
Preorders were not showing up in the preorders section of both `SellerOrdersPage.tsx` and `OrdersPage.tsx` after the buyer placed them.

## Root Cause
The route definitions in `routes/api.php` had the wrong order. Laravel was matching `/preorders/consumer` and `/preorders/seller` as `/preorders/{id}` with `id = 'consumer'` or `id = 'seller'`, instead of calling the correct controller methods.

## Solution
Reordered the preorder routes so that specific routes come **before** generic parameter routes:

```php
// ✅ CORRECT ORDER
Route::get('/preorders/consumer', [PreorderController::class, 'consumerPreorders']);
Route::get('/preorders/seller', [PreorderController::class, 'sellerPreorders']);
Route::get('/preorders/{id}', [PreorderController::class, 'show']); // Generic route comes AFTER
```

```php
// ❌ WRONG ORDER
Route::get('/preorders/{id}', [PreorderController::class, 'show']); // This would match "consumer" as an ID
Route::get('/preorders/consumer', [PreorderController::class, 'consumerPreorders']); // Never reached
```

## Changes Made

### 1. `routes/api.php`
- Moved `/preorders/consumer` and `/preorders/seller` routes above generic `/preorders/{id}` route
- Removed duplicate route definitions
- Added comment explaining the importance of route order

### 2. `app/Http/Controllers/PreorderController.php`
- Updated `consumerPreorders()` to return consistent response format with `data` wrapper
- Updated `sellerPreorders()` to return consistent response format with `data` wrapper

## Response Format
Both endpoints now return:
```json
{
  "message": "Consumer/Seller preorders fetched successfully",
  "data": [...]
}
```

This matches the format expected by the frontend code which checks for `res.data?.data || res.data`.

## Testing
After this fix:
1. ✅ Buyers can see their preorders in `OrdersPage.tsx` preorders tab
2. ✅ Sellers can see incoming preorders in `SellerOrdersPage.tsx` preorders tab
3. ✅ Accept/Reject functionality works correctly
4. ✅ Preorder transitions to order after acceptance

