<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\{CategoryController, PostController, TagController};

Route::middleware(['auth', 'verified'])->prefix('dashboard')
    ->name('dashboard.')
    ->group(function () {
        // Posts routes
        Route::resource('posts', PostController::class);

        //  Categories routes
        Route::resource('categories', CategoryController::class);

        // Tags routes - specific routes before resource routes
        Route::get('tags/popular', [TagController::class, 'popular'])->name('tags.popular');
        Route::resource('tags', TagController::class);
    });
