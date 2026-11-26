// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect } from '@playwright/test';
import { resetApiThrottle } from './helpers';

/**
 * E2E Tests for the public API rate limit
 *
 * Verifies that:
 * - Many page views from one IP within a minute (a shared office network)
 *   still render the tile grid
 * - A rejected tile request shows an error instead of an empty grid
 *
 * Prerequisites: see dashboard-navigation.spec.ts.
 */

test.beforeEach(resetApiThrottle);

test.describe('Public API rate limit', () => {
  test('20 page views in a row from one IP all render the tile grid', async ({ page }) => {
    const statuses: number[] = [];
    page.on('response', (response) => {
      if (response.url().includes('/api/v1/')) statuses.push(response.status());
    });

    for (let view = 0; view < 20; view++) {
      await page.goto('/tiles');
      await expect(page.locator('.shadow-card').first()).toBeVisible();
    }

    // Far more requests than the former limit of 60 per minute, none rejected
    expect(statuses.length).toBeGreaterThan(100);
    expect(statuses.filter((status) => status === 429)).toEqual([]);
  });

  test('a rejected tile request shows an error instead of an empty grid', async ({ page }) => {
    await page.route(/\/api\/v1\/tiles\?/, (route) =>
      route.fulfill({
        status: 429,
        contentType: 'application/json',
        headers: { 'Retry-After': '60' },
        body: JSON.stringify({ message: 'Too Many Attempts.' }),
      }),
    );

    await page.goto('/tiles');

    await expect(page.getByRole('alert')).toContainText(/Fehler beim Laden|Error loading tiles/);
    await expect(page.locator('.shadow-card')).toHaveCount(0);
  });
});
