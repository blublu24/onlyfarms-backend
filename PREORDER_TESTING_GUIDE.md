# Preorder System Testing Guide

## Overview
This guide provides comprehensive testing procedures for the preorder system with unit-specific functionality.

## Test Environment Setup

### Prerequisites
1. Laravel backend running on `http://localhost:8000`
2. React Native frontend running
3. Database with test data
4. Postman or similar API testing tool

### Test Data Requirements
- Products with variations (premium, type_a, type_b, regular)
- Products with stock ≤ 2kg for preorder eligibility
- Active crop schedules for products
- Unit conversions configured
- Test users (sellers and consumers)

## API Endpoints Testing

### 1. Preorder Eligibility Check
**Endpoint:** `GET /api/products/{id}/preorder-eligibility`

#### Test Cases:
```bash
# Test 1: Product with sufficient stock (should not be eligible)
GET /api/products/1/preorder-eligibility
Expected: {"eligible": false, "message": "Product has sufficient stock"}

# Test 2: Product with low stock + crop schedule (should be eligible)
GET /api/products/2/preorder-eligibility
Expected: {"eligible": true, "harvest_date": "2025-02-15", "variations": [...]}

# Test 3: Product without crop schedule (should not be eligible)
GET /api/products/3/preorder-eligibility
Expected: {"eligible": false, "message": "No active crop schedule found"}
```

#### Manual Testing Steps:
1. Navigate to product detail page
2. Check if "Pre-Order Now" button appears when stock ≤ 2kg
3. Verify unit options are displayed correctly
4. Test variation selection and unit selection

### 2. Create Preorder
**Endpoint:** `POST /api/preorders`

#### Test Payload:
```json
{
    "consumer_id": 1,
    "product_id": 2,
    "seller_id": 2,
    "quantity": 3,
    "expected_availability_date": "2025-02-15",
    "variation_type": "premium",
    "variation_name": "Premium",
    "unit_key": "sack",
    "unit_price": 150.00,
    "unit_weight_kg": 25.0
}
```

#### Test Cases:
- Valid preorder creation
- Invalid unit for product
- Insufficient stock
- Missing required fields
- Unauthorized user

### 3. Get Preorder Details
**Endpoint:** `GET /api/preorders/{id}`

#### Test Cases:
- Seller accessing their preorder
- Consumer accessing their preorder
- Unauthorized access attempt
- Non-existent preorder

### 4. Update Preorder
**Endpoint:** `PUT /api/preorders/{id}`

#### Test Payload:
```json
{
    "harvest_date": "2025-02-20",
    "notes": "Updated harvest date due to weather"
}
```

#### Test Cases:
- Valid harvest date update
- Invalid date (past date)
- Unauthorized update attempt
- Non-existent preorder

### 5. Fulfill Preorder
**Endpoint:** `POST /api/preorders/{id}/fulfill`

#### Test Cases:
- Successful fulfillment
- Preorder not ready for fulfillment
- Unauthorized fulfillment attempt
- Stock insufficient for fulfillment

### 6. Cancel Preorder
**Endpoint:** `POST /api/preorders/{id}/cancel`

#### Test Cases:
- Consumer canceling their preorder
- Seller canceling preorder
- Cancel after harvest date
- Already fulfilled preorder

## Frontend Testing

### 1. Product Detail Page
**File:** `app/(tabs)/productdetailscreen.tsx`

#### Test Scenarios:
1. **Preorder Eligible Product:**
   - Verify "Pre-Order Now" button appears
   - Check harvest date display
   - Verify "Add to Cart" and "Buy Now" are disabled
   - Test navigation to PreorderPage

2. **Regular Product:**
   - Verify normal purchase buttons work
   - No preorder options shown

### 2. Preorder Placement Page
**File:** `app/(tabs)/PreorderPage.tsx`

#### Test Scenarios:
1. **Variation Selection:**
   - Test horizontal scrolling of variations
   - Verify selection states
   - Check price updates per variation

2. **Unit Selection:**
   - Test unit cards display
   - Verify unit selection updates
   - Check weight calculations

3. **Quantity Input:**
   - Test quantity validation
   - Verify total weight calculation
   - Check total price calculation

4. **Form Submission:**
   - Test with valid data
   - Test validation errors
   - Verify API integration

### 3. Preorder List Page
**File:** `app/(tabs)/PreorderListPage.tsx`

#### Test Scenarios:
1. **List Display:**
   - Verify preorders load correctly
   - Check unit information display
   - Test status indicators

2. **Cancellation:**
   - Test cancel button visibility
   - Verify cancellation flow
   - Check confirmation dialog

### 4. Seller Orders Page
**File:** `app/(tabs)/SellerOrdersPage.tsx`

#### Test Scenarios:
1. **Tab Navigation:**
   - Test switching between Orders and Preorders tabs
   - Verify count displays
   - Check active tab styling

2. **Preorder Management:**
   - Test preorder card tap navigation
   - Verify unit information display
   - Check fulfillment button states

### 5. Preorder Management Page
**File:** `app/(tabs)/PreorderManagementPage.tsx`

#### Test Scenarios:
1. **Details Display:**
   - Verify all preorder information shows
   - Check unit-specific data
   - Test status indicators

2. **Harvest Date Editing:**
   - Test modal opening
   - Verify date input validation
   - Test save functionality

3. **Fulfillment:**
   - Test fulfillment button
   - Verify confirmation dialog
   - Check success/error handling

## Database Testing

### 1. Preorder Creation
```sql
-- Verify preorder record created
SELECT * FROM preorders WHERE id = {preorder_id};

-- Check unit-specific fields
SELECT variation_type, variation_name, unit_key, unit_weight_kg, unit_price 
FROM preorders WHERE id = {preorder_id};
```

### 2. Order Creation from Fulfillment
```sql
-- Verify order created
SELECT * FROM orders WHERE preorder_id = {preorder_id};

-- Check order item with unit data
SELECT * FROM order_items WHERE order_id = {order_id};

-- Verify stock reduction
SELECT stock_kg, premium_stock_kg FROM products WHERE product_id = {product_id};
```

### 3. Status Updates
```sql
-- Check preorder status update
SELECT status FROM preorders WHERE id = {preorder_id};

-- Verify order status
SELECT status FROM orders WHERE preorder_id = {preorder_id};
```

## Integration Testing

### 1. End-to-End Preorder Flow
1. **Setup:** Create product with low stock and crop schedule
2. **Eligibility:** Verify product shows preorder option
3. **Placement:** Create preorder with unit selection
4. **Management:** Seller views and manages preorder
5. **Fulfillment:** Convert preorder to order
6. **Verification:** Check order appears in seller orders

### 2. Unit Conversion Testing
1. **Setup:** Product with multiple unit options
2. **Selection:** Test different unit selections
3. **Calculation:** Verify weight and price calculations
4. **Storage:** Check unit data stored correctly
5. **Display:** Verify unit info shows in all views

### 3. Stock Management Testing
1. **Initial Stock:** Record initial product stock
2. **Preorder Creation:** Create preorder with specific quantity
3. **Stock Check:** Verify stock not reduced yet
4. **Fulfillment:** Fulfill preorder
5. **Final Stock:** Verify stock reduced correctly

## Performance Testing

### 1. API Response Times
- Eligibility check: < 500ms
- Preorder creation: < 1s
- List operations: < 800ms
- Fulfillment: < 2s

### 2. Database Queries
- Monitor query count per request
- Check for N+1 query problems
- Verify proper indexing usage

## Error Handling Testing

### 1. Network Errors
- Test with no internet connection
- Test with slow network
- Verify proper error messages

### 2. Validation Errors
- Test invalid data submission
- Verify field validation
- Check error message display

### 3. Authorization Errors
- Test unauthorized access
- Verify proper error responses
- Check redirect behavior

## Browser Testing

### 1. API Testing with Postman
```bash
# Collection: Preorder System Tests
# Environment: Local Development
# Variables:
# - base_url: http://localhost:8000/api
# - auth_token: {your_token}
# - product_id: 2
# - preorder_id: {created_preorder_id}
```

### 2. Frontend Testing
- Test on different screen sizes
- Verify responsive design
- Check touch interactions
- Test navigation flows

## Test Data Setup Script

```php
// Create test data for preorder testing
$product = Product::create([
    'product_name' => 'Test Rice',
    'stock_kg' => 1.5, // Low stock for preorder eligibility
    'price_per_kg' => 50.00,
    'premium_stock_kg' => 0.8,
    'premium_price_per_kg' => 75.00,
    'type_a_stock_kg' => 1.2,
    'type_a_price_per_kg' => 60.00,
]);

$cropSchedule = CropSchedule::create([
    'product_id' => $product->product_id,
    'is_active' => true,
    'expected_harvest_start' => now()->addDays(30),
    'expected_harvest_end' => now()->addDays(45),
]);

$unitConversion = UnitConversion::create([
    'product_id' => $product->product_id,
    'unit_key' => 'sack',
    'unit_label' => 'Sack',
    'weight_kg' => 25.0,
    'price' => 150.00,
]);
```

## Test Checklist

### Backend API Tests
- [ ] Eligibility endpoint returns correct data
- [ ] Preorder creation with unit data
- [ ] Preorder retrieval and updates
- [ ] Fulfillment flow creates proper order
- [ ] Cancellation updates status correctly
- [ ] Authorization works for all endpoints
- [ ] Validation prevents invalid data
- [ ] Stock management works correctly

### Frontend Tests
- [ ] Product detail shows preorder option
- [ ] Preorder page handles unit selection
- [ ] Preorder list displays unit information
- [ ] Seller page shows preorder management
- [ ] Management page allows editing
- [ ] All navigation flows work
- [ ] Error handling displays properly
- [ ] Loading states work correctly

### Integration Tests
- [ ] End-to-end preorder flow
- [ ] Unit data preserved through flow
- [ ] Stock updates correctly
- [ ] Status transitions work
- [ ] Database integrity maintained
- [ ] Performance meets requirements

## Reporting Issues

When reporting issues, include:
1. Test case that failed
2. Expected vs actual behavior
3. Steps to reproduce
4. Screenshots or error messages
5. Environment details
6. Test data used

## Success Criteria

The preorder system is considered ready for production when:
- All API endpoints return correct responses
- Frontend displays unit information correctly
- End-to-end flow works without errors
- Stock management is accurate
- Performance meets requirements
- Error handling is comprehensive
- All test cases pass consistently
