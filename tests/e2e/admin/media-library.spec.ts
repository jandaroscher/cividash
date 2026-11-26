// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect, type Page } from '@playwright/test';
import { execSync } from 'child_process';
import { deflateSync } from 'zlib';

/**
 * E2E test for the media library: an editor uploads an image to the library,
 * picks it in a Text & Image block of a new page, and the public page shows it.
 *
 * Prerequisites:
 * - A running app at PLAYWRIGHT_BASE_URL (DDEV by default) with built assets
 * - Test data seeded via: ${E2E_ARTISAN_CMD:-ddev exec php artisan} e2e:seed-full --json
 */

let user: { email: string; password: string };

/** A small solid PNG, so the test needs no fixture file. */
function png(width: number, height: number): Buffer {
  const crcTable = Array.from({ length: 256 }, (_, n) => {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    return c >>> 0;
  });
  const crc = (buf: Buffer) => {
    let c = 0xffffffff;
    for (const byte of buf) c = crcTable[(c ^ byte) & 0xff] ^ (c >>> 8);
    return (c ^ 0xffffffff) >>> 0;
  };
  const chunk = (type: string, data: Buffer) => {
    const body = Buffer.concat([Buffer.from(type), data]);
    const out = Buffer.alloc(body.length + 8);
    out.writeUInt32BE(data.length, 0);
    body.copy(out, 4);
    out.writeUInt32BE(crc(body), body.length + 4);
    return out;
  };
  const header = Buffer.alloc(13);
  header.writeUInt32BE(width, 0);
  header.writeUInt32BE(height, 4);
  header[8] = 8;
  header[9] = 2;
  const row = Buffer.concat([Buffer.from([0]), Buffer.alloc(width * 3, 0x50)]);
  const pixels = Buffer.concat(Array.from({ length: height }, () => row));

  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk('IHDR', header),
    chunk('IDAT', deflateSync(pixels)),
    chunk('IEND', Buffer.alloc(0)),
  ]);
}

async function login(page: Page): Promise<string> {
  await page.goto('/admin/login');
  await page.locator('input[type="email"]').fill(user.email);
  await page.locator('input[type="password"]').fill(user.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.includes('/login'));

  // The panel URL starts with the current dashboard: /admin/{tenant}
  return new URL(page.url()).pathname.split('/').slice(0, 3).join('/');
}

test.describe('Media library', () => {
  test.beforeAll(() => {
    const artisanCmd = process.env.E2E_ARTISAN_CMD ?? 'ddev exec php artisan';
    const fixtures = JSON.parse(
      execSync(`${artisanCmd} e2e:seed-full --clean --json`, { encoding: 'utf-8', cwd: process.cwd() }).trim(),
    );
    user = fixtures.user;
  });

  test('an image uploaded to the library can be picked in a block and shows on the page', async ({ page }) => {
    test.setTimeout(90_000);
    const name = `lake-${Date.now()}`;
    const panel = await login(page);

    // Upload the image in the media library
    await page.goto(`${panel}/media/create`);
    await page.locator('input[type="file"]').setInputFiles({ name: `${name}.png`, mimeType: 'image/png', buffer: png(64, 48) });
    await expect(page.getByRole('alert').filter({ hasText: 'Upload abgeschlossen' })).toBeVisible();
    await page.getByLabel('Alternativer Text').fill('Ein See im Abendlicht');
    await page.getByRole('button', { name: 'Erstellen', exact: true }).click();
    await page.waitForURL(/\/media\/\d+\/edit/);
    const mediaUrl = page.url();
    await expect(page.getByText('Wird nirgends verwendet.')).toBeVisible();

    // Create a page with a Text & Image block and pick the image from the library
    await page.goto(`${panel}/pages/create`);
    await page.getByLabel('Titel').first().fill(`Seeblick ${name}`);
    await page.getByRole('button', { name: 'Zu Blöcke hinzufügen' }).click();
    await page.getByRole('button', { name: 'Text & Bild' }).click();
    const block = page.locator('.fi-fo-builder-item').first();
    await block.locator('[contenteditable="true"]').fill('Blick auf den See.');
    await block.getByRole('button', { name: 'Aus Mediathek wählen' }).click();

    const picker = page.locator('.fi-modal-window:visible');
    await picker.getByPlaceholder('Suchen').fill(name);
    await picker.getByAltText('Ein See im Abendlicht').click();
    await picker.getByRole('button', { name: 'Einfügen' }).click();
    await expect(picker).toBeHidden();

    // A dashboard without pages suggests a landing page, which has no slug
    await page.getByRole('combobox', { name: /^Layout/ }).selectOption('subpage');
    // The slug follows the title until it is set, so set it last
    await page.getByLabel('URL-Slug').fill(name);

    await page.getByRole('button', { name: 'Erstellen', exact: true }).click();
    await page.waitForURL(/\/pages\/\d+\/edit/);

    // The public page shows the image with the alt text kept in the library
    await page.goto(`/${name}`);
    const image = page.getByAltText('Ein See im Abendlicht');
    await expect(image).toBeVisible({ timeout: 15_000 });
    expect(await image.getAttribute('src')).toMatch(/\/storage\/(_resized\/\d+\/)?media\/\d+\/.+\.png$/);
    expect(await image.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);

    // The library now lists the page as a usage
    await page.goto(mediaUrl);
    await expect(page.getByRole('link', { name: `Seite: Seeblick ${name}` })).toBeVisible();
  });
});
