// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

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
 *
 * Set PLAYWRIGHT_BASE_URL to run against another server, e.g. `php artisan serve`.
 * Specs seed fixtures via `${E2E_ARTISAN_CMD} e2e:seed-full --json` etc.;
 * E2E_ARTISAN_CMD defaults to `ddev exec php artisan`. tenant-resolution.spec.ts and
 * export.spec.ts need the ddev tenant subdomains and keep their own hostnames.
 * See docs/testing/README.md.
 */
export default defineConfig({
  testDir: './tests/e2e',
  
  // Run tests sequentially for deterministic results
  fullyParallel: false,
  
  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,
  
  // Retry on CI only
  retries: process.env.CI ? 1 : 0,

  // Single worker everywhere: several specs run `e2e:seed-full --clean` against
  // the same DB, and 2 CI workers raced two of those --clean calls into a real
  // failure (not a dev-server issue). Keep this at 1 unless that seeding is made
  // safe to run concurrently.
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
    // Default ddev site over HTTP to avoid SSL issues
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://open-source-dashboard.ddev.site',
    
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
    {
      name: 'accessibility',
      testMatch: /a11y-audit\.spec\.ts/,
    },    {
      name: 'exports',
      testMatch: /export\.spec\.ts/,
    },
  ],
});
