<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerController;

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
});
