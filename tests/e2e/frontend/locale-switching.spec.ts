// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect, type Page } from '@playwright/test';
import { resetApiThrottle } from './helpers';

/**
 * E2E Tests for Locale Switching
 *
 * Verifies that:
 * - Default page loads in German (DE)
 * - Language switcher is visible in header
 * - Switching to EN changes URL to /en/...
 * - Content changes to English (check header navigation text)
 * - Switching back to DE removes /en/ prefix
 * - The app requests API data with the matching ?locale= parameter
 *
 * Prerequisites:
 * - A running app at PLAYWRIGHT_BASE_URL, pointing at the demo-city tenant
 * - The demo-city tenant with English translation active and its "Kontakt"/"Contact"
 *   header navigation item (dashboard:reset, see docs/testing/README.md)
 */

/**
 * Open a home page and wait until the router has resolved it. The app mounts
 * (header included) before its first navigation finishes, and a language
 * switch clicked in that window is overridden by that navigation.
 */
async function openHome(page: Page, path: '/' | '/en'): Promise<void> {
  await page.goto(path);
  await expect(page.locator('.home-page .page-content')).toBeVisible();
}

test.beforeEach(resetApiThrottle);

test.describe('Locale Switching', () => {
  test.describe('Default locale', () => {
    test('default page loads in German (DE)', async ({ page }) => {
      await page.goto('/');

      expect(page.url()).not.toMatch(/\/en(\/|$)/);

      await expect(page.locator('header')).toBeVisible();
      const nav = page.locator('.desktop-nav');
      await expect(nav.getByRole('link', { name: 'Kontakt' })).toHaveAttribute('href', '/kontakt');
    });
  });

  test.describe('Language switcher', () => {
    test('language switcher is visible in header', async ({ page }) => {
      await page.goto('/');

      const languageSwitcher = page.locator('.language-switcher-desktop');
      await expect(languageSwitcher.getByRole('button', { name: 'DE' })).toBeVisible();
      await expect(languageSwitcher.getByRole('button', { name: 'EN' })).toBeVisible();
    });

    test('switching to EN changes URL to /en/...', async ({ page }) => {
      await openHome(page, '/');

      await page.locator('.language-switcher-desktop').getByRole('button', { name: 'EN' }).click();

      await expect(page).toHaveURL(/\/en(\/|$)/);
    });

    test('content changes to English after switching locale', async ({ page }) => {
      await openHome(page, '/');
      const nav = page.locator('.desktop-nav');
      await expect(nav.getByRole('link', { name: 'Kontakt' })).toBeVisible();

      await page.locator('.language-switcher-desktop').getByRole('button', { name: 'EN' }).click();

      // The header navigation is now English and links to English pages
      await expect(nav.getByRole('link', { name: 'Contact' })).toHaveAttribute('href', '/en/contact');
      await expect(nav.getByRole('link', { name: 'Kontakt' })).toHaveCount(0);
    });

    test('switching back to DE removes /en/ prefix', async ({ page }) => {
      await openHome(page, '/en');
      await expect(page).toHaveURL(/\/en$/);
      const nav = page.locator('.desktop-nav');
      await expect(nav.getByRole('link', { name: 'Contact' })).toBeVisible();

      await page.locator('.language-switcher-desktop').getByRole('button', { name: 'DE' }).click();

      await expect(page).not.toHaveURL(/\/en(\/|$)/);
      await expect(nav.getByRole('link', { name: 'Kontakt' })).toBeVisible();
    });
  });

  test.describe('API locale parameter', () => {
    test('API calls use correct ?locale= parameter', async ({ page }) => {
      for (const [path, locale] of [['/tiles', 'de'], ['/en/tiles', 'en']]) {
        const tilesResponse = page.waitForResponse((response) => {
          const url = new URL(response.url());
          return url.pathname === '/api/v1/tiles' && url.searchParams.get('locale') === locale;
        });

        await page.goto(path);

        const response = await tilesResponse;
        expect(response.ok()).toBeTruthy();
        expect((await response.json()).data.length).toBeGreaterThan(0);
        await expect(page.locator('.shadow-card').first()).toBeVisible();
      }
    });
  });
});
