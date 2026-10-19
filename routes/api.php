<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DatasetController;
use App\Http\Controllers\Api\ImportController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (JWT Authentication)
Route::middleware('auth.jwt')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    Route::prefix('datasets')->group(function () {
        // Read operations: accessible by all authenticated roles (admin, operator, viewer)
        Route::get('/', [DatasetController::class, 'index']);
        Route::get('/{slug}', [DatasetController::class, 'show']);
        Route::get('/{slug}/items', [DatasetController::class, 'items']);

        // Write operations: accessible by admin and operator roles
        Route::middleware('role:admin,operator')->group(function () {
            Route::post('/{slug}/items', [DatasetController::class, 'storeItem']);
            Route::put('/items/{id}', [DatasetController::class, 'updateItem']);
        });

        // Destructive / System operations: strictly restricted to admin role
        Route::middleware('role:admin')->group(function () {
            Route::delete('/items/{id}', [DatasetController::class, 'deleteItem']);
            Route::post('/import', [ImportController::class, 'import']);
            Route::post('/import-defaults', [ImportController::class, 'importDefaultFiles']);
        });
    });
});
