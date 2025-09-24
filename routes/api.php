<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\DashboardController;


// 🔓 Public routes (no login needed)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);      // anyone can browse products
Route::get('/products/{id}', [ProductController::class, 'show']); // anyone can see product details
Route::get('/sellers', [SellerController::class, 'index']);       // anyone can browse sellers
Route::get('/sellers/{id}', [SellerController::class, 'show']);   // anyone can view a seller

// 🔒 Protected routes (require Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // Seller account management
    Route::post('/seller/become', [SellerController::class, 'becomeSeller']); // become a seller
    Route::get('/seller/profile', [SellerController::class, 'profile']);      // seller’s own profile

    // Seller product management
    Route::get('/seller/products', [ProductController::class, 'myProducts']); // seller’s own products
    Route::post('/seller/products', [ProductController::class, 'store']);     // add product
    Route::put('/seller/products/{id}', [ProductController::class, 'update']); // update product
    Route::delete('/seller/products/{id}', [ProductController::class, 'destroy']); // delete product

    // User orders
    Route::get('/orders', [OrderController::class, 'index']);          // current user’s orders
    Route::get('/orders/{order}', [OrderController::class, 'show']);   // view specific order
    Route::post('/orders', [OrderController::class, 'store']);         // place new order

    // Seller orders
    Route::get('/seller/orders', [SellerOrderController::class, 'index']);   // list all orders for seller
    Route::get('/seller/orders/{order}', [SellerOrderController::class, 'show']); // ✅ view specific order for seller
    Route::patch('/seller/orders/{order}/status', [SellerOrderController::class, 'updateStatus']); // update order status

    // Addresses
    Route::get('/addresses', [AddressController::class, 'index']);
    Route::post('/addresses', [AddressController::class, 'store']);
    Route::put('/addresses/{id}', [AddressController::class, 'update']);
    Route::delete('/addresses/{id}', [AddressController::class, 'destroy']);

    //Order Payment
    Route::post('/orders/{id}/pay', [OrderController::class, 'generatePaymentLink']);
    Route::post('/orders/{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/top-purchased', [DashboardController::class, 'topPurchased']);

});
