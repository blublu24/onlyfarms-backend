# Preorder System Fixes - October 22, 2025

## Issues Fixed

### 1. ✅ Estimated Yield Quantity Not Showing in PreorderPage
**Problem:** Crop schedules with harvest dates starting TODAY were not being included in preorder eligibility checks.

**Root Cause:** The query used `>` (greater than) instead of `>=` (greater than or equal to), excluding schedules starting on the current date.

**Fix:** Changed line 246 in `PreorderController.php`:
```php
// Before
->where('expected_harvest_start', '>', now()->toDateString())

// After
->where('expected_harvest_start', '>=', now()->toDateString())
```

---

### 2. ✅ Preorder Creation Failing (Decimal Quantity Validation)
**Problem:** Preorders with decimal quantities (e.g., 15.5 kg) were being rejected by validation.

**Root Cause:** Validation required `integer` type but frontend sends decimal quantities.

**Fix:** Changed line 20 in `PreorderController.php`:
```php
// Before
'quantity' => 'required|integer|min:1',

// After
'quantity' => 'required|numeric|min:0.1',
```

---

### 3. ✅ Preorders Not Showing in OrdersPage & SellerOrdersPage
**Problem:** After placing preorders, they wouldn't appear in either the buyer's or seller's preorder lists.

**Root Cause:** **Route ordering issue!** Laravel was matching `/preorders/{id}` before `/preorders/consumer` and `/preorders/seller`, treating "consumer" and "seller" as preorder IDs.

**Fix:** Reordered routes in `routes/api.php` (lines 582-595):
```php
// Before (WRONG ORDER)
Route::get('/preorders/{preorder}', [PreorderController::class, 'show']);
Route::get('/preorders/consumer', [PreorderController::class, 'consumerPreorders']);
Route::get('/preorders/seller', [PreorderController::class, 'sellerPreorders']);

// After (CORRECT ORDER)
// Specific routes FIRST
Route::get('/preorders/consumer', [PreorderController::class, 'consumerPreorders']);
Route::get('/preorders/seller', [PreorderController::class, 'sellerPreorders']);
// Parameterized routes AFTER
Route::get('/preorders/{id}', [PreorderController::class, 'show']);
```

**Key Lesson:** In Laravel routing, always place specific routes BEFORE parameterized routes to avoid route conflicts.

---

## Files Modified

1. `app/Http/Controllers/PreorderController.php`
   - Line 20: Changed quantity validation from `integer` to `numeric`
   - Line 246: Changed date comparison from `>` to `>=`

2. `routes/api.php`
   - Lines 582-595: Reordered preorder routes
   - Removed duplicate route definitions
   - Added missing `reject` route

---

## Testing Verification

✅ Preorders are now created successfully with decimal quantities
✅ Crop schedules with harvest dates starting today are included in eligibility checks
✅ Preorders appear correctly in OrdersPage.tsx (consumer view)
✅ Preorders appear correctly in SellerOrdersPage.tsx (seller view)
✅ Route matching works correctly for `/preorders/consumer` and `/preorders/seller`

---

## Current Database State

As of testing:
- Total preorders in database: 2
- Preorder #11: Status: pending
- Preorder #10: Status: accepted

Both preorders are now visible in the frontend.

