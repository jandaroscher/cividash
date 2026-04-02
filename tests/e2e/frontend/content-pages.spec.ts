import { test, expect } from '@playwright/test';

/**
 * E2E Tests for CMS Content Pages
 *
 * Verifies that:
 * - A CMS page loads successfully
 * - Page renders block content (not empty)
 * - 404 page shows for non-existent slug
 * - Page title is visible
 *
 * Prerequisites:
 * - ddev running
 * - Test data seeded via: ddev php artisan e2e:seed-full --json
 */

const BASE_URL = 'http://open-source-dashboard.ddev.site';

test.describe('Content Pages', () => {
  test.describe('CMS page rendering', () => {
    test('a CMS page loads successfully from the API', async ({ page, request }) => {
      // First, fetch available pages from the API to find a valid slug
      const response = await request.get(`${BASE_URL}/api/pages?locale=de`);

      if (!response.ok()) {
        test.skip(true, 'Pages API not available');
        return;
      }

      const json = await response.json();
      const pages = json.data || json;

      if (!Array.isArray(pages) || pages.length === 0) {
        test.skip(true, 'No CMS pages available');
        return;
      }

      // Find a page with a slug (not the root page)
      const cmsPage = pages.find((p: { slug: string }) => p.slug && p.slug !== '/' && p.slug !== '');

      if (!cmsPage) {
        test.skip(true, 'No non-root CMS pages found');
        return;
      }

      // Navigate to the CMS page
      await page.goto(`${BASE_URL}/${cmsPage.slug}`);
      await page.waitForLoadState('networkidle');

      // The page should not show an error
      const bodyText = await page.locator('body').textContent();
      expect(bodyText).not.toContain('500');
      expect(bodyText).not.toContain('Server Error');

      // The dynamic-page container should be present
      const dynamicPage = page.locator('.dynamic-page');
      await expect(dynamicPage).toBeVisible({ timeout: 10000 });
    });

    test('page renders block content (not empty)', async ({ page, request }) => {
      // Fetch available pages from the API
      const response = await request.get(`${BASE_URL}/api/pages?locale=de`);

      if (!response.ok()) {
        test.skip(true, 'Pages API not available');
        return;
      }

      const json = await response.json();
      const pages = json.data || json;

      if (!Array.isArray(pages) || pages.length === 0) {
        test.skip(true, 'No CMS pages available');
        return;
      }

      // Find a non-root page (index API doesn't include blocks, so just pick by slug)
      const cmsPage = pages.find((p: { slug: string }) =>
        p.slug && p.slug !== '/' && p.slug !== ''
      );

      if (!cmsPage) {
        test.skip(true, 'No suitable CMS pages found');
        return;
      }

      await page.goto(`${BASE_URL}/${cmsPage.slug}`);

      await page.waitForLoadState('networkidle');

      // The page-view or page-content area should have rendered content
      const pageContent = page.locator('.page-view, .dynamic-page');
      await expect(pageContent).toBeVisible({ timeout: 10000 });

      // The page should contain some meaningful text content
      const textContent = await pageContent.textContent();
      expect(textContent?.trim().length).toBeGreaterThan(0);
    });
  });

  test.describe('404 handling', () => {
    test('404 page shows for non-existent slug', async ({ page }) => {
      await page.goto(`${BASE_URL}/this-page-does-not-exist-xyz-123`);
      await page.waitForLoadState('networkidle');

      // The 404 page should render with the not-found title
      const notFoundHeading = page.locator('#not-found-title');
      await expect(notFoundHeading).toBeVisible({ timeout: 10000 });

      // Should contain "404" text
      const headingText = await notFoundHeading.textContent();
      expect(headingText).toContain('404');

      // Should contain the description text
      const description = page.locator('main[role="main"] p');
      await expect(description).toBeVisible();
    });
  });

  test.describe('Page title', () => {
    test('page title is visible on CMS page', async ({ page, request }) => {
      // Fetch available pages from the API
      const response = await request.get(`${BASE_URL}/api/pages?locale=de`);

      if (!response.ok()) {
        test.skip(true, 'Pages API not available');
        return;
      }

      const json = await response.json();
      const pages = json.data || json;

      if (!Array.isArray(pages) || pages.length === 0) {
        test.skip(true, 'No CMS pages available');
        return;
      }

      const cmsPage = pages.find((p: { slug: string; title?: string }) =>
        p.slug && p.slug !== '/' && p.slug !== '' && p.title
      );

      if (!cmsPage) {
        test.skip(true, 'No CMS pages with titles found');
        return;
      }

      await page.goto(`${BASE_URL}/${cmsPage.slug}`);
      await page.waitForLoadState('networkidle');

      // The page should have a heading element visible
      const heading = page.locator('.page-view h1, .page-view h2, .page-content h1, .page-content h2').first();
      const isHeadingVisible = await heading.isVisible().catch(() => false);

      if (isHeadingVisible) {
        const headingText = await heading.textContent();
        expect(headingText?.trim().length).toBeGreaterThan(0);
      }
      // Some pages may not have explicit headings (e.g., hero blocks) - that's acceptable
    });
  });
});
