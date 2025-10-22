# Preorder Accept/Reject Status Fix

## Issue
When seller tried to accept a preorder, the system crashed with error:
```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status' at row 1
SQL: update `preorders` set `status` = accepted
```

## Root Cause
The `preorders` table's `status` column is an ENUM type with limited allowed values:
- `['pending', 'reserved', 'ready', 'fulfilled', 'cancelled', 'partially_fulfilled']`

But the new accept/reject functionality was trying to set:
- `'accepted'` - NOT in allowed values ❌
- `'rejected'` - NOT in allowed values ❌

Additionally, the controller was trying to set timestamps `accepted_at` and `rejected_at` which didn't exist in the database.

## Solution
Created two migrations to fix this:

### 1. Add Status Values to ENUM
**Migration**: `2025_10_22_041706_add_accepted_rejected_to_preorders_status.php`

Updated the ENUM to include the new statuses:
```php
DB::statement("ALTER TABLE preorders MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'reserved', 'ready', 'fulfilled', 'cancelled', 'partially_fulfilled') DEFAULT 'pending'");
```

**New allowed statuses**:
- ✅ `'pending'` - Initial state
- ✅ `'accepted'` - Seller accepted the preorder
- ✅ `'rejected'` - Seller rejected the preorder
- ✅ `'reserved'` - Reserved for harvest
- ✅ `'ready'` - Ready for fulfillment
- ✅ `'fulfilled'` - Completed
- ✅ `'cancelled'` - Buyer cancelled
- ✅ `'partially_fulfilled'` - Partially completed

### 2. Add Timestamp Columns
**Migration**: `2025_10_22_041731_add_accepted_rejected_timestamps_to_preorders.php`

Added timestamp columns for audit tracking:
```php
$table->timestamp('accepted_at')->nullable()->after('status');
$table->timestamp('rejected_at')->nullable()->after('accepted_at');
```

### 3. Update Preorder Model
**File**: `app/Models/Preorder.php`

Added new fields to fillable array and casts:
```php
protected $fillable = [
    // ... existing fields
    'accepted_at',
    'rejected_at',
];

protected $casts = [
    // ... existing casts
    'accepted_at' => 'datetime',
    'rejected_at' => 'datetime',
];
```

## Status Flow
```
pending → accepted → (order created automatically)
   ↓
rejected (terminal state)
   ↓
cancelled (buyer cancels before seller accepts)
```

## Benefits
1. ✅ Seller can accept preorders without database errors
2. ✅ Seller can reject preorders without database errors
3. ✅ Proper audit trail with timestamps
4. ✅ Status transitions are tracked
5. ✅ Frontend displays correct status badges

## Testing
1. ✅ Seller accepts preorder → Status changes to 'accepted' ✅
2. ✅ Seller rejects preorder → Status changes to 'rejected' ✅
3. ✅ Accepted preorder creates order automatically ✅
4. ✅ Timestamps are recorded correctly ✅

## Commands Run
```bash
php artisan make:migration add_accepted_rejected_to_preorders_status --table=preorders
php artisan make:migration add_accepted_rejected_timestamps_to_preorders --table=preorders
php artisan migrate
```

Both migrations ran successfully! ✅

