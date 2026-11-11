// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test as base, expect } from '@playwright/test';

/**
 * E2E tests for the JSON/CSV export feature.
 *
 * Covers all three frontend entry points:
 *  - Card grid: direct CSV icon on a tile card
 *  - Overlay: "Daten herunterladen" dialog for a single tile
 *  - Footer: global catalog export dialog
 *
 * Prerequisites:
 *  - the demo-city tenant seeded with content. It is resolved by its
 *    domain, the demo-city. subdomain of the PLAYWRIGHT_BASE_URL host. Without
 *    DDEV, use a localhost base URL: demo-city.localhost resolves locally.
 */

const test = base.extend({
    baseURL: async ({ baseURL }, use) => {
        const url = new URL(baseURL!);
        url.hostname = `demo-city.${url.hostname}`;
        await use(url.origin);
    },
});

test.describe('Exports', () => {
    test('tile card offers a direct CSV download', async ({ page }) => {
        await page.goto('/tiles');
        await page.waitForLoadState('networkidle');

        const firstCard = page.locator('.shadow-card').first();
        await expect(firstCard).toBeVisible({ timeout: 10000 });

        const exportButton = firstCard.getByRole('button', { name: /Daten herunterladen|Download data/i }).first();
        await expect(exportButton).toBeVisible();

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            exportButton.click(),
        ]);

        expect(download.suggestedFilename()).toMatch(/\.csv$/);
    });

    test('tile overlay opens the export dialog and downloads JSON', async ({ page }) => {
        await page.goto('/tiles');
        await page.waitForLoadState('networkidle');

        const firstCard = page.locator('.shadow-card').first();
        await expect(firstCard).toBeVisible({ timeout: 10000 });
        // Info button opens the overlay
        await firstCard.locator('button[aria-label="Info"]').click();

        const dialogTrigger = page.getByRole('button', { name: /Daten herunterladen|Download data/ }).first();
        await expect(dialogTrigger).toBeVisible();
        await dialogTrigger.click();

        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();

        // Select JSON (default) and download
        await dialog.getByRole('radio', { name: 'JSON' }).check();

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            dialog.getByRole('button', { name: /^Herunterladen$|^Download$/ }).click(),
        ]);

        expect(download.suggestedFilename()).toMatch(/tile-.*\.json$/);
    });

    test('footer exposes a global catalog export', async ({ page }) => {
        await page.goto('/tiles');
        await page.waitForLoadState('networkidle');

        const catalogButton = page.getByRole('button', { name: /Alle Kacheln exportieren|Export all tiles/ });
        await expect(catalogButton).toBeVisible();
        await catalogButton.scrollIntoViewIfNeeded();
        await catalogButton.click();

        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();

        // Switch to CSV to exercise the Excel path
        await dialog.getByRole('radio', { name: /CSV/ }).check();

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            dialog.getByRole('button', { name: /^Herunterladen$|^Download$/ }).click(),
        ]);

        expect(download.suggestedFilename()).toMatch(/catalog-.*\.csv$/);
    });
});
