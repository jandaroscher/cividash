<?php

use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminCategoryGroupController;
use App\Http\Controllers\Api\Admin\AdminMetricDefinitionController;
use App\Http\Controllers\Api\Admin\AdminMetricValueController;
use App\Http\Controllers\Api\Admin\AdminPageController;
use App\Http\Controllers\Api\Admin\AdminTileController;
use App\Http\Controllers\Api\Admin\AdminTimePeriodController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\Content\PageController as ContentPageController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FilterController;
use App\Http\Controllers\Api\OgMetaController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Tenant\TenantUserController;
use App\Http\Controllers\Api\TileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['throttle:60,1', 'auth:sanctum']);

// Authenticated user profile endpoints
Route::middleware(['throttle:60,1', 'auth:sanctum'])->prefix('me')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::patch('/', [ProfileController::class, 'update']);
    Route::put('/password', [ProfileController::class, 'updatePassword']);
});

// Public API routes with tenant resolution (Token > Domain > Default)
Route::middleware(['throttle:60,1', 'resolve.tenant'])->group(function () {
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

    // OG meta data for headless frontend deployments
    Route::get('/og-meta', [OgMetaController::class, 'show']);
});

// Public export endpoints. Separate, tighter rate limit (10/min per IP)
// since exports are more expensive than regular list endpoints. Data is public
// (same as /api/tiles) — no authentication required.
Route::middleware(['throttle:export', 'resolve.tenant'])->group(function () {
    Route::get('/tiles/{slug}/export', [ExportController::class, 'tile']);
    Route::get('/exports/tiles', [ExportController::class, 'tiles']);
    Route::get('/exports/catalog', [ExportController::class, 'catalog']);
});

// Admin API routes (secured with Sanctum + admin permission check + tenant resolution)
// Middleware order:
// 1. throttle:120,1 - rate limit (reject before expensive auth)
// 2. auth:sanctum - 401 if not authenticated
// 3. admin.api - 403 if user/token lacks permission
// 4. resolve.tenant - resolve tenant from token/domain
// 5. admin.tenant - 400 if no explicit tenant (no default fallback)
Route::middleware(['throttle:120,1', 'auth:sanctum', 'admin.api', 'resolve.tenant', 'admin.tenant'])->prefix('admin')->group(function () {
    // Config routes
    Route::post('/config/branding', [ConfigController::class, 'updateBranding']);
    Route::patch('/config/branding', [ConfigController::class, 'updateBranding']);

    // Tile management
    Route::post('/tiles', [AdminTileController::class, 'store']);
    Route::patch('/tiles/{id}', [AdminTileController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/tiles/{id}', [AdminTileController::class, 'destroy'])->where('id', '[0-9]+');

    // TimePeriod management
    Route::post('/time-periods', [AdminTimePeriodController::class, 'store']);
    Route::patch('/time-periods/{id}', [AdminTimePeriodController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/time-periods/{id}', [AdminTimePeriodController::class, 'destroy'])->where('id', '[0-9]+');

    // MetricDefinition management
    Route::post('/metric-definitions', [AdminMetricDefinitionController::class, 'store']);
    Route::patch('/metric-definitions/{id}', [AdminMetricDefinitionController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/metric-definitions/{id}', [AdminMetricDefinitionController::class, 'destroy'])->where('id', '[0-9]+');

    // MetricValue management
    Route::post('/metric-values', [AdminMetricValueController::class, 'store']);
    Route::patch('/metric-values/{id}', [AdminMetricValueController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/metric-values/{id}', [AdminMetricValueController::class, 'destroy'])->where('id', '[0-9]+');

    // Category group management
    Route::post('/category-groups', [AdminCategoryGroupController::class, 'store']);
    Route::patch('/category-groups/{id}', [AdminCategoryGroupController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/category-groups/{id}', [AdminCategoryGroupController::class, 'destroy'])->where('id', '[0-9]+');

    // Category management
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::patch('/categories/{id}', [AdminCategoryController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy'])->where('id', '[0-9]+');

    // Tile-Category sync
    Route::post('/tiles/{id}/categories', [AdminTileController::class, 'syncCategories'])->where('id', '[0-9]+');

    // Page management
    Route::post('/pages', [AdminPageController::class, 'store']);
    Route::patch('/pages/{id}', [AdminPageController::class, 'update'])->where('id', '[0-9]+');
    Route::delete('/pages/{id}', [AdminPageController::class, 'destroy'])->where('id', '[0-9]+');

    // Settings updates
    Route::patch('/config/navigation', [ConfigController::class, 'updateNavigation']);
    Route::patch('/config/footer', [ConfigController::class, 'updateFooter']);
    Route::patch('/config/general', [ConfigController::class, 'updateGeneral']);
    Route::patch('/config/dashboard', [ConfigController::class, 'updateDashboard']);
    Route::patch('/config/content', [ConfigController::class, 'updateContent']);
});

// Tenant user management routes (requires auth, role-based authorization inside controller)
Route::middleware(['throttle:60,1', 'auth:sanctum', 'resolve.tenant'])->prefix('tenants/{tenant:slug}')->group(function () {
    Route::get('/users', [TenantUserController::class, 'index']);
    Route::patch('/users/{user}', [TenantUserController::class, 'update']);
    Route::delete('/users/{user}', [TenantUserController::class, 'destroy']);
});
