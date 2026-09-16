<?php

use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ZakatController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', DashboardController::class)->name('dashboard');

Route::resource('products', ProductController::class);
Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');

Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
Route::get('/sales/export', [SaleController::class, 'export'])->name('sales.export');
Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');

Route::get('/zakat', [ZakatController::class, 'index'])->name('zakat.index');
Route::post('/zakat', [ZakatController::class, 'calculate'])->name('zakat.calculate');

Route::post('/language', [LanguageController::class, 'update'])->name('language.update');
Route::get('/settings/company', [CompanyProfileController::class, 'edit'])->name('settings.company.edit');
Route::put('/settings/company', [CompanyProfileController::class, 'update'])->name('settings.company.update');
