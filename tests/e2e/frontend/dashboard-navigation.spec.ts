// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect } from '@playwright/test';
import { fetchApi, resetApiThrottle, escapeRegExp } from './helpers';

/**
 * E2E Tests for Dashboard Navigation
 *
 * Verifies that:
 * - Home page loads and shows tiles grid
 * - A tile's Info button opens its details (?tile= overlay), closing returns to the grid
 * - Browser back from a tile detail page returns to the tile grid
 * - Header navigation links are visible and clickable
 * - Footer is visible with navigation items
 *
 * Prerequisites:
 * - A running app at PLAYWRIGHT_BASE_URL, pointing at the demo-city tenant
 * - Tiles for the demo-city tenant: dashboard:reset (see docs/testing/README.md;
 *   it deletes tenant content)
 */

test.beforeEach(resetApiThrottle);

test.describe('Dashboard Navigation', () => {
  test.describe('Tiles page', () => {
    test('tiles page loads and shows the tile grid', async ({ page }) => {
      await page.goto('/tiles');

      await expect(page.locator('main')).toBeVisible();
      // Tiles are rendered as shadow-card elements within the waterfall grid
      await expect(page.locator('.shadow-card').first()).toBeVisible();
    });
  });

  test.describe('Tile detail navigation', () => {
    test('the Info button opens the tile details', async ({ page }) => {
      await page.goto('/tiles');

      const firstTile = page.locator('.shadow-card').first();
      await expect(firstTile).toBeVisible();

      // A tile opens its details in an overlay dialog via its Info button
      await firstTile.getByRole('button', { name: 'Info' }).click();

      const dialog = page.getByRole('dialog');
      await expect(dialog).toBeVisible();
      await expect(page).toHaveURL(/[?&]tile=\d+/);

      // The dialog shows the title of the tile that was clicked
      const title = (await dialog.getByRole('heading').first().textContent())?.trim() ?? '';
      expect(title).not.toBe('');
      await expect(firstTile).toContainText(title);

      // Closing the details returns to the plain grid
      await dialog.getByRole('button', { name: /^(Overlay schließen|Close overlay)$/ }).click();
      await expect(dialog).toBeHidden();
      await expect(page).not.toHaveURL(/[?&]tile=/);
      await expect(firstTile).toBeVisible();
    });

    test('browser back from a shared tile link returns to the tile grid', async ({ page }) => {
      await page.goto('/tiles');
      await expect(page.locator('.shadow-card').first()).toBeVisible();

      // Open a tile's detail page (the URL its share button hands out)
      const tiles = await fetchApi<{ slug: string }[]>(page, '/api/v1/tiles?locale=de');
      await page.goto(`/tiles/${tiles[0].slug}`);
      await expect(page.locator('.tile-detail-content h1')).toBeVisible();

      await page.goBack();

      await expect(page).toHaveURL(/\/tiles$/);
      await expect(page.locator('.shadow-card').first()).toBeVisible();
    });
  });

  test.describe('Header navigation', () => {
    test('header navigation links are visible and clickable', async ({ page }) => {
      await page.goto('/');

      const header = page.locator('header');
      await expect(header).toBeVisible();

      // Logo or site name should be present in header
      await expect(header.locator('a img.logo, span.font-bold').first()).toBeVisible();

      // A desktop navigation link leads to its page
      const firstNavLink = page.locator('.desktop-nav .nav-link').first();
      await expect(firstNavLink).toBeVisible();
      await expect(firstNavLink).not.toHaveText(/^\s*$/);
      const href = await firstNavLink.getAttribute('href');
      expect(href).toMatch(/^\//);

      await firstNavLink.click();
      await expect(page).toHaveURL(new RegExp(`${escapeRegExp(href)}$`));
      await expect(page.locator('.dynamic-page')).toBeVisible();
    });
  });

  test.describe('Footer', () => {
    test('footer is visible with navigation items', async ({ page }) => {
      await page.goto('/');

      const footer = page.locator('footer');
      await expect(footer).toBeVisible();

      const firstLink = footer.locator('.footer-link').first();
      await expect(firstLink).toBeVisible();
      await expect(firstLink).not.toHaveText(/^\s*$/);
    });
  });
});
