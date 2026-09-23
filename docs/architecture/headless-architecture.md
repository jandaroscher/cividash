# Headless Architecture: Vue SPA with Laravel API

This document describes the headless architecture of CiviDash.

## Overview

- **Backend (Laravel)**: Provides REST API endpoints for all data (tiles, pages, config, filters)
- **Frontend (Vue 3 SPA)**: Handles all routing and rendering via Vue Router 4
- **Entry Point**: `resources/views/app.blade.php` serves the Vue app for all public routes
- **No Server-Side Rendering**: Pages are rendered client-side via API calls; only meta tags are rendered on the server

## Architecture Components

### Backend API

All data is provided via REST API endpoints, among them:

- `/api/tiles`, `/api/tiles/{slug}` - Tile data
- `/api/content/pages` - Content pages list
- `/api/content/pages/root` - Root/home page
- `/api/content/pages/{id}` - Specific page by ID
- `/api/config/*` - Configuration (branding, general, header, footer, tenant, dashboard, content)
- `/api/filters` - Filter options

All endpoints support `?locale=de` or `?locale=en` query parameter for localization. The full
reference is served at `/docs`.

### Frontend Vue SPA

**Entry Point**: `resources/js/app.js`
- Initializes Vue 3 app
- Sets up Pinia stores
- Configures Vue Router
- Mounts to `#app` in `app.blade.php`

**Router**: `resources/js/router/index.js`
- History mode: `createWebHistory('/')`
- Routes:
  - `/` - HomePage (loads root page via API)
  - `/tiles` - TilesPage (tile explorer)
  - `/tiles/:slug` - TileDetailPage
  - `/en`, `/en/tiles`, `/en/tiles/:slug` - English variants
  - `/en/:slug+` - DynamicPage (English)
  - `/:slug+` - DynamicPage (German)
  - `/:pathMatch(.*)*` - NotFound (404)

**Stores (Pinia)**: `resources/js/stores/` holds pages, tiles, branding, filter, overlay, help,
header, footer and tenant state.

**Components**:
- `components/pages/PageView.vue` - Renders pages with blocks
- `components/pages/HomePage.vue` - Homepage component
- `components/pages/DynamicPage.vue` - Dynamic page component
- `components/pages/TilesPage.vue` - Tile explorer page
- `components/pages/TileDetailPage.vue` - Tile detail page
- `components/pages/NotFound.vue` - 404 page
- `components/BlockRenderer.vue` - Renders content blocks dynamically

**Composables**: `resources/js/composables/` (locale, help context, indicators, exports, image URLs).

## Routing Strategy

### Laravel Routes (`routes/web.php`)

All public routes are handled by a catch-all that serves `app.blade.php` through `SpaController`:

```php
Route::get('/{any?}', [SpaController::class, 'index'])
    ->where('any', '^(?!api|admin|filament|telescope|horizon|storage|share|embeds|_dusk|tinker).*$')
    ->middleware(['resolve.tenant'])
    ->name('spa');
```

This excludes:
- `/api/*` - API routes
- `/admin/*` - Admin panel
- `/filament/*` - Filament admin
- System routes (telescope, horizon, storage, etc.)

### Vue Router Routes

Vue Router handles all client-side routing from the site root, with locale handling via route
meta and path prefixes (`/en` for English).

## Locale Handling

1. **URL-based**: `/en/:slug` for English, `/:slug` for German
2. **Route Meta**: Locale stored in `route.meta.locale`
3. **LocalStorage**: Locale persisted in `localStorage.getItem('locale')`
4. **API Calls**: Locale passed as `?locale=de` or `?locale=en` query parameter

## Page Rendering Flow

1. User navigates to URL (e.g., `/kontakt`)
2. Laravel serves `app.blade.php` (catch-all route) with server-side meta tags
3. Vue app initializes and Vue Router takes over
4. Router matches route and loads component (e.g., `DynamicPage`)
5. Component uses `usePagesStore` to fetch page data via API
6. `PageView` component renders page with `BlockRenderer`

## Content Blocks

Content blocks are rendered dynamically via `BlockRenderer`:
- `hero` - HeroBlock
- `text-image` - TextImageBlock
- `intro-text` - IntroTextBlock
- `section` - SectionBlock
- `list` - ListBlock
- `faq` - FAQBlock
- `link` - LinkBlock
- `slider` - SliderBlock
- `tile-app` / `card-grid` - TileAppBlock
- `download` - DownloadBlock

Blocks are provided by the API in format:
```json
{
  "type": "hero",
  "props": { ... }
}
```

`BlockRenderer` transforms this to the format expected by block components.

## SEO Considerations

`SpaController` resolves title, description and Open Graph tags per path with `MetaTagService`
and renders them into `app.blade.php`, so crawlers that do not execute JavaScript still see them.

**Limitation**: Page content itself is rendered client-side, so search engines that do not run
JavaScript do not see it.

## Development

### Local Development

1. Start Laravel: `ddev start`
2. Start Vite: `npm run dev`
3. Access: `https://open-source-dashboard.ddev.site`

### Building for Production

```bash
npm run build
```

This compiles the Vue app to `public/build/`.

## API Integration

API calls use the page origin as base URL (`getApiBaseUrl()` in `resources/js/utils/api.js`).

Example API call:
```javascript
const res = await fetch(`${getApiBaseUrl()}/api/content/pages/root?locale=de`);
const json = await res.json();
const pageData = json.data;
```

## Testing

- **Backend Tests**: PHPUnit tests for API endpoints
- **Frontend Tests**: Vitest component tests and Playwright E2E tests
