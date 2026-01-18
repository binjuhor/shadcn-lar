<?php

use Illuminate\Support\Facades\Route;
use Modules\Ecommerce\Http\Controllers\{
    OrderController,
    ProductCategoryController,
    ProductController,
    ProductTagController
};

Route::middleware(['auth', 'verified'])->prefix('dashboard/ecommerce')
    ->name('dashboard.ecommerce.')
    ->group(function () {
        // Product routes
        Route::resource('products', ProductController::class);

        // Product Categories routes
        Route::resource('product-categories', ProductCategoryController::class);

        // Product Tags routes
        Route::resource('product-tags', ProductTagController::class);

        // Orders routes
        Route::resource('orders', OrderController::class);
        Route::post('orders/{order}/mark-as-paid', [OrderController::class, 'markAsPaid'])->name('orders.mark-as-paid');
        Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
    });
