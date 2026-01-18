<?php

use Illuminate\Support\Facades\Route;
use Modules\Ecommerce\Http\Controllers\{
    OrderController,
    ProductCategoryController,
    ProductController,
    ProductTagController
};

Route::middleware(['auth:sanctum'])->prefix('v1/ecommerce')->group(function () {
    // Products API routes
    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-categories', ProductCategoryController::class);
    Route::apiResource('product-tags', ProductTagController::class);
    Route::apiResource('orders', OrderController::class);

    // Order specific routes
    Route::post('orders/{order}/mark-as-paid', [OrderController::class, 'markAsPaid'])->name('orders.mark-as-paid');
    Route::put('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
});
