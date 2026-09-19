<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->isAdmin()
        ? redirect()->route('dashboard')
        : redirect()->route('sales.create');
})->name('home');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/language', [LanguageController::class, 'update'])->name('language.update');

    // Cashier + admin: create sale + view receipt
    Route::middleware('role:admin,cashier')->group(function (): void {
        Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])
            ->whereNumber('sale')
            ->name('sales.show');
    });

    // Admin only
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::resource('products', ProductController::class);
        Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');
        Route::post('/products/{product}/batches', [ProductController::class, 'storeBatch'])->name('products.batches.store');
        Route::post('/products/{product}/uoms', [ProductController::class, 'storeUom'])->name('products.uoms.store');

        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/export', [SaleController::class, 'export'])->name('sales.export');

        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::post('/purchase-orders/generate', [PurchaseOrderController::class, 'generate'])->name('purchase-orders.generate');
        Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::post('/purchase-orders/{purchaseOrder}/mark-ordered', [PurchaseOrderController::class, 'markOrdered'])->name('purchase-orders.mark-ordered');
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

        Route::get('/settings/company', [CompanyProfileController::class, 'edit'])->name('settings.company.edit');
        Route::put('/settings/company', [CompanyProfileController::class, 'update'])->name('settings.company.update');
    });
});
