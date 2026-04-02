import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const BASE_URL = 'http://open-source-dashboard.ddev.site';

test.describe('Accessibility Audit', () => {
  test.describe('Public pages', () => {
    test('home page has no critical a11y violations', async ({ page }) => {
      await page.goto(BASE_URL);
      await page.waitForLoadState('networkidle');

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .disableRules(['color-contrast']) // May fail with custom branding colors
        .analyze();

      const critical = results.violations.filter(v =>
        v.impact === 'critical' || v.impact === 'serious'
      );

      expect(critical).toEqual([]);
    });

    test('tiles page has no critical a11y violations', async ({ page }) => {
      await page.goto(`${BASE_URL}/tiles`);
      await page.waitForLoadState('networkidle');

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .disableRules(['color-contrast'])
        .analyze();

      const critical = results.violations.filter(v =>
        v.impact === 'critical' || v.impact === 'serious'
      );

      expect(critical).toEqual([]);
    });
  });

  test.describe('Landmarks and structure', () => {
    test('page has proper landmark structure', async ({ page }) => {
      await page.goto(BASE_URL);
      await page.waitForLoadState('networkidle');

      // Check for main landmark
      const main = page.locator('main');
      await expect(main).toBeVisible();

      // Check for header
      const header = page.locator('header');
      await expect(header).toBeVisible();

      // Check for footer
      const footer = page.locator('footer');
      await expect(footer).toBeVisible();
    });

    test('images have alt attributes', async ({ page }) => {
      await page.goto(BASE_URL);
      await page.waitForLoadState('networkidle');

      const imagesWithoutAlt = await page.locator('img:not([alt])').count();
      expect(imagesWithoutAlt).toBe(0);
    });
  });

  test.describe('Keyboard navigation', () => {
    test('interactive elements are focusable via Tab', async ({ page }) => {
      await page.goto(BASE_URL);
      await page.waitForLoadState('networkidle');

      // Press Tab and check that focus moves to an interactive element
      await page.keyboard.press('Tab');

      const focusedTag = await page.evaluate(() => {
        const el = document.activeElement;
        return el ? el.tagName.toLowerCase() : null;
      });

      // Focus should be on a link, button, or input — not stuck on body
      expect(['a', 'button', 'input', 'select', 'textarea']).toContain(focusedTag);
    });

    test('focus indicators are visible', async ({ page }) => {
      await page.goto(BASE_URL);
      await page.waitForLoadState('networkidle');

      // Tab to first interactive element
      await page.keyboard.press('Tab');

      // Check that the focused element has a visible outline/ring
      const hasVisibleFocus = await page.evaluate(() => {
        const el = document.activeElement;
        if (!el) return false;
        const style = window.getComputedStyle(el);
        const outline = style.outline;
        const boxShadow = style.boxShadow;
        // Has either an outline or a box-shadow (ring) for focus indication
        return (outline && outline !== 'none' && !outline.includes('0px')) ||
               (boxShadow && boxShadow !== 'none');
      });

      expect(hasVisibleFocus).toBe(true);
    });
  });

  test.describe('Admin panel', () => {
    test('admin login page has no critical a11y violations', async ({ page }) => {
      await page.goto(`${BASE_URL}/admin/login`);
      await page.waitForLoadState('networkidle');

      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .analyze();

      const critical = results.violations.filter(v =>
        v.impact === 'critical' || v.impact === 'serious'
      );

      expect(critical).toEqual([]);
    });
  });
});
