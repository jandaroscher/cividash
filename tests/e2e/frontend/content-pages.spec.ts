// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect, type Page } from '@playwright/test';
import { fetchApi, resetApiThrottle, escapeRegExp } from './helpers';

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
 * - A running app at PLAYWRIGHT_BASE_URL, pointing at the demo-city tenant
 * - CMS pages for the demo-city tenant: dashboard:reset (see docs/testing/README.md)
 */

type CmsPage = { slug: string; title: string };

/** Open the first non-root CMS page of the tenant and return it. */
async function openCmsPage(page: Page): Promise<CmsPage> {
  await page.goto('/');
  const pages = await fetchApi<CmsPage[]>(page, '/api/v1/content/pages?locale=de');
  const cmsPage = pages.find((p) => p.slug && p.slug !== '/' && p.title);
  expect(cmsPage, 'a non-root CMS page with a title').toBeDefined();

  await page.goto(`/${cmsPage!.slug}`);
  return cmsPage!;
}

test.beforeEach(resetApiThrottle);

test.describe('Content Pages', () => {
  test.describe('CMS page rendering', () => {
    test('a CMS page loads successfully from the API', async ({ page }) => {
      await openCmsPage(page);

      await expect(page.locator('.dynamic-page .page-view')).toBeVisible();
      await expect(page.locator('#not-found-title')).toHaveCount(0);
      await expect(page.locator('.dynamic-page')).not.toContainText(/Fehler beim Laden|Error loading|Server Error/);
    });

    test('page renders block content (not empty)', async ({ page }) => {
      await openCmsPage(page);

      const pageContent = page.locator('.page-view .page-content');
      await expect(pageContent).toBeVisible();
      await expect(pageContent).not.toHaveText(/^\s*$/);
    });
  });

  test.describe('404 handling', () => {
    test('404 page shows for non-existent slug', async ({ page }) => {
      await page.goto('/this-page-does-not-exist-xyz-123');

      const notFoundHeading = page.locator('#not-found-title');
      await expect(notFoundHeading).toBeVisible();
      await expect(notFoundHeading).toContainText('404');

      await expect(page.getByRole('main').locator('#not-found-title ~ p')).toBeVisible();
    });
  });

  test.describe('Page title', () => {
    test('page title is visible on CMS page', async ({ page }) => {
      const cmsPage = await openCmsPage(page);

      await expect(page).toHaveTitle(new RegExp(escapeRegExp(cmsPage.title)));
      const heading = page.locator('.page-content h1, .page-content h2').first();
      await expect(heading).toBeVisible();
      await expect(heading).not.toHaveText(/^\s*$/);
    });
  });
});
