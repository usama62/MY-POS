<?php

use App\Http\Controllers\Api\V1\CustomerApiController;
use App\Http\Controllers\Api\V1\DashboardApiController;
use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\ProductApiController;
use App\Http\Controllers\Api\V1\SaleApiController;
use App\Http\Controllers\Api\V1\ZakatApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function (): void {
    Route::get('/health', fn () => response()->json(['status' => 'ok']));
    Route::post('/auth/register', [AuthApiController::class, 'register'])->name('auth.register');
    Route::post('/auth/login', [AuthApiController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthApiController::class, 'me'])->name('auth.me');
        Route::post('/auth/logout', [AuthApiController::class, 'logout'])->name('auth.logout');

        Route::get('/dashboard', DashboardApiController::class);

        Route::middleware('role:admin')->group(function (): void {
            Route::apiResource('products', ProductApiController::class);
            Route::post('/products-import', [ProductApiController::class, 'import']);
            Route::apiResource('customers', CustomerApiController::class);
        });

        Route::middleware('role:admin,cashier')->group(function (): void {
            Route::get('/sales', [SaleApiController::class, 'index']);
            Route::post('/sales', [SaleApiController::class, 'store']);
            Route::get('/sales/{sale}', [SaleApiController::class, 'show']);
            Route::post('/zakat/calculate', [ZakatApiController::class, 'calculate']);
        });
    });

});
