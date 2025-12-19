<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\PostController;
use Modules\Blog\Http\Controllers\CategoryController;
use Modules\Blog\Http\Controllers\TagController;

Route::middleware(['auth:sanctum'])->prefix('v1/blog')->group(function () {
    // Posts API routes

});
