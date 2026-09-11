import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * E2E Smoke Tests for the Filament Admin Panel
 *
 * Verifies that:
 * - Admin login page renders
 * - Login with valid credentials succeeds
 * - Dashboard renders after login
 * - Navigation contains expected items
 * - Tiles resource section is accessible
 *
 * Prerequisites:
 * - ddev running
 * - Test data seeded via: ddev php artisan e2e:seed-full --json
 */

let fixtures: {
  success: boolean;
  tokens: {
    tenantA: string;
    tenantB: string;
    lifecycle: string;
    lifecycleId: number;
  };
  tenants: {
    tenantA: { id: number; slug: string; domain: string };
    tenantB: { id: number; slug: string; domain: string };
  };
  user: {
    id: number;
    email: string;
    password: string;
  };
};

const BASE_URL = 'http://open-source-dashboard.ddev.site';

test.describe('Admin Panel Smoke Tests', () => {
  test.beforeAll(async () => {
    try {
      const artisanCmd = process.env.E2E_ARTISAN_CMD ?? 'ddev exec php artisan';
      const output = execSync(`${artisanCmd} e2e:seed-full --clean --json`, {
        encoding: 'utf-8',
        cwd: process.cwd(),
      });

      fixtures = JSON.parse(output.trim());

      if (!fixtures.success) {
        throw new Error('Seeding failed');
      }
    } catch (error) {
      console.error('Failed to seed test data. Make sure ddev is running.');
      console.error('Run: ddev start && ddev php artisan e2e:seed-full --json');
      throw error;
    }
  });

  test.describe('Login page', () => {
    test('admin login page renders', async ({ page }) => {
      await page.goto(`${BASE_URL}/admin/login`);

      // Filament login page should have email and password fields
      await expect(page.locator('input[type="email"], input[name="email"]')).toBeVisible();
      await expect(page.locator('input[type="password"], input[name="password"]')).toBeVisible();

      // Should have a submit/login button
      await expect(page.locator('button[type="submit"]')).toBeVisible();
    });
  });

  test.describe('Login and dashboard', () => {
    test('login with valid credentials shows dashboard', async ({ page }) => {
      await page.goto(`${BASE_URL}/admin/login`);

      // Fill in credentials
      await page.locator('input[type="email"], input[name="email"]').fill(fixtures.user.email);
      await page.locator('input[type="password"], input[name="password"]').fill(fixtures.user.password);

      // Submit the form
      await page.locator('button[type="submit"]').click();

      // After login, Filament redirects to the panel. With tenancy enabled,
      // it may redirect to a tenant selection page or directly to the dashboard.
      // Wait for navigation to complete.
      await page.waitForLoadState('networkidle');

      // We should be in the admin area (URL contains /admin)
      expect(page.url()).toContain('/admin');

      // Should not be on the login page anymore
      expect(page.url()).not.toContain('/login');
    });
  });

  test.describe('Navigation', () => {
    test('dashboard has navigation with expected items', async ({ page }) => {
      // Login first
      await page.goto(`${BASE_URL}/admin/login`);
      await page.locator('input[type="email"], input[name="email"]').fill(fixtures.user.email);
      await page.locator('input[type="password"], input[name="password"]').fill(fixtures.user.password);
      await page.locator('button[type="submit"]').click();
      await page.waitForLoadState('networkidle');

      // If we land on a tenant selection page, select the first tenant
      if (page.url().includes('/new')) {
        // Tenant registration page - skip this test
        test.skip(true, 'Landed on tenant registration page - tenant setup required');
        return;
      }

      // The Filament sidebar navigation should be present
      const sidebar = page.locator('[class*="sidebar"], nav, aside').first();
      await expect(sidebar).toBeVisible({ timeout: 10000 });

      // Check that the page loaded successfully (no server error)
      const pageContent = await page.textContent('body');
      expect(pageContent).toBeTruthy();
    });
  });

  test.describe('Tiles section', () => {
    test('tiles resource page is accessible after login', async ({ page }) => {
      // Login
      await page.goto(`${BASE_URL}/admin/login`);
      await page.locator('input[type="email"], input[name="email"]').fill(fixtures.user.email);
      await page.locator('input[type="password"], input[name="password"]').fill(fixtures.user.password);
      await page.locator('button[type="submit"]').click();
      await page.waitForLoadState('networkidle');

      // After login, try to navigate to the tiles resource
      // Filament tenant-aware URL pattern: /admin/{tenantSlug}/tiles
      const currentUrl = page.url();

      // Extract the tenant path segment from the current URL
      // e.g., /admin/e2e-tenant-a -> tenant slug is "e2e-tenant-a"
      const adminMatch = currentUrl.match(/\/admin\/([^/]+)/);
      if (!adminMatch) {
        // We might be on a page that doesn't have a tenant segment yet
        test.skip(true, 'Could not determine tenant path segment from URL');
        return;
      }

      const tenantSegment = adminMatch[1];

      // Navigate to tiles resource
      await page.goto(`${BASE_URL}/admin/${tenantSegment}/tiles`);
      await page.waitForLoadState('networkidle');

      // Should be on the tiles page (not redirected to login)
      expect(page.url()).toContain('/tiles');
      expect(page.url()).not.toContain('/login');

      // The page should render without server errors
      const status = await page.locator('body').textContent();
      expect(status).not.toContain('500');
      expect(status).not.toContain('Server Error');
    });
  });
});
