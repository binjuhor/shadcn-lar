<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('invoice')->group(function () {
    // API routes for Invoice module
});
