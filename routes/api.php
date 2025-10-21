<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\CropScheduleController;
use App\Http\Controllers\PreorderController;
use App\Http\Controllers\AdminProductController;
use App\Http\Controllers\ProductController as MainProductController; // ✅ alias to avoid confusion
use App\Http\Controllers\ChatController;
use App\Http\Controllers\LalamoveController;
use App\Http\Controllers\UnitConversionController;

// NEW: harvest controllers
use App\Http\Controllers\Seller\HarvestController as SellerHarvestController;
use App\Http\Controllers\Admin\HarvestController as AdminHarvestController;
use App\Http\Controllers\Admin\ProductVerificationController as AdminProductVerificationController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\GmailApiVerificationController;
use App\Http\Controllers\SmartEmailVerificationController;
use App\Http\Controllers\SocialLoginController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

// Admin Auth
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// 🚨 TEMPORARY: Setup admin account - DELETE AFTER USE!
Route::get('/setup-admin-once', function () {
    try {
        if (\App\Models\Admin::where('email', 'superadminonlyfarms@gmail.com')->exists()) {
            return response()->json(['message' => 'Admin already exists!'], 400);
        }
        
        \App\Models\Admin::create([
            'name' => 'Super Admin',
            'email' => 'superadminonlyfarms@gmail.com',
            'password' => \Illuminate\Support\Facades\Hash::make('SuperAdmin1'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '✅ Admin created successfully!',
            'credentials' => [
                'email' => 'superadminonlyfarms@gmail.com',
                'password' => 'SuperAdmin1'
            ],
            'warning' => '🚨 DELETE THE /setup-admin-once ROUTE FROM routes/api.php NOW!'
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Auth with rate limiting
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Phone verification routes (public)
Route::post('/send-phone-verification-code', [AuthController::class, 'sendPhoneVerificationCode'])->middleware('throttle:3,1');
Route::post('/resend-phone-verification-code', [AuthController::class, 'resendPhoneVerificationCode'])->middleware('throttle:3,1');
Route::post('/verify-phone', [AuthController::class, 'verifyPhone'])->middleware('throttle:5,1');

// Email verification routes (public)
Route::post('/send-email-verification-code', [AuthController::class, 'sendEmailVerificationCode'])->middleware('throttle:3,1');
Route::post('/resend-email-verification-code', [AuthController::class, 'resendEmailVerificationCode'])->middleware('throttle:3,1');
Route::post('/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:5,1');

// Facebook login routes
Route::get('/auth/facebook', [AuthController::class, 'redirectToFacebook']);
Route::get('/auth/facebook/callback', [AuthController::class, 'handleFacebookCallback']);
Route::post('/auth/facebook/callback', [AuthController::class, 'handleFacebookCallback']);
Route::get('/auth/facebook/url', [AuthController::class, 'getFacebookLoginUrl']);
Route::post('/auth/facebook/check-user', [AuthController::class, 'checkFacebookUser']); // Check if Facebook user exists
Route::post('/auth/facebook/signup', [AuthController::class, 'facebookSignup']); // Facebook signup

// Google login routes
Route::get('/auth/google/url', [AuthController::class, 'getGoogleLoginUrl']);
Route::post('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('/auth/google/signup', [AuthController::class, 'googleSignup']); // Google signup

// SMS test route (for development)
Route::post('/test-sms', function(Request $request) {
    $request->validate([
        'phone_number' => 'required|string',
    ]);

    $smsService = new \App\Services\SmsService();
    $result = $smsService->testSms($request->phone_number);

    return response()->json($result);
})->middleware('throttle:1,1');

// Firebase SMS test route (for development)
Route::post('/test-firebase-sms', function(Request $request) {
    $request->validate([
        'phone_number' => 'required|string',
    ]);

    $smsService = new \App\Services\SmsService();
    $result = $smsService->testSms($request->phone_number);

    return response()->json($result);
})->middleware('throttle:1,1');

// Products & Sellers (public browsing)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/products/{id}/preorder-eligibility', [ProductController::class, 'checkPreorderEligibility']);
Route::get('/products/{id}/preorder-eligibility', [PreorderController::class, 'checkEligibility']);
Route::get('/sellers', [SellerController::class, 'index']);
Route::get('/sellers/{id}', [SellerController::class, 'show']);

// Reviews
Route::get('/products/{productId}/reviews', [ReviewController::class, 'index']);

// Unit Conversions (public)
Route::get('/unit-conversions/{vegetableSlug}', [UnitConversionController::class, 'getAvailableUnits']);
Route::get('/unit-conversions', [UnitConversionController::class, 'index']);

// PayMongo Webhook (public, no auth)
Route::post('/webhook/paymongo', [OrderController::class, 'handleWebhook'])
    ->withoutMiddleware(['auth:sanctum']);

// Lalamove Webhook (public, no auth - validated by signature)
Route::patch('/lalamove/webhook', [LalamoveController::class, 'handleWebhook'])
    ->withoutMiddleware(['auth:sanctum']);

// Simulated payment results (for testing)
Route::get('/payments/success/{id}', fn($id) => "Payment success for order $id");
Route::get('/payments/cancel/{id}', fn($id) => "Payment cancelled for order $id");

// Multi-unit order creation and seller verification routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Create order with multi-unit support
    Route::post('/orders', [OrderController::class, 'store']);
    
    // Seller verification routes
    Route::get('/seller/{sellerId}/orders/pending', [OrderController::class, 'getPendingOrders']);
    Route::post('/orders/{orderId}/seller/verify', [OrderController::class, 'sellerVerify']);
    
    // Order cancellation
    Route::post('/orders/{orderId}/cancel', [OrderController::class, 'cancelOrder']);
    
    // Seller confirmation (decreases stock)
    Route::post('/orders/{orderId}/seller/confirm', [OrderController::class, 'sellerConfirmOrder']);
    
    // Seller delivery method confirmation (self-delivery vs Lalamove)
    Route::post('/orders/{orderId}/confirm-delivery-method', [OrderController::class, 'confirmDeliveryMethod']);
    
    // Update order item weight
    Route::patch('/orders/{orderId}/items/{itemId}', [OrderController::class, 'updateOrderItem']);
    
    // Buyer confirmation
    Route::post('/orders/{orderId}/buyer/confirm', [OrderController::class, 'buyerConfirm']);
});


/*
|--------------------------------------------------------------------------
| Admin-Protected Routes (require Sanctum + admin middleware)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:admin', 'admin'])->prefix('admin')->group(function () {
    // Admin CRUD for users
    Route::put('/users/{userId}/products/{productId}', [AdminUserController::class, 'updateProduct']);
    Route::get('/users/{id}/products', [AdminUserController::class, 'products']);
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
    Route::get('/users/{id}/orders', [AdminUserController::class, 'userOrders']); // ✅ NEW

    // ✅ Admin CRUD for products
    // Note: Route group already prefixed with /admin
    Route::get('/products/{id}', [AdminProductController::class, 'show']);
    Route::post('/products/{id}', [AdminProductController::class, 'update']); // with _method=PUT
    Route::put('/products/{id}', [AdminProductController::class, 'update']);
    Route::delete('/products/{id}', [AdminProductController::class, 'destroy']);

    // ✅ New: Fetch all products of a specific seller (for admin-user-products)
    Route::get('/users/{sellerId}/products-list', [MainProductController::class, 'getUserProducts']);

    // ✅ Admin Harvest management
    Route::get('/harvests', [AdminHarvestController::class, 'index']);
    Route::get('/harvests/{harvest}', [AdminHarvestController::class, 'show']);
    Route::post('/crop-schedules/{cropSchedule}/harvest', [AdminHarvestController::class, 'storeForSchedule']);
    Route::put('/harvests/{harvest}', [AdminHarvestController::class, 'update']);
    Route::delete('/harvests/{harvest}', [AdminHarvestController::class, 'destroy']);
    Route::post('/harvests/{harvest}/verify', [AdminHarvestController::class, 'verify']);
    Route::post('/harvests/{harvest}/publish', [AdminHarvestController::class, 'publish']);

    // ✅ Admin Product Verification management
    Route::get('/product-verifications', [AdminProductVerificationController::class, 'index']);
    Route::get('/product-verifications/{product}', [AdminProductVerificationController::class, 'show']);
    Route::post('/product-verifications/{product}/approve', [AdminProductVerificationController::class, 'approve']);
    Route::post('/product-verifications/{product}/reject', [AdminProductVerificationController::class, 'reject']);
    Route::get('/product-verifications-stats', [AdminProductVerificationController::class, 'stats']);
});


/*
|--------------------------------------------------------------------------
| Protected Routes (require Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'throttle:100,1'])->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/user/profile', [AuthController::class, 'updateProfile']);

    // Seller account
    Route::post('/seller/become', [SellerController::class, 'becomeSeller']);
    Route::get('/seller/profile', [SellerController::class, 'profile']);

    // Seller product management
    Route::get('/seller/products', [ProductController::class, 'myProducts']);
    Route::post('/seller/products', [ProductController::class, 'store']);
    Route::put('/seller/products/{id}', [ProductController::class, 'update']);
    Route::delete('/seller/products/{id}', [ProductController::class, 'destroy']);

    // Orders (buyer)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/{order}/buyer/confirm', [OrderController::class, 'buyerConfirm']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancelOrder']);
    Route::patch('/orders/{order}/items/{item}', [OrderController::class, 'updateItem']);

    // Orders (seller)
    Route::get('/seller/orders', [SellerOrderController::class, 'index']);
    Route::get('/seller/orders/{order}', [SellerOrderController::class, 'show']);
    Route::patch('/seller/orders/{order}/status', [SellerOrderController::class, 'updateStatus']);
    Route::post('/orders/{order}/seller/confirm', [SellerOrderController::class, 'sellerConfirm']);
    Route::post('/orders/{order}/seller/verify', [SellerOrderController::class, 'verifyOrder']);
    Route::get('/seller/{seller}/orders/pending', [SellerOrderController::class, 'pendingOrders']);

    // Addresses
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

    // Order Payment
    Route::post('/orders/{id}/pay', [OrderController::class, 'generatePaymentLink']);
    Route::post('/orders/{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);
    Route::post('/orders/{id}/cod-delivered', [OrderController::class, 'markCODDelivered']);
    Route::post('/orders/{id}/payment-failure', [OrderController::class, 'handlePaymentFailure']);

    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/top-purchased', [DashboardController::class, 'topPurchased']);

    // Analytics
    Route::get('/analytics/monthly-sales', [AnalyticsController::class, 'monthlySales']);
    Route::get('/analytics/top-products', [AnalyticsController::class, 'topProducts']);
    Route::get('/analytics/seasonal-trends', [AnalyticsController::class, 'seasonalTrends']);
    
    // 🆕 NEW: Enhanced Analytics Endpoints
    Route::get('/analytics/daily-sales', [AnalyticsController::class, 'dailySales']);
    Route::get('/analytics/weekly-sales', [AnalyticsController::class, 'weeklySales']);
    Route::get('/analytics/monthly-sales-detailed', [AnalyticsController::class, 'monthlySalesDetailed']);
    Route::get('/analytics/top-seller', [AnalyticsController::class, 'topSeller']);
    Route::get('/analytics/top-rated-product', [AnalyticsController::class, 'topRatedProduct']);

    // Reviews
    Route::post('/products/{productId}/order-items/{orderItemId}/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    Route::get('/orders/{order}/reviewable-items', [ReviewController::class, 'reviewableItems']);

    // Crop Schedules (seller/admin blended via controller logic)
    Route::apiResource('crop-schedules', CropScheduleController::class);

    // ✅ Preorders (protected)
    Route::get('/preorders', [PreorderController::class, 'index']);
    Route::post('/preorders', [PreorderController::class, 'store']);
    Route::get('/preorders/{preorder}', [PreorderController::class, 'show']);
    Route::put('/preorders/{preorder}', [PreorderController::class, 'update']);
    Route::post('/preorders/{preorder}/cancel', [PreorderController::class, 'cancel']);
    Route::get('/preorders/consumer', [PreorderController::class, 'consumerPreorders']);
    Route::get('/preorders/seller', [PreorderController::class, 'sellerPreorders']);
    Route::get('/preorders/consumer', [PreorderController::class, 'consumerPreorders']);
    Route::get('/preorders/seller', [PreorderController::class, 'sellerPreorders']);
    Route::get('/preorders/{id}', [PreorderController::class, 'show']);
    Route::put('/preorders/{id}', [PreorderController::class, 'update']);
    Route::post('/preorders/{id}/accept', [PreorderController::class, 'accept']);
    Route::post('/preorders/{id}/fulfill', [PreorderController::class, 'fulfill']);
    Route::post('/preorders/{id}/cancel', [PreorderController::class, 'cancel']);
    Route::get('/products/{id}/stock-info', [PreorderController::class, 'getStockInfo']);

    //Messaging (Chat)
    Route::post('/conversations', [ChatController::class, 'createConversation']);
    Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
    Route::get('/conversations', [ChatController::class, 'listConversations']);
    Route::get('/conversations/{id}/messages', [ChatController::class, 'listMessages']);
    Route::get('/conversations/{id}/listen', [ChatController::class, 'listen']);
    Route::post('/conversations/{id}/mark-read', [ChatController::class, 'markAsRead']);

    // ✅ Seller Harvest endpoints
    Route::post('/crop-schedules/{cropSchedule}/harvest', [SellerHarvestController::class, 'storeForSchedule']);
    Route::get('/harvests', [SellerHarvestController::class, 'index']);
    Route::get('/harvests/{harvest}', [SellerHarvestController::class, 'show']);
    Route::put('/harvests/{harvest}', [SellerHarvestController::class, 'update']);
    Route::delete('/harvests/{harvest}', [SellerHarvestController::class, 'destroy']);
    Route::post('/harvests/{harvest}/publish', [SellerHarvestController::class, 'publish']); // requires verification

    // ✅ Lalamove Delivery endpoints
    Route::post('/lalamove/quotation', [LalamoveController::class, 'getQuotation']);
    Route::post('/lalamove/orders', [LalamoveController::class, 'placeOrder']);
    Route::get('/lalamove/orders/{lalamoveOrderId}', [LalamoveController::class, 'getOrderStatus']);
    Route::delete('/lalamove/orders/{lalamoveOrderId}', [LalamoveController::class, 'cancelOrder']);
    Route::post('/lalamove/orders/{lalamoveOrderId}/priority-fee', [LalamoveController::class, 'addPriorityFee']);
    Route::get('/lalamove/service-types', [LalamoveController::class, 'getServiceTypes']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes (require admin middleware)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:admin', 'admin'])->group(function () {
    // Admin Harvest Management
    Route::get('/admin/harvests', [AdminHarvestController::class, 'index']);
    Route::get('/admin/harvests/{harvest}', [AdminHarvestController::class, 'show']);
    Route::post('/admin/harvests/{harvest}/verify', [AdminHarvestController::class, 'verify']);
    Route::post('/admin/harvests/{harvest}/publish', [AdminHarvestController::class, 'publish']);
    
    // Admin Product Management
    Route::get('/admin/products/{product}', [AdminProductController::class, 'show']);
    Route::put('/admin/products/{product}', [AdminProductController::class, 'update']);
    
    // Admin Product Verification
    Route::get('/admin/product-verifications', [AdminProductVerificationController::class, 'index']);
    Route::get('/admin/product-verifications/{product}', [AdminProductVerificationController::class, 'show']);
    Route::post('/admin/product-verifications/{product}/approve', [AdminProductVerificationController::class, 'approve']);
    Route::post('/admin/product-verifications/{product}/reject', [AdminProductVerificationController::class, 'reject']);
    Route::get('/admin/product-verifications-stats', [AdminProductVerificationController::class, 'stats']);
    
    // Admin User Management
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::get('/admin/users/{user}', [AdminUserController::class, 'show']);
    Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
    Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
});


