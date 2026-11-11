// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test as base, expect } from '@playwright/test';

/**
 * E2E tests for the JSON/CSV export feature.
 *
 * Covers both frontend entry points:
 *  - Tile detail: "Daten herunterladen" dialog for a single tile
 *  - Home page: catalog export of the card grid
 *
 * Prerequisites:
 *  - the demo-city tenant seeded with tiles and pages. It is resolved by its
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
    test('tile detail opens the export dialog and downloads JSON', async ({ page }) => {
        // Fetch in the browser: Node cannot resolve *.localhost tenant hosts.
        await page.goto('/tiles');
        const slug = await page.evaluate(async () => {
            const response = await fetch('/api/v1/tiles', { headers: { Accept: 'application/json' } });
            return (await response.json()).data[0].slug.de;
        });
        await page.goto(`/tiles/${slug}`);

        await page.getByRole('button', { name: /^(Daten herunterladen|Download data)$/ }).click();

        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        // The radio inputs are visually hidden; their labels take the click.
        await dialog.getByText('JSON', { exact: true }).click();
        await expect(dialog.getByRole('radio', { name: 'JSON' })).toBeChecked();

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            dialog.getByRole('button', { name: /^Herunterladen$|^Download$/ }).click(),
        ]);

        expect(download.suggestedFilename()).toMatch(/tile-.*\.json$/);
    });

    test('home page card grid offers a catalog export', async ({ page }) => {
        await page.goto('/');

        await page.getByRole('button', { name: /Alle Kacheln exportieren|Export all tiles/ }).click();

        const dialog = page.getByRole('dialog');
        await expect(dialog).toBeVisible();
        await dialog.getByText(/^CSV/).click();
        await expect(dialog.getByRole('radio', { name: /CSV/ })).toBeChecked();

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            dialog.getByRole('button', { name: /^Herunterladen$|^Download$/ }).click(),
        ]);

        expect(download.suggestedFilename()).toMatch(/catalog-.*\.csv$/);
    });
});
