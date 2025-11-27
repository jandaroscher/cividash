<?php

use App\Models\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Z3d0X\FilamentFabricator\Http\Controllers\PageController;

Route::get('/', function () {
    // Ensure locale is set to 'de' for root route
    app()->setLocale('de');
    
    // Check if pages table exists and try to find a Fabricator page with slug '/' or 'home'
    try {
        if (Schema::hasTable(config('filament-fabricator.table_name', 'pages'))) {
            $locale = app()->getLocale();
            
            // Validate locale against whitelist to prevent SQL injection
            $allowedLocales = ['de', 'en'];
            if (!in_array($locale, $allowedLocales)) {
                $locale = 'de'; // Fallback to default locale
            }
            
            // Try to find root page for current locale
            // First try slug '/', then 'home'
            $homePage = Page::query()
                ->whereRaw("JSON_EXTRACT(slug, '$.{$locale}') = ?", ['/'])
                ->orWhereRaw("JSON_EXTRACT(slug, '$.{$locale}') = ?", ['home'])
                ->orderByRaw("CASE WHEN JSON_EXTRACT(slug, '$.{$locale}') = '/' THEN 0 ELSE 1 END")
                ->first();
            
            // If not found for current locale, try default locale (de)
            if (!$homePage && $locale !== 'de') {
                $homePage = Page::query()
                    ->whereRaw("JSON_EXTRACT(slug, '$.de') = ?", ['/'])
                    ->orWhereRaw("JSON_EXTRACT(slug, '$.de') = ?", ['home'])
                    ->orderByRaw("CASE WHEN JSON_EXTRACT(slug, '$.de') = '/' THEN 0 ELSE 1 END")
                    ->first();
            }

            if ($homePage) {
                // Use Fabricator PageController to render the page
                $controller = app(PageController::class);
                return $controller($homePage);
            } else {
                // Log when no page is found for debugging
                Log::info('Root route: No home page found', ['locale' => $locale]);
            }
        }
    } catch (\Exception $e) {
        // Log the exception for debugging
        Log::warning('Root route error: ' . $e->getMessage(), ['exception' => $e]);
        // If table doesn't exist or any other error, fall back to Vue SPA
    }

    // Fallback to Vue SPA under /app if no Fabricator home page exists
    return redirect()->to('/app');
});

// Route for EN root page (/en)
Route::get('/en', function () {
    // Ensure locale is set to 'en' for EN root route
    app()->setLocale('en');
    
    try {
        if (Schema::hasTable(config('filament-fabricator.table_name', 'pages'))) {
            // Find root page for EN locale (slug = '/')
            $homePage = Page::query()
                ->whereRaw("JSON_EXTRACT(slug, '$.en') = ?", ['/'])
                ->orWhereRaw("JSON_EXTRACT(slug, '$.en') = ?", ['home'])
                ->orderByRaw("CASE WHEN JSON_EXTRACT(slug, '$.en') = '/' THEN 0 ELSE 1 END")
                ->first();
            
            // If not found for EN, try default locale (de)
            if (!$homePage) {
                $homePage = Page::query()
                    ->whereRaw("JSON_EXTRACT(slug, '$.de') = ?", ['/'])
                    ->orWhereRaw("JSON_EXTRACT(slug, '$.de') = ?", ['home'])
                    ->orderByRaw("CASE WHEN JSON_EXTRACT(slug, '$.de') = '/' THEN 0 ELSE 1 END")
                    ->first();
                
                // Reset locale to 'de' when using German fallback page
                if ($homePage) {
                    app()->setLocale('de');
                }
            }

            if ($homePage) {
                $controller = app(PageController::class);
                return $controller($homePage);
            } else {
                // Log when no page is found for debugging
                Log::info('EN root route: No home page found');
            }
        }
    } catch (\Exception $e) {
        // Log the exception for debugging
        Log::warning('EN root route error: ' . $e->getMessage(), ['exception' => $e]);
        // Fall back to Vue SPA
    }

    return redirect()->to('/app');
});

// Route for EN pages with /en/ prefix
Route::get('/en/{slug}', function (string $slug) {
    // Ensure locale is set to 'en' for EN routes
    app()->setLocale('en');
    
    try {
        if (Schema::hasTable(config('filament-fabricator.table_name', 'pages'))) {
            // Find page by slug (without /en/ prefix) for EN locale
            $page = Page::query()
                ->whereRaw("JSON_EXTRACT(slug, '$.en') = ?", [$slug])
                ->first();

            if ($page) {
                $controller = app(PageController::class);
                return $controller($page);
            }
        }
    } catch (\Exception $e) {
        // Log the exception for debugging
        Log::warning('EN slug route error: ' . $e->getMessage(), ['exception' => $e]);
        // Fall back to Vue SPA
    }

    return redirect()->to('/app');
})->where('slug', '[^/]+'); // Exclude empty slug and slashes (handled by /en route above)

// Vue SPA route - must be defined BEFORE /{slug} route to prevent it from catching /app
Route::view('/app/{any?}', 'app')
    ->where('any', '.*')
    ->name('spa');

// Route for DE pages (without prefix)
// This will be handled by Fabricator's auto-routing if enabled
// But we add a fallback for direct access
Route::get('/{slug}', function (string $slug) {
    // Ensure locale is set to 'de' for DE routes
    app()->setLocale('de');
    
    try {
        if (Schema::hasTable(config('filament-fabricator.table_name', 'pages'))) {
            // Find page by slug for DE locale
            $page = Page::query()
                ->whereRaw("JSON_EXTRACT(slug, '$.de') = ?", [$slug])
                ->first();

            if ($page) {
                $controller = app(PageController::class);
                return $controller($page);
            }
        }
    } catch (\Exception $e) {
        // Log the exception for debugging
        Log::warning('DE slug route error: ' . $e->getMessage(), ['exception' => $e]);
        // Fall back to Vue SPA
    }

    return redirect()->to('/app');
})->where('slug', '[^/]+');
