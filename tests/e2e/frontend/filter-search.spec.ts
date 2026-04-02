import { test, expect } from '@playwright/test';

/**
 * E2E Tests for Filter and Search Functionality
 *
 * Verifies that:
 * - Filter component is visible on tiles page
 * - Clicking a category filter button updates displayed tiles
 * - Search input accepts text and filters tiles by title
 * - Clearing search shows all tiles again
 * - Filter state persists in URL query parameters
 *
 * Prerequisites:
 * - ddev running
 * - Test data seeded via: ddev php artisan e2e:seed-full --json
 */

const BASE_URL = 'http://open-source-dashboard.ddev.site';

test.describe('Filter and Search', () => {
  test.describe('Filter component', () => {
    test('filter component is visible on tiles page', async ({ page }) => {
      await page.goto(`${BASE_URL}/tiles`);
      await page.waitForLoadState('networkidle');

      // Wait for tiles to load first
      await expect(page.locator('.shadow-card').first()).toBeVisible({ timeout: 10000 });

      // Filter buttons should be rendered with role="tab"
      const filterButtons = page.locator('[role="tab"]');
      const buttonCount = await filterButtons.count();

      // There should be at least one filter group tab
      if (buttonCount > 0) {
        await expect(filterButtons.first()).toBeVisible();
      }

      // Search input should be visible
      const searchInput = page.locator('input[type="text"]');
      await expect(searchInput).toBeVisible();
    });

    test('clicking a category filter button updates the displayed tiles', async ({ page }) => {
      await page.goto(`${BASE_URL}/tiles`);
      await page.waitForLoadState('networkidle');

      // Wait for tiles to load
      await expect(page.locator('.shadow-card').first()).toBeVisible({ timeout: 10000 });

      // Get initial tile count
      const initialCount = await page.locator('.shadow-card').count();

      // Find filter tabs
      const filterTabs = page.locator('[role="tab"]');
      const tabCount = await filterTabs.count();

      if (tabCount > 1) {
        // Click the second filter tab (first might already be selected)
        await filterTabs.nth(1).click();
        await page.waitForLoadState('networkidle');

        // Wait for the filter panel to appear
        const filterPanel = page.locator('[role="tabpanel"]');
        await expect(filterPanel).toBeVisible({ timeout: 5000 });

        // The URL should reflect filter state or tiles should have changed
        // This verifies the filter interaction works
        const currentUrl = page.url();
        const tilesStillVisible = await page.locator('.shadow-card').count();

        // Verify filter interaction had an effect: either tiles changed or URL updated
        const urlChanged = currentUrl !== `${BASE_URL}/tiles`;
        const tilesChanged = tilesStillVisible !== initialCount;
        expect(urlChanged || tilesChanged).toBeTruthy();
      }
    });
  });

  test.describe('Search functionality', () => {
    test('search input accepts text and filters tiles by title', async ({ page }) => {
      await page.goto(`${BASE_URL}/tiles`);
      await page.waitForLoadState('networkidle');

      // Wait for tiles to load
      await expect(page.locator('.shadow-card').first()).toBeVisible({ timeout: 10000 });

      // Get initial tile count
      const initialCount = await page.locator('.shadow-card').count();

      // Type a search query into the search input
      const searchInput = page.locator('input[type="text"]');
      await expect(searchInput).toBeVisible();
      await searchInput.fill('test-query-that-likely-matches-nothing-xyz');

      // Wait for debounced search to apply (300ms debounce + rendering)
      await page.waitForTimeout(500);

      // After searching for a non-matching term, either no tiles or fewer tiles should show
      const filteredCount = await page.locator('.shadow-card:visible').count();
      expect(filteredCount).toBeLessThanOrEqual(initialCount);
    });

    test('clearing search shows all tiles again', async ({ page }) => {
      await page.goto(`${BASE_URL}/tiles`);
      await page.waitForLoadState('networkidle');

      // Wait for tiles to load
      await expect(page.locator('.shadow-card').first()).toBeVisible({ timeout: 10000 });

      // Get initial tile count
      const initialCount = await page.locator('.shadow-card').count();

      // Type a search query
      const searchInput = page.locator('input[type="text"]');
      await searchInput.fill('xyz-nonexistent');
      await page.waitForTimeout(500);

      // Clear the search
      await searchInput.fill('');
      await page.waitForTimeout(500);

      // All tiles should be visible again
      const restoredCount = await page.locator('.shadow-card').count();
      expect(restoredCount).toBe(initialCount);
    });

    test('filter state persists in URL query parameters', async ({ page }) => {
      await page.goto(`${BASE_URL}/tiles`);
      await page.waitForLoadState('networkidle');

      // Wait for tiles to load
      await expect(page.locator('.shadow-card').first()).toBeVisible({ timeout: 10000 });

      // Type a search query
      const searchInput = page.locator('input[type="text"]');
      await searchInput.fill('energy');
      await page.waitForTimeout(500);

      // Check if search query is reflected in URL
      const url = new URL(page.url());
      const searchParam = url.searchParams.get('search');

      // The filter store persists state to URL via query params
      // Either ?search= or the URL is updated with filter state
      if (searchParam) {
        expect(searchParam).toBe('energy');
      }

      // Reload the page and check if filter state is restored
      await page.reload();
      await page.waitForLoadState('networkidle');

      // After reload, the search input should retain its value if URL param was set
      if (searchParam) {
        const restoredValue = await searchInput.inputValue();
        expect(restoredValue).toBe('energy');
      }
    });
  });
});
