<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DatasetController;
use App\Http\Controllers\Api\ImportController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::prefix('datasets')->group(function () {
        Route::get('/', [DatasetController::class, 'index']);
        Route::get('/{slug}', [DatasetController::class, 'show']);
        Route::get('/{slug}/items', [DatasetController::class, 'items']);
        Route::post('/{slug}/items', [DatasetController::class, 'storeItem']);
        Route::put('/items/{id}', [DatasetController::class, 'updateItem']);
        Route::delete('/items/{id}', [DatasetController::class, 'deleteItem']);

        Route::post('/import', [ImportController::class, 'import']);
        Route::post('/import-defaults', [ImportController::class, 'importDefaultFiles']);
    });
});
