<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellerController;

// Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// Public route for viewing products
Route::get('/products', [ProductController::class, 'index']);
// Protected route for adding products (only sellers)
//Route::middleware('auth:sanctum')->group(function () {
//    Route::post('/products', [ProductController::class, 'store']);
//});
Route::get('/products', [ProductController::class, 'index']);   // List all
Route::get('/products/{id}', [ProductController::class, 'show']); // Get one
Route::post('/products', [ProductController::class, 'store']); // Create
Route::put('/products/{id}', [ProductController::class, 'update']); // Update
Route::delete('/products/{id}', [ProductController::class, 'destroy']); // Delete



// Seller routes
Route::get('/sellers', [SellerController::class, 'index']);
Route::get('/sellers/{id}', [SellerController::class, 'show']);
// These require authentication
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/sellers', [SellerController::class, 'store']);
    Route::put('/sellers/{id}', [SellerController::class, 'update']);
    Route::delete('/sellers/{id}', [SellerController::class, 'destroy']);
});
