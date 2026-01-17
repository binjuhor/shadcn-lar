<?php

use Illuminate\Support\Facades\Route;
use Modules\Invoice\Http\Controllers\InvoiceController;
use Modules\Invoice\Http\Controllers\InvoiceReportController;

Route::middleware(['auth', 'verified'])->prefix('dashboard')
    ->name('dashboard.')
    ->group(function () {
        Route::resource('invoices', InvoiceController::class);
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
        Route::get('invoices-reports', [InvoiceReportController::class, 'index'])->name('invoices.reports');
    });
