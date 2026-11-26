// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect, type Page } from '@playwright/test';
import { fetchApi, resetApiThrottle } from './helpers';

/**
 * E2E Tests for Branding Configuration
 *
 * Verifies that:
 * - CSS custom properties are set on document root
 * - Header background color matches branding config
 * - Footer background color matches branding config
 * - Font family is applied from branding
 *
 * Prerequisites:
 * - A running app at PLAYWRIGHT_BASE_URL (DDEV by default)
 * - Test data seeded via: ${E2E_ARTISAN_CMD:-ddev exec php artisan} e2e:seed-full --json
 * - Branding for the demo-city tenant: dashboard:reset (see docs/testing/README.md)
 */

type Branding = {
  primary_color: string;
  accent_color: string | null;
  header_background_color: string;
  footer_background_color: string;
};

async function openHomeWithBranding(page: Page): Promise<Branding> {
  await page.goto('/');
  return fetchApi<Branding>(page, '/api/v1/config/branding');
}

/** '#E8EEF3' -> 'rgb(232, 238, 243)', the form computed styles report. */
function hexToRgb(hex: string): string {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16));
  return `rgb(${r}, ${g}, ${b})`;
}

function rootProperty(page: Page, name: string): Promise<string> {
  return page.evaluate((n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(), name);
}

test.beforeEach(resetApiThrottle);

test.describe('Branding', () => {
  test.describe('CSS custom properties', () => {
    test('CSS custom properties are set on document root', async ({ page }) => {
      const branding = await openHomeWithBranding(page);

      await expect.poll(() => rootProperty(page, '--primary-color')).toBe(branding.primary_color);
      await expect.poll(() => rootProperty(page, '--accent-color')).toBe(branding.accent_color ?? branding.primary_color);
    });
  });

  test.describe('Header branding', () => {
    test('header background color matches branding config', async ({ page }) => {
      const branding = await openHomeWithBranding(page);

      await expect(page.locator('header')).toHaveCSS('background-color', hexToRgb(branding.header_background_color));
    });
  });

  test.describe('Footer branding', () => {
    test('footer background color matches branding config', async ({ page }) => {
      const branding = await openHomeWithBranding(page);

      // The footer's bottom area (navigation links, social icons) carries the colour
      const footerBottom = page.locator('footer > div').filter({ has: page.locator('.footer-link') });
      await expect(footerBottom).toHaveCSS('background-color', hexToRgb(branding.footer_background_color));
    });
  });

  test.describe('Typography', () => {
    test('font family is applied from branding', async ({ page }) => {
      await page.goto('/');

      // The branding store sets --font-family; the body font must come from it
      await expect.poll(() => rootProperty(page, '--font-family')).not.toBe('');
      const fontFamily = await rootProperty(page, '--font-family');
      const firstFont = fontFamily.split(',')[0].replace(/['"]/g, '').trim();
      await expect.poll(() => page.evaluate(() => getComputedStyle(document.body).fontFamily)).toContain(firstFont);
    });
  });
});
