import { defineConfig } from '@playwright/test';

/**
 * Playwright configuration for E2E tests.
 *
 * Tests cover: tenant resolution, multi-tenant data isolation,
 * API key lifecycle, admin panel smoke tests, dashboard navigation,
 * filter/search, locale switching, content pages, and branding.
 *
 * Prerequisites:
 * - ddev must be running: `ddev start`
 * - Test data must be seeded: `ddev php artisan e2e:seed-full --clean --json`
 * - ddev hostnames configured: a.open-source-dashboard.ddev.site, b.open-source-dashboard.ddev.site
 */
export default defineConfig({
  testDir: './tests/e2e',
  
  // Run tests sequentially for deterministic results
  fullyParallel: false,
  
  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,
  
  // Retry on CI only
  retries: process.env.CI ? 2 : 0,
  
  // Single worker for API tests (no browser parallelization needed)
  workers: 1,
  
  // Reporter configuration
  reporter: [
    ['list'],
    ['html', { open: 'never' }],
  ],
  
  // Global test timeout
  timeout: 30000,
  
  // Use projects to organize different test scenarios
  use: {
    // Base URL for the default ddev site (HTTP to avoid SSL issues)
    baseURL: 'http://open-source-dashboard.ddev.site',
    
    // Ignore HTTPS errors (in case any test uses https)
    ignoreHTTPSErrors: true,
    
    // Extra HTTP headers (if needed)
    extraHTTPHeaders: {
      'Accept': 'application/json',
    },
  },
  
  projects: [
    {
      name: 'tenant-resolution',
      testMatch: /tenant-resolution\.spec\.ts/,
    },
    {
      name: 'data-isolation',
      testMatch: /data-isolation\.spec\.ts/,
    },
    {
      name: 'api-key-lifecycle',
      testMatch: /api-key-lifecycle\.spec\.ts/,
    },
    {
      name: 'admin-smoke',
      testMatch: /smoke-test\.spec\.ts/,
    },
    {
      name: 'dashboard-navigation',
      testMatch: /dashboard-navigation\.spec\.ts/,
    },
    {
      name: 'filter-search',
      testMatch: /filter-search\.spec\.ts/,
    },
    {
      name: 'locale-switching',
      testMatch: /locale-switching\.spec\.ts/,
    },
    {
      name: 'content-pages',
      testMatch: /content-pages\.spec\.ts/,
    },
    {
      name: 'branding',
      testMatch: /branding\.spec\.ts/,
    },
  ],
});
