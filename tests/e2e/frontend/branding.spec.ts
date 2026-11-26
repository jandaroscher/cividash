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
 * - A running app at PLAYWRIGHT_BASE_URL, pointing at the demo-city tenant
 * - Demo data seeded via dashboard:reset (see docs/testing/README.md)
 */

type Branding = {
  primary_color: string;
  accent_color: string | null;
  header_background_color: string;
  footer_background_color: string;
  typography_font_family: string;
  font_family_body: string | null;
};

async function openHomeWithBranding(page: Page): Promise<Branding> {
  await page.goto('/');
  return fetchApi<Branding>(page, '/api/v1/config/branding');
}

/** Any CSS colour -> the form computed styles report, e.g. 'rgb(232, 238, 243)'. */
function toComputedColor(page: Page, color: string): Promise<string> {
  return page.evaluate((c) => {
    const probe = document.createElement('span');
    probe.style.color = c;
    document.body.append(probe);
    const computed = getComputedStyle(probe).color;
    probe.remove();
    return computed;
  }, color);
}

/** "'Source Sans 3', Arial" -> 'Source Sans 3' */
function firstFamily(stack: string): string {
  return stack.split(',')[0].replace(/['"]/g, '').trim();
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

      await expect(page.locator('header')).toHaveCSS('background-color', await toComputedColor(page, branding.header_background_color));
    });
  });

  test.describe('Footer branding', () => {
    test('footer background color matches branding config', async ({ page }) => {
      const branding = await openHomeWithBranding(page);

      // The footer's bottom area (navigation links, social icons) carries the colour
      const footerBottom = page.locator('footer > div').filter({ has: page.locator('.footer-link') });
      await expect(footerBottom).toHaveCSS('background-color', await toComputedColor(page, branding.footer_background_color));
    });
  });

  test.describe('Typography', () => {
    test('body text uses the body font from branding', async ({ page }) => {
      const branding = await openHomeWithBranding(page);
      const expected = firstFamily(branding.font_family_body || branding.typography_font_family);

      await expect
        .poll(() => page.evaluate(() => getComputedStyle(document.body).fontFamily).then(firstFamily))
        .toBe(expected);
    });
  });
});
