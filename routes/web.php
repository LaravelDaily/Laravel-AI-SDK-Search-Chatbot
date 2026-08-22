<?php

use App\Http\Controllers\AiSearchLogController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('search', [SearchController::class, 'index'])->name('search.index');
    Route::get('search/results', [SearchController::class, 'show'])->name('search.results');
    Route::get('admin/ai-logs', [AiSearchLogController::class, 'index'])->name('admin.ai-logs.index');
});

require __DIR__.'/settings.php';
