<?php

use App\Http\Controllers\AiChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectBlogController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::post('products', [ProductController::class, 'store'])->name('products.store');
    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');

    Route::get('ai-chat', [AiChatController::class, 'index'])->name('ai-chat.index');
    Route::post('ai-chat', [AiChatController::class, 'store'])->middleware('throttle:10,1')->name('ai-chat.store');
    Route::get('project-blog', ProjectBlogController::class)->name('project-blog');
});

require __DIR__.'/settings.php';
