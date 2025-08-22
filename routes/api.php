<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerController;

// 🔓 Public routes (no login needed)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);    // anyone can browse products
Route::get('/products/{id}', [ProductController::class, 'show']); // anyone can see details
Route::get('/sellers', [SellerController::class, 'index']);      // anyone can browse sellers
Route::get('/sellers/{id}', [SellerController::class, 'show']);  // anyone can view a seller

// 🔒 Protected routes (require Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // Products (only logged-in users can manage)
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Sellers (only logged-in users can manage)
    Route::post('/sellers', [SellerController::class, 'store']);
    Route::put('/sellers/{id}', [SellerController::class, 'update']);
    Route::delete('/sellers/{id}', [SellerController::class, 'destroy']);

    // Example: Profile route (get logged-in user)
    Route::get('/profile', function (Request $request) {
        return $request->user();
    });
});
