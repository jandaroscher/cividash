<?php

use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\TileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/tiles', [TileController::class, 'index']);
Route::get('/tiles/{tile}', [TileController::class, 'show']);
Route::get('/config/branding', [ConfigController::class, 'branding']);
Route::get('/config/general', [ConfigController::class, 'general']);
