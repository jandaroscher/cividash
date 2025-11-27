<?php

use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\TileController;
use App\Http\Controllers\Api\Content\PageController as ContentPageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/tiles', [TileController::class, 'index']);
Route::get('/tiles/{tile}', [TileController::class, 'show']);
Route::get('/config/branding', [ConfigController::class, 'branding']);
Route::get('/config/general', [ConfigController::class, 'general']);
Route::get('/config/footer', [ConfigController::class, 'footer']);

// List all pages
Route::get('/content/pages', [ContentPageController::class, 'index']);

// Special route for root/home page
Route::get('/content/pages/root', [ContentPageController::class, 'showRoot']);

// Get page by ID
Route::get('/content/pages/{id}', [ContentPageController::class, 'show'])->where('id', '[0-9]+');
