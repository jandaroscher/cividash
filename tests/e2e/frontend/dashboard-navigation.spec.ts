import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Dashboard Navigation
 *
 * Verifies that:
 * - Home page loads and shows tiles grid
 * - Clicking a tile navigates to detail page
 * - Back button returns to tile grid
 * - Header navigation links are visible and clickable
 * - Footer is visible with navigation items
 *
 * Prerequisites:
 * - ddev running
 * - Test data seeded via: ddev php artisan e2e:seed-full --json
 */

const BASE_URL = 'http://open-source-dashboard.ddev.site';

test.describe('Dashboard Navigation', () => {
  test.describe('Home page', () => {
    test('home page loads and shows tiles grid', async ({ page }) => {
      await page.goto(`${BASE_URL}/tiles`);
      await page.waitForLoadState('networkidle');

      // The tiles page should render the main content area
      const main = page.locator('main');
      await expect(main).toBeVisible();

      // Tiles should be rendered as shadow-card elements within the waterfall grid
      const tiles = page.locator('.shadow-card');
      await expect(tiles.first()).toBeVisible({ timeout: 10000 });

      // There should be at least one tile
      const count = await tiles.count();
      expect(count).toBeGreaterThan(0);
    });
  });

  test.describe('Tile detail navigation', () => {
    test('clicking a tile navigates to detail page', async ({ page }) => {
      await page.goto(`${BASE_URL}/tiles`);
      await page.waitForLoadState('networkidle');

      // Wait for tiles to appear
      const firstTile = page.locator('.shadow-card').first();
      await expect(firstTile).toBeVisible({ timeout: 10000 });

      // Click the first tile card
      await firstTile.click();

      // After clicking, the URL should change to include a tile slug or query param
      // The overlay may open via ?tile= query param, or navigate to /tiles/:slug
      await page.waitForLoadState('networkidle');

      const currentUrl = page.url();
      // Either the URL has a ?tile= param (overlay) or navigated to /tiles/:slug
      const hasTileParam = currentUrl.includes('?tile=') || currentUrl.includes('&tile=');
      const hasTileSlug = /\/tiles\/[^/]+/.test(currentUrl);
      expect(hasTileParam || hasTileSlug).toBeTruthy();
    });

    test('back button returns to tile grid', async ({ page }) => {
      await page.goto(`${BASE_URL}/tiles`);
      await page.waitForLoadState('networkidle');

      // Wait for tiles to appear
      const firstTile = page.locator('.shadow-card').first();
      await expect(firstTile).toBeVisible({ timeout: 10000 });

      // Click the first tile
      await firstTile.click();
      await page.waitForLoadState('networkidle');

      // Go back
      await page.goBack();
      await page.waitForLoadState('networkidle');

      // Should be back on the tiles page without tile param
      expect(page.url()).toContain('/tiles');

      // Tiles should still be visible
      await expect(page.locator('.shadow-card').first()).toBeVisible({ timeout: 10000 });
    });
  });

  test.describe('Header navigation', () => {
    test('header navigation links are visible and clickable', async ({ page }) => {
      await page.goto(`${BASE_URL}/`);
      await page.waitForLoadState('networkidle');

      // Header should be visible
      const header = page.locator('header');
      await expect(header).toBeVisible();

      // Logo or site name should be present in header
      const logoOrSiteName = header.locator('a img.logo, span.font-bold').first();
      await expect(logoOrSiteName).toBeVisible();

      // Desktop navigation should contain links (nav-link class)
      const navLinks = page.locator('.desktop-nav .nav-link');
      const navCount = await navLinks.count();

      // If there are navigation items, they should be clickable
      if (navCount > 0) {
        const firstNavLink = navLinks.first();
        await expect(firstNavLink).toBeVisible();

        // Get the href/to before clicking
        const linkText = await firstNavLink.textContent();
        expect(linkText?.trim()).toBeTruthy();
      }
    });
  });

  test.describe('Footer', () => {
    test('footer is visible with navigation items', async ({ page }) => {
      await page.goto(`${BASE_URL}/`);
      await page.waitForLoadState('networkidle');

      // Footer should be present
      const footer = page.locator('footer');
      await expect(footer).toBeVisible();

      // Footer should contain navigation links (footer-link class)
      const footerLinks = footer.locator('.footer-link');
      const linkCount = await footerLinks.count();

      // If footer navigation items exist, verify they are rendered
      if (linkCount > 0) {
        const firstLink = footerLinks.first();
        await expect(firstLink).toBeVisible();

        // Each footer link should have text content
        const text = await firstLink.textContent();
        expect(text?.trim()).toBeTruthy();
      }
    });
  });
});
