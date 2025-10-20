# OnlyFarms - Crop Reservation & Harvest Pipeline Business Rules

## Overview
This document defines the business rules and policies for the Crop Reservation & Harvest pipeline, ensuring consistent behavior across the preorder → harvest → fulfillment → order → delivery flow.

## Core Invariant
Every record (harvest, preorder, order) persists `variationId`, `unitKey`, `unit_weight_kg`, and `unit_price` at the time of the action so historical data is auditable and conversions are consistent.

---

## 1. Decimal Precision & Calculations

### Weight Precision
- **Decimal places**: 4 decimals for all weight calculations (kg)
- **Example**: `15.2500 kg` (not `15.25 kg`)
- **Rationale**: Ensures precision for small unit conversions (e.g., tali = 0.2500 kg)

### Price Precision
- **Decimal places**: 2 decimals for all price calculations (PHP)
- **Example**: `₱125.50` (not `₱125.5`)
- **Rationale**: Standard currency precision for Philippine Peso

### Unit Conversion Rules
- All unit conversions must preserve the 4-decimal precision
- Rounding occurs only at the final display level
- Internal calculations maintain full precision

---

## 2. Preorder Matching Policy

### Primary Policy: FIFO (First In, First Out)
- Preorders are matched in chronological order (by `created_at`)
- Within the same time, priority by `id` (lower ID = higher priority)

### Grouping Rules
- Group by: `product_id` + `variation_type` + `unit_key`
- Matching occurs within each group independently
- Example: Premium kg preorders are matched separately from Regular sack preorders

### Matching Algorithm
1. Find all `pending` preorders for the same product/variation/unit
2. Sort by `created_at ASC`, then by `id ASC`
3. Allocate harvest weight to preorders in order until harvest is exhausted
4. Update preorder status to `reserved` when allocated
5. If harvest weight is insufficient, mark remaining preorders as `pending`

---

## 3. Partial Fulfillment Rules

### Policy: Allow Partial Fulfillment
- Sellers can fulfill preorders partially when harvest yield is insufficient
- Partial fulfillment creates proportional pricing adjustments

### Refund Policy
- **Full refund**: If preorder cannot be fulfilled at all (crop failure, quality issues)
- **Proportional refund**: If only partial quantity can be fulfilled
- **No refund**: If buyer cancels after harvest is published

### Partial Fulfillment Process
1. Seller marks preorder as "Partially Fulfilled"
2. System calculates actual vs requested quantity
3. Order is created with actual quantity
4. Refund is processed for unfulfilled portion
5. Preorder status becomes `partially_fulfilled`

---

## 4. Auto-Timeout Rules

### Seller Confirmation Window
- **Duration**: 24 hours from harvest publication
- **Action**: Seller must confirm harvest details and publish
- **Timeout**: If no action, harvest remains `pending` (no auto-cancellation)

### Harvest Verification SLA
- **Duration**: 48 hours from harvest submission
- **Action**: Admin must verify harvest quality and details
- **Timeout**: If no action, harvest remains `unverified` (no auto-approval)

### Preorder Cancellation Window
- **Buyer cancellation**: Allowed until harvest is published
- **Seller cancellation**: Allowed until harvest is published
- **Post-harvest**: No cancellations allowed (except for quality issues)

---

## 5. Status Transition Rules

### Preorder Status Flow
```
pending → reserved → ready → fulfilled
   ↓         ↓
cancelled  cancelled
```

### Harvest Status Flow
```
draft → submitted → verified → published
  ↓         ↓
deleted   rejected
```

### Order Status Flow (from preorder fulfillment)
```
pending → confirmed → dispatched → in_transit → delivered
   ↓
cancelled
```

---

## 6. Stock Management Rules

### Stock Updates
- **Harvest published**: Increase product stock by harvest weight
- **Preorder fulfilled**: Decrease product stock by fulfilled weight
- **Preorder cancelled**: No stock change (stock was never reserved)

### Stock Validation
- Preorders can only be fulfilled if sufficient stock exists
- Stock checks occur at fulfillment time (not at preorder creation)
- Race conditions handled via database transactions and optimistic locking

---

## 7. Quality & Verification Rules

### Harvest Quality Grades
- **Grade A**: Premium quality, full price
- **Grade B**: Good quality, 90% of price
- **Grade C**: Acceptable quality, 80% of price
- **Reject**: Not suitable for sale, preorders cancelled with full refund

### Verification Requirements
- All harvests must be verified by admin before publication
- Quality grade affects pricing for all preorders
- Photos and lot codes are mandatory for verification

---

## 8. Notification Rules

### Trigger Events
- **New preorder**: Notify seller immediately
- **Harvest published**: Notify all pending preorder buyers
- **Preorder matched**: Notify buyer of reservation
- **Preorder fulfilled**: Notify buyer of order creation
- **Harvest rejected**: Notify seller and cancel affected preorders

### Notification Channels
- **In-app notifications**: Real-time updates
- **Push notifications**: For mobile app users
- **Email notifications**: For critical events (harvest rejection, payment issues)

---

## 9. Audit & Compliance Rules

### Data Immutability
- Unit fields (`unit_key`, `unit_weight_kg`, `unit_price`) cannot be modified after preorder creation
- Harvest data cannot be modified after verification
- All changes must be logged with timestamp and actor

### Audit Trail Requirements
- Log all status changes with timestamp and user ID
- Log all stock adjustments with reason
- Log all price changes with justification
- Maintain audit logs for 2 years minimum

---

## 10. Error Handling & Edge Cases

### Race Conditions
- Multiple sellers fulfilling same preorder: Use optimistic locking
- Concurrent harvest publications: Use database transactions
- Stock depletion during fulfillment: Return 409 Conflict with current stock level

### Data Consistency
- Unit conversions must be validated against current unit definitions
- Price calculations must use stored unit prices (not current prices)
- Stock levels must be consistent across all operations

### Failure Recovery
- Harvest verification failure: Allow resubmission with corrections
- Preorder fulfillment failure: Rollback transaction and notify seller
- Payment failure: Mark order as failed and notify buyer

---

## 11. Performance & Scalability Rules

### Matching Job Performance
- Matching job must complete within 30 seconds for 1000+ preorders
- Use database indexes for efficient preorder lookups
- Implement job queuing for large harvest publications

### API Response Times
- Preorder eligibility check: < 200ms
- Preorder creation: < 500ms
- Harvest matching: < 30 seconds (background job)
- Order fulfillment: < 1 second

---

## 12. Security & Authorization Rules

### Access Control
- Sellers can only manage their own harvests and preorders
- Buyers can only view their own preorders
- Admins can verify any harvest and view all data
- API endpoints must validate user permissions

### Data Protection
- Sensitive user data (addresses, phone numbers) only accessible to authorized users
- Harvest photos and lot codes are seller property
- Audit logs must not contain sensitive user information

---

## Implementation Notes

### Database Constraints
- All foreign keys must have proper constraints
- Unique constraints on lot codes and preorder IDs
- Check constraints for status values and price ranges

### API Validation
- All input data must be validated against business rules
- Unit conversions must be validated against current definitions
- Stock levels must be validated before operations

### Testing Requirements
- Unit tests for all business rule calculations
- Integration tests for complete preorder → harvest → fulfillment flow
- Performance tests for matching algorithm with large datasets
- Edge case testing for race conditions and error scenarios

---

**Document Version**: 1.0  
**Last Updated**: January 2025  
**Next Review**: February 2025
