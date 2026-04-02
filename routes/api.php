<?php

use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminCategoryGroupController;
use App\Http\Controllers\Api\Admin\AdminMetricDefinitionController;
use App\Http\Controllers\Api\Admin\AdminMetricValueController;
use App\Http\Controllers\Api\Admin\AdminPageController;
use App\Http\Controllers\Api\Admin\AdminTileController;
use App\Http\Controllers\Api\Admin\AdminTileYearController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\Content\PageController as ContentPageController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Tenant\TenantUserController;
use App\Http\Controllers\Api\TileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'throttle:60,1']);

// Authenticated user profile endpoints
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('me')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::patch('/', [ProfileController::class, 'update']);
    Route::put('/password', [ProfileController::class, 'updatePassword']);
});

// Public API routes with tenant resolution (Token > Domain > Default)
Route::middleware(['resolve.tenant', 'throttle:60,1'])->group(function () {
    Route::get('/tiles', [TileController::class, 'index']);
    Route::get('/tiles/{slug}', [TileController::class, 'show']);
    Route::get('/filters', [FilterController::class, 'index']);
    Route::get('/categories/{groupKey}', [CategoryController::class, 'showByGroup']);
    Route::get('/config/branding', [ConfigController::class, 'branding']);
    Route::get('/config/general', [ConfigController::class, 'general']);
    Route::get('/config/header', [ConfigController::class, 'header']);
    Route::get('/config/footer', [ConfigController::class, 'footer']);
    Route::get('/config/tenant', [ConfigController::class, 'tenant']);
    Route::get('/config/dashboard', [ConfigController::class, 'dashboard']);
    Route::get('/config/content', [ConfigController::class, 'content']);

    // Content pages
    Route::get('/content/pages', [ContentPageController::class, 'index']);
    Route::get('/content/pages/root', [ContentPageController::class, 'showRoot']);
    Route::get('/content/pages/{id}', [ContentPageController::class, 'show'])->where('id', '[0-9]+');
});

// Admin API routes (secured with Sanctum + admin permission check + tenant resolution)
// Middleware order:
// 1. auth:sanctum - 401 if not authenticated
// 2. admin.api - 403 if user/token lacks permission
// 3. resolve.tenant - resolve tenant from token/domain
// 4. admin.tenant - 400 if no explicit tenant (no default fallback)
Route::middleware(['auth:sanctum', 'admin.api', 'resolve.tenant', 'admin.tenant', 'throttle:120,1'])->prefix('admin')->group(function () {
    // Config routes
    Route::post('/config/branding', [ConfigController::class, 'updateBranding']);
    Route::patch('/config/branding', [ConfigController::class, 'updateBranding']);

    // Tile management
    Route::post('/tiles', [AdminTileController::class, 'store']);
    Route::patch('/tiles/{id}', [AdminTileController::class, 'update']);
    Route::delete('/tiles/{id}', [AdminTileController::class, 'destroy']);

    // TileYear management
    Route::post('/tile-years', [AdminTileYearController::class, 'store']);
    Route::patch('/tile-years/{id}', [AdminTileYearController::class, 'update']);
    Route::delete('/tile-years/{id}', [AdminTileYearController::class, 'destroy']);

    // MetricDefinition management
    Route::post('/metric-definitions', [AdminMetricDefinitionController::class, 'store']);
    Route::patch('/metric-definitions/{id}', [AdminMetricDefinitionController::class, 'update']);
    Route::delete('/metric-definitions/{id}', [AdminMetricDefinitionController::class, 'destroy']);

    // MetricValue management
    Route::post('/metric-values', [AdminMetricValueController::class, 'store']);
    Route::patch('/metric-values/{id}', [AdminMetricValueController::class, 'update']);
    Route::delete('/metric-values/{id}', [AdminMetricValueController::class, 'destroy']);

    // Category group management
    Route::post('/category-groups', [AdminCategoryGroupController::class, 'store']);
    Route::patch('/category-groups/{id}', [AdminCategoryGroupController::class, 'update']);
    Route::delete('/category-groups/{id}', [AdminCategoryGroupController::class, 'destroy']);

    // Category management
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::patch('/categories/{id}', [AdminCategoryController::class, 'update']);
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

    // Tile-Category sync
    Route::post('/tiles/{id}/categories', [AdminTileController::class, 'syncCategories']);

    // Page management
    Route::post('/pages', [AdminPageController::class, 'store']);
    Route::patch('/pages/{id}', [AdminPageController::class, 'update']);
    Route::delete('/pages/{id}', [AdminPageController::class, 'destroy']);

    // Settings updates
    Route::patch('/config/navigation', [ConfigController::class, 'updateNavigation']);
    Route::patch('/config/footer', [ConfigController::class, 'updateFooter']);
    Route::patch('/config/general', [ConfigController::class, 'updateGeneral']);
    Route::patch('/config/dashboard', [ConfigController::class, 'updateDashboard']);
    Route::patch('/config/content', [ConfigController::class, 'updateContent']);
});

// Tenant user management routes (requires auth, role-based authorization inside controller)
Route::middleware(['auth:sanctum', 'resolve.tenant', 'throttle:60,1'])->prefix('tenants/{tenant:slug}')->group(function () {
    Route::get('/users', [TenantUserController::class, 'index']);
    Route::patch('/users/{user}', [TenantUserController::class, 'update']);
    Route::delete('/users/{user}', [TenantUserController::class, 'destroy']);
});
