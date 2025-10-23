<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/test-storage', function () {
    $storagePath = storage_path('app/public');
    $publicPath = public_path('storage');
    
    return response()->json([
        'storage_path' => $storagePath,
        'public_path' => $publicPath,
        'storage_exists' => file_exists($storagePath),
        'public_storage_exists' => file_exists($publicPath),
        'storage_link_exists' => is_link($publicPath),
        'files_in_storage' => file_exists($storagePath) ? array_slice(scandir($storagePath), 2, 10) : [],
        'files_in_public_storage' => file_exists($publicPath) ? array_slice(scandir($publicPath), 2, 10) : [],
    ]);
});

Route::get('/test-image/{filename}', function ($filename) {
    $storagePath = storage_path('app/public/products/' . $filename);
    $publicPath = public_path('storage/products/' . $filename);
    
    return response()->json([
        'filename' => $filename,
        'storage_path' => $storagePath,
        'public_path' => $publicPath,
        'storage_file_exists' => file_exists($storagePath),
        'public_file_exists' => file_exists($publicPath),
        'storage_file_size' => file_exists($storagePath) ? filesize($storagePath) : 0,
        'public_file_size' => file_exists($publicPath) ? filesize($publicPath) : 0,
    ]);
});