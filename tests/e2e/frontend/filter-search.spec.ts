// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect, type Page } from '@playwright/test';
import { fetchApi, resetApiThrottle } from './helpers';

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
 * - A running app at PLAYWRIGHT_BASE_URL (DDEV by default)
 * - Test data seeded via: ${E2E_ARTISAN_CMD:-ddev exec php artisan} e2e:seed-full --json
 * - Tiles for the demo-city tenant: dashboard:reset (see docs/testing/README.md;
 *   it deletes tenant content)
 */

// Cards hidden by a filter or by the grid's re-layout are not :visible.
const VISIBLE_TILES = '.shadow-card:visible';

type Tile = { title: string; categories: { id: number; group?: { key: string } }[] };
type FilterGroup = { key: string; title: string; items: { id: number; key: string | null; title: string }[] };

/** Open the tiles page and wait until every tile of the tenant is shown. */
async function openTilesPage(page: Page): Promise<Tile[]> {
  await page.goto('/tiles');
  const tiles = await fetchApi<Tile[]>(page, '/api/v1/tiles?locale=de');
  expect(tiles.length).toBeGreaterThan(1);
  await expect(page.locator(VISIBLE_TILES)).toHaveCount(tiles.length);
  return tiles;
}

test.beforeEach(resetApiThrottle);

test.describe('Filter and Search', () => {
  test.describe('Filter component', () => {
    test('filter component is visible on tiles page', async ({ page }) => {
      await openTilesPage(page);

      // Filter groups are tabs; the first one is selected by default
      const tabs = page.getByRole('tab');
      await expect(tabs.first()).toBeVisible();
      await expect(tabs.first()).toHaveAttribute('aria-selected', 'true');
      await expect(page.getByRole('tabpanel')).toBeVisible();

      await expect(page.getByRole('textbox', { name: /^(Kacheln durchsuchen|Search tiles)$/ })).toBeVisible();
    });

    test('clicking a category filter button updates the displayed tiles', async ({ page }) => {
      const tiles = await openTilesPage(page);

      // Pick a category that some, but not all, tiles carry
      const { groups } = await fetchApi<{ groups: FilterGroup[] }>(page, '/api/v1/filters?locale=de');
      const choice = groups
        .flatMap((group) => group.items.map((item) => ({
          group,
          item,
          count: tiles.filter((tile) => tile.categories.some((c) => c.group?.key === group.key && c.id === item.id)).length,
        })))
        .find(({ count }) => count > 0 && count < tiles.length);
      expect(choice, 'a category carried by some but not all tiles').toBeDefined();
      const { group, item, count } = choice!;

      const tab = page.getByRole('tab', { name: group.title, exact: true });
      await tab.click();
      await expect(tab).toHaveAttribute('aria-selected', 'true');

      const category = page.getByRole('tabpanel').getByRole('button', { name: item.title, exact: true });
      await category.click();
      await expect(category).toHaveAttribute('aria-pressed', 'true');
      await expect(page).toHaveURL(new RegExp(`[?&]filterKey=${item.key ?? item.id}(&|$)`));

      // Only the tiles of that category remain
      await expect(page.locator(VISIBLE_TILES)).toHaveCount(count);
    });
  });

  test.describe('Search functionality', () => {
    test('search input accepts text and filters tiles by title', async ({ page }) => {
      const tiles = await openTilesPage(page);
      const title = tiles[0].title;

      await page.getByRole('textbox', { name: /^(Kacheln durchsuchen|Search tiles)$/ }).fill(title);

      await expect.poll(() => page.locator(VISIBLE_TILES).count()).toBeLessThan(tiles.length);
      await expect(page.locator(VISIBLE_TILES).first()).toContainText(title);

      // A term that matches nothing leaves an empty grid with a message
      await page.getByRole('textbox', { name: /^(Kacheln durchsuchen|Search tiles)$/ }).fill('xyz-nonexistent');
      await expect(page.locator(VISIBLE_TILES)).toHaveCount(0);
      await expect(page.getByText(/Keine Kacheln gefunden für Suche|No tiles found for search/)).toBeVisible();
    });

    test('clearing search shows all tiles again', async ({ page }) => {
      const tiles = await openTilesPage(page);

      const searchInput = page.getByRole('textbox', { name: /^(Kacheln durchsuchen|Search tiles)$/ });
      await searchInput.fill('xyz-nonexistent');
      await expect(page.locator(VISIBLE_TILES)).toHaveCount(0);

      await page.getByRole('button', { name: /^(Suche leeren|Clear search)$/ }).click();

      await expect(searchInput).toHaveValue('');
      await expect(page).not.toHaveURL(/[?&]search=/);
      await expect(page.locator(VISIBLE_TILES)).toHaveCount(tiles.length);
    });

    test('filter state persists in URL query parameters', async ({ page }) => {
      const tiles = await openTilesPage(page);
      const title = tiles[0].title;

      const searchInput = page.getByRole('textbox', { name: /^(Kacheln durchsuchen|Search tiles)$/ });
      await searchInput.fill(title);
      await expect(page).toHaveURL(new RegExp(`[?&]search=${encodeURIComponent(title).replace(/%20/g, '(\\+|%20)')}`));
      await expect.poll(() => page.locator(VISIBLE_TILES).count()).toBeLessThan(tiles.length);
      const filteredCount = await page.locator(VISIBLE_TILES).count();

      // After a reload the search is restored from the URL
      await page.reload();

      await expect(searchInput).toHaveValue(title);
      await expect(page.locator(VISIBLE_TILES)).toHaveCount(filteredCount);
      await expect(page.locator(VISIBLE_TILES).first()).toContainText(title);
    });
  });
});
