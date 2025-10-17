<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\LalamoveDelivery;
use App\Models\Seller;
use App\Services\LalamoveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class LalamoveController extends Controller
{
    private LalamoveService $lalamoveService;

    public function __construct(LalamoveService $lalamoveService)
    {
        $this->lalamoveService = $lalamoveService;
    }

    /**
     * Get Lalamove quotation for delivery
     * 
     * POST /api/lalamove/quotation
     */
    public function getQuotation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'nullable|exists:orders,id',
                'pickup_address' => 'required|string|max:500',
                'dropoff_address' => 'required|string|max:500',
                'service_type' => 'nullable|string|in:MOTORCYCLE,VAN,TRUCK'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $serviceType = $data['service_type'] ?? 'MOTORCYCLE';

            Log::info('Lalamove Quotation Request', [
                'user_id' => Auth::id(),
                'order_id' => $data['order_id'] ?? null,
                'pickup_address' => $data['pickup_address'],
                'dropoff_address' => $data['dropoff_address'],
                'service_type' => $serviceType
            ]);

            // Get quotation from Lalamove
            $quotation = $this->lalamoveService->getQuotation(
                $data['pickup_address'],
                $data['dropoff_address'],
                $serviceType
            );

            if (!$quotation['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to get quotation from Lalamove',
                    'details' => $quotation['error']
                ], 400);
            }

            // If order_id is provided, create/update delivery record
            if ($data['order_id']) {
                $order = Order::findOrFail($data['order_id']);
                
                // Verify user has access to this order
                if ($order->user_id !== Auth::id()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Unauthorized access to order'
                    ], 403);
                }

                // Create delivery record with quotation data
                $this->lalamoveService->createDeliveryRecord($order, $quotation);
                
                // Update order with delivery fee
                $order->update([
                    'lalamove_delivery_fee' => $quotation['delivery_fee']
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'quotation_id' => $quotation['quotation_id'],
                    'delivery_fee' => $quotation['delivery_fee'],
                    'expires_at' => $quotation['expires_at'],
                    'service_type' => $quotation['service_type'],
                    'distance' => $quotation['distance'],
                    'price_breakdown' => $quotation['price_breakdown'],
                    'stops' => $quotation['stops']
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Lalamove Quotation Exception', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error occurred'
            ], 500);
        }
    }

    /**
     * Place Lalamove order
     * 
     * POST /api/lalamove/orders
     */
    public function placeOrder(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|exists:orders,id',
                'quotation_id' => 'required|string',
                'sender_name' => 'required|string|max:255',
                'sender_phone' => 'required|string|max:20',
                'recipient_name' => 'required|string|max:255',
                'recipient_phone' => 'required|string|max:20',
                'recipient_remarks' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $order = Order::findOrFail($data['order_id']);

            // Verify user has access to this order (seller only)
            $user = Auth::user();
            if (!$user->is_seller) {
                return response()->json([
                    'success' => false,
                    'error' => 'Only sellers can place Lalamove orders'
                ], 403);
            }

            // Verify this is the seller's order
            $firstItem = $order->items()->first();
            if (!$firstItem || $firstItem->product->seller_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to order'
                ], 403);
            }

            // Get delivery record
            $delivery = LalamoveDelivery::where('order_id', $order->id)->first();
            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'error' => 'No delivery record found for this order'
                ], 404);
            }

            // Verify quotation ID matches
            if ($delivery->quotation_id !== $data['quotation_id']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid quotation ID'
                ], 400);
            }

            Log::info('Lalamove Place Order Request', [
                'user_id' => $user->id,
                'order_id' => $order->id,
                'quotation_id' => $data['quotation_id']
            ]);

            // Prepare sender and recipient details
            $senderDetails = [
                'stopId' => 'sender',
                'name' => $data['sender_name'],
                'phone' => $data['sender_phone']
            ];

            $recipientDetails = [
                'stopId' => 'recipient',
                'name' => $data['recipient_name'],
                'phone' => $data['recipient_phone'],
                'remarks' => $data['recipient_remarks'] ?? ''
            ];

            // Place order with Lalamove
            $orderResult = $this->lalamoveService->placeOrder(
                $data['quotation_id'],
                $senderDetails,
                $recipientDetails
            );

            if (!$orderResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to place order with Lalamove',
                    'details' => $orderResult['error']
                ], 400);
            }

            // Update delivery record with order data
            $delivery->update([
                'lalamove_order_id' => $orderResult['lalamove_order_id'],
                'status' => $this->lalamoveService->mapLalamoveStatus($orderResult['status']),
                'share_link' => $orderResult['share_link'],
                'price_breakdown' => $orderResult['price_breakdown'],
                'distance' => $orderResult['distance']
            ]);

            // Update order with Lalamove order ID
            $order->update([
                'lalamove_order_id' => $orderResult['lalamove_order_id'],
                'use_third_party_delivery' => true
            ]);

            // If driver is assigned, get driver details
            if ($orderResult['driver_id']) {
                $driverResult = $this->lalamoveService->getDriverDetails(
                    $orderResult['lalamove_order_id'],
                    $orderResult['driver_id']
                );

                if ($driverResult['success']) {
                    $this->lalamoveService->updateDeliveryWithDriver($delivery, $driverResult);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'lalamove_order_id' => $orderResult['lalamove_order_id'],
                    'status' => $delivery->status,
                    'share_link' => $orderResult['share_link'],
                    'driver_id' => $orderResult['driver_id'],
                    'delivery_fee' => $delivery->delivery_fee
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Lalamove Place Order Exception', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error occurred'
            ], 500);
        }
    }

    /**
     * Get Lalamove order status
     * 
     * GET /api/lalamove/orders/{lalamoveOrderId}
     */
    public function getOrderStatus(Request $request, string $lalamoveOrderId)
    {
        try {
            // Find delivery record
            $delivery = LalamoveDelivery::where('lalamove_order_id', $lalamoveOrderId)->first();
            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'error' => 'Delivery record not found'
                ], 404);
            }

            // Verify user has access to this order
            $user = Auth::user();
            $order = $delivery->order;
            
            if ($order->user_id !== $user->id && 
                (!$user->is_seller || $order->items()->first()?->product->seller_id !== $user->id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to order'
                ], 403);
            }

            Log::info('Lalamove Get Order Status Request', [
                'user_id' => $user->id,
                'lalamove_order_id' => $lalamoveOrderId
            ]);

            // Get latest status from Lalamove
            $statusResult = $this->lalamoveService->getOrderDetails($lalamoveOrderId);

            if (!$statusResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to get order status from Lalamove',
                    'details' => $statusResult['error']
                ], 400);
            }

            // Update delivery record with latest status
            $delivery->update([
                'status' => $this->lalamoveService->mapLalamoveStatus($statusResult['status']),
                'share_link' => $statusResult['share_link'] ?? $delivery->share_link,
                'price_breakdown' => $statusResult['price_breakdown'] ?? $delivery->price_breakdown,
                'distance' => $statusResult['distance'] ?? $delivery->distance
            ]);

            // Get driver details if driver is assigned
            $driverDetails = null;
            if ($delivery->driver_id) {
                $driverResult = $this->lalamoveService->getDriverDetails(
                    $lalamoveOrderId,
                    $delivery->driver_id
                );

                if ($driverResult['success']) {
                    $driverDetails = [
                        'driver_id' => $driverResult['driver_id'],
                        'name' => $driverResult['name'],
                        'phone' => $driverResult['phone'],
                        'plate_number' => $driverResult['plate_number'],
                        'photo' => $driverResult['photo'],
                        'coordinates' => $driverResult['coordinates']
                    ];

                    // Update delivery record with latest driver info
                    $this->lalamoveService->updateDeliveryWithDriver($delivery, $driverResult);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'lalamove_order_id' => $lalamoveOrderId,
                    'status' => $delivery->status,
                    'status_display' => $delivery->status_display,
                    'share_link' => $delivery->share_link,
                    'delivery_fee' => $delivery->delivery_fee,
                    'service_type' => $delivery->service_type,
                    'pickup_address' => $delivery->pickup_address,
                    'dropoff_address' => $delivery->dropoff_address,
                    'driver_details' => $driverDetails,
                    'price_breakdown' => $delivery->price_breakdown,
                    'distance' => $delivery->distance,
                    'created_at' => $delivery->created_at,
                    'updated_at' => $delivery->updated_at
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Lalamove Get Order Status Exception', [
                'user_id' => Auth::id(),
                'lalamove_order_id' => $lalamoveOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error occurred'
            ], 500);
        }
    }

    /**
     * Cancel Lalamove order
     * 
     * DELETE /api/lalamove/orders/{lalamoveOrderId}
     */
    public function cancelOrder(Request $request, string $lalamoveOrderId)
    {
        try {
            // Find delivery record
            $delivery = LalamoveDelivery::where('lalamove_order_id', $lalamoveOrderId)->first();
            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'error' => 'Delivery record not found'
                ], 404);
            }

            // Verify user has access to this order (seller only)
            $user = Auth::user();
            if (!$user->is_seller) {
                return response()->json([
                    'success' => false,
                    'error' => 'Only sellers can cancel Lalamove orders'
                ], 403);
            }

            $order = $delivery->order;
            $firstItem = $order->items()->first();
            if (!$firstItem || $firstItem->product->seller_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to order'
                ], 403);
            }

            // Check if order can be cancelled
            if (in_array($delivery->status, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Order cannot be cancelled in current status'
                ], 400);
            }

            Log::info('Lalamove Cancel Order Request', [
                'user_id' => $user->id,
                'lalamove_order_id' => $lalamoveOrderId
            ]);

            // Cancel order with Lalamove
            $cancelResult = $this->lalamoveService->cancelOrder($lalamoveOrderId);

            if (!$cancelResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to cancel order with Lalamove',
                    'details' => $cancelResult['error']
                ], 400);
            }

            // Update delivery record
            $delivery->update([
                'status' => 'cancelled'
            ]);

            // Update order
            $order->update([
                'use_third_party_delivery' => false,
                'lalamove_order_id' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully'
            ]);

        } catch (Exception $e) {
            Log::error('Lalamove Cancel Order Exception', [
                'user_id' => Auth::id(),
                'lalamove_order_id' => $lalamoveOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error occurred'
            ], 500);
        }
    }

    /**
     * Add priority fee (tip) to Lalamove order
     * 
     * POST /api/lalamove/orders/{lalamoveOrderId}/priority-fee
     */
    public function addPriorityFee(Request $request, string $lalamoveOrderId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Validation failed',
                    'details' => $validator->errors()
                ], 422);
            }

            $amount = $validator->validated()['amount'];

            // Find delivery record
            $delivery = LalamoveDelivery::where('lalamove_order_id', $lalamoveOrderId)->first();
            if (!$delivery) {
                return response()->json([
                    'success' => false,
                    'error' => 'Delivery record not found'
                ], 404);
            }

            // Verify user has access to this order
            $user = Auth::user();
            $order = $delivery->order;
            
            if ($order->user_id !== $user->id && 
                (!$user->is_seller || $order->items()->first()?->product->seller_id !== $user->id)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access to order'
                ], 403);
            }

            Log::info('Lalamove Add Priority Fee Request', [
                'user_id' => $user->id,
                'lalamove_order_id' => $lalamoveOrderId,
                'amount' => $amount
            ]);

            // Add priority fee with Lalamove
            $feeResult = $this->lalamoveService->addPriorityFee($lalamoveOrderId, $amount);

            if (!$feeResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to add priority fee',
                    'details' => $feeResult['error']
                ], 400);
            }

            // Update delivery record with new price breakdown
            if ($feeResult['price_breakdown']) {
                $delivery->update([
                    'price_breakdown' => $feeResult['price_breakdown']
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Priority fee added successfully',
                'data' => [
                    'lalamove_order_id' => $lalamoveOrderId,
                    'priority_fee' => $amount,
                    'price_breakdown' => $feeResult['price_breakdown']
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Lalamove Add Priority Fee Exception', [
                'user_id' => Auth::id(),
                'lalamove_order_id' => $lalamoveOrderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error occurred'
            ], 500);
        }
    }

    /**
     * Handle Lalamove webhooks
     * 
     * PATCH /api/lalamove/webhook
     */
    public function handleWebhook(Request $request)
    {
        try {
            // Get the raw request body
            $payload = $request->getContent();
            $signature = $request->header('X-Request-Signature');

            if (!$signature) {
                Log::warning('Lalamove Webhook: Missing signature');
                return response()->json(['error' => 'Missing signature'], 400);
            }

            // Validate webhook signature
            if (!$this->lalamoveService->validateWebhookSignature($signature, $payload)) {
                Log::warning('Lalamove Webhook: Invalid signature', [
                    'signature' => $signature,
                    'payload_length' => strlen($payload)
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $data = json_decode($payload, true);
            if (!$data) {
                Log::warning('Lalamove Webhook: Invalid JSON payload');
                return response()->json(['error' => 'Invalid JSON payload'], 400);
            }

            Log::info('Lalamove Webhook Received', [
                'event_type' => $data['eventType'] ?? 'unknown',
                'order_id' => $data['orderId'] ?? null,
                'data' => $data
            ]);

            $lalamoveOrderId = $data['orderId'] ?? null;
            if (!$lalamoveOrderId) {
                Log::warning('Lalamove Webhook: Missing order ID');
                return response()->json(['error' => 'Missing order ID'], 400);
            }

            // Find delivery record
            $delivery = LalamoveDelivery::where('lalamove_order_id', $lalamoveOrderId)->first();
            if (!$delivery) {
                Log::warning('Lalamove Webhook: Delivery record not found', [
                    'lalamove_order_id' => $lalamoveOrderId
                ]);
                return response()->json(['error' => 'Delivery record not found'], 404);
            }

            $order = $delivery->order;

            // Handle different webhook event types
            switch ($data['eventType'] ?? '') {
                case 'ORDER_STATUS_CHANGED':
                    $this->handleOrderStatusChanged($delivery, $data);
                    break;

                case 'DRIVER_ASSIGNED':
                    $this->handleDriverAssigned($delivery, $data);
                    break;

                case 'ORDER_AMOUNT_CHANGED':
                    $this->handleOrderAmountChanged($delivery, $data);
                    break;

                default:
                    Log::info('Lalamove Webhook: Unknown event type', [
                        'event_type' => $data['eventType'] ?? 'unknown',
                        'lalamove_order_id' => $lalamoveOrderId
                    ]);
            }

            return response()->json(['success' => true]);

        } catch (Exception $e) {
            Log::error('Lalamove Webhook Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->getContent()
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle ORDER_STATUS_CHANGED webhook
     */
    private function handleOrderStatusChanged(LalamoveDelivery $delivery, array $data)
    {
        $newStatus = $data['data']['status'] ?? null;
        if (!$newStatus) {
            return;
        }

        $mappedStatus = $this->lalamoveService->mapLalamoveStatus($newStatus);
        
        Log::info('Lalamove Order Status Changed', [
            'lalamove_order_id' => $delivery->lalamove_order_id,
            'old_status' => $delivery->status,
            'new_status' => $mappedStatus,
            'lalamove_status' => $newStatus
        ]);

        $delivery->update(['status' => $mappedStatus]);

        // If order is completed, update order status
        if ($mappedStatus === 'completed') {
            $order = $delivery->order;
            if ($order->status === 'confirmed') {
                $order->update(['status' => 'delivered']);
            }
        }
    }

    /**
     * Handle DRIVER_ASSIGNED webhook
     */
    private function handleDriverAssigned(LalamoveDelivery $delivery, array $data)
    {
        $driverData = $data['data'] ?? [];
        $driverId = $driverData['driverId'] ?? null;

        if (!$driverId) {
            return;
        }

        Log::info('Lalamove Driver Assigned', [
            'lalamove_order_id' => $delivery->lalamove_order_id,
            'driver_id' => $driverId
        ]);

        // Get driver details from Lalamove
        $driverResult = $this->lalamoveService->getDriverDetails(
            $delivery->lalamove_order_id,
            $driverId
        );

        if ($driverResult['success']) {
            $this->lalamoveService->updateDeliveryWithDriver($delivery, $driverResult);
        }

        // Update status to assigned
        $delivery->update(['status' => 'assigned']);
    }

    /**
     * Handle ORDER_AMOUNT_CHANGED webhook
     */
    private function handleOrderAmountChanged(LalamoveDelivery $delivery, array $data)
    {
        $priceBreakdown = $data['data']['priceBreakdown'] ?? null;
        if (!$priceBreakdown) {
            return;
        }

        Log::info('Lalamove Order Amount Changed', [
            'lalamove_order_id' => $delivery->lalamove_order_id,
            'new_price_breakdown' => $priceBreakdown
        ]);

        $delivery->update(['price_breakdown' => $priceBreakdown]);
    }

    /**
     * Get available service types for the market
     * 
     * GET /api/lalamove/service-types
     */
    public function getServiceTypes()
    {
        try {
            Log::info('Lalamove Get Service Types Request', [
                'user_id' => Auth::id()
            ]);

            $cityInfo = $this->lalamoveService->getCityInfo();

            if (!$cityInfo['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to get service types from Lalamove',
                    'details' => $cityInfo['error']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $cityInfo['cities']
            ]);

        } catch (Exception $e) {
            Log::error('Lalamove Get Service Types Exception', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error occurred'
            ], 500);
        }
    }
}
