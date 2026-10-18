<?php

use App\Http\Controllers\PreviewController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/setup/{token}', [SetupController::class, 'show'])->middleware('throttle:10,1');
Route::post('/admin/setup/{token}', [SetupController::class, 'store'])->middleware('throttle:5,1');
Route::get('/admin/preview/{document}', PreviewController::class)->middleware('auth')->name('content.preview');
Route::get('/', fn () => response()->file(public_path('index.html')));

if (app()->environment('local')) {
    require __DIR__.'/review.php';
}
