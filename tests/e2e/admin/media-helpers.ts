// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { expect, type Locator, type Page } from '@playwright/test';
import { execSync } from 'child_process';
import { deflateSync } from 'zlib';

/**
 * Shared steps for the media library E2E specs.
 *
 * Prerequisites:
 * - A running app at PLAYWRIGHT_BASE_URL (DDEV by default) with built assets
 * - Test data seeded via: ${E2E_ARTISAN_CMD:-ddev exec php artisan} e2e:seed-full and e2e:seed-media
 */

export const user = { email: 'e2e-admin@example.com', password: 'e2e-admin-password' };
/** Theme and site settings are for administrators only. */
export const admin = { email: 'e2e-superadmin@example.com', password: 'e2e-superadmin-password' };

export function artisan(command: string): string {
  const artisanCmd = process.env.E2E_ARTISAN_CMD ?? 'ddev exec php artisan';

  return execSync(`${artisanCmd} ${command}`, { encoding: 'utf-8', cwd: process.cwd() }).trim();
}

export type MediaFixtures = {
  defaultTenant: number;
  otherTenant: number;
  categoryGroup: string;
  legacy?: {
    run: string;
    files: Record<'harbour' | 'square' | 'water' | 'seo' | 'favicon' | 'logo', string>;
    page: number;
    slugs: { de: string; en: string };
    tile: number;
  };
};

/** Seeds the E2E users and dashboards, then the media fixtures. */
export function seedMedia(options = ''): MediaFixtures {
  artisan('e2e:seed-full --clean --json');
  const output = artisan(`e2e:seed-media ${options}`);

  return JSON.parse(output.slice(output.indexOf('{')));
}

/** A small solid PNG, so the test needs no fixture file. */
export function png(width: number, height: number): Buffer {
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


export async function login(page: Page, tenant: number, as = user): Promise<string> {
  await page.goto('/admin/login');
  // A click before Livewire has started on the page is lost
  await page.waitForLoadState('networkidle');
  await page.locator('input[type="email"]').fill(as.email);
  await page.locator('input[type="password"]').fill(as.password);
  await page.locator('button[type="submit"]').click();
  await page.waitForURL((url) => !url.pathname.includes('/login'));

  return `/admin/${tenant}`;
}

export type Uploaded = { url: string; id: string; file: string };

/**
 * Uploads a file on the "create media" page. The library stores it under a generated
 * name and keeps the original name as its title, so `file` is the stored name.
 */
export async function upload(
  page: Page,
  panel: string,
  file: { name: string; mimeType: string; buffer: Buffer },
  meta: { alt?: string; title?: string; copyright?: string } = {},
): Promise<Uploaded> {
  await page.goto(`${panel}/media/create`);
  await page.locator('input[type="file"]').setInputFiles(file);
  await expect(page.getByRole('alert').filter({ hasText: 'Upload abgeschlossen' })).toBeVisible();
  if (meta.alt) await page.getByLabel('Alternativer Text').fill(meta.alt);
  if (meta.title) await page.getByLabel('Titel', { exact: true }).fill(meta.title);
  if (meta.copyright) await page.getByLabel('Copyright').fill(meta.copyright);
  await page.getByRole('button', { name: 'Erstellen', exact: true }).click();
  await page.waitForURL(/\/media\/\d+\/edit/);
  const stored = await page.getByText(/^\/storage\/media\/\d+\/.+/).first().innerText();

  return { url: page.url(), id: page.url().match(/\/media\/(\d+)\/edit/)![1], file: stored.split('/').pop()! };
}

/** Opens the library picker of a field, searches for `search` and inserts the item stored as `file`. */
export async function pick(page: Page, field: Locator, search: string, file: string): Promise<void> {
  await field.getByRole('button', { name: 'Aus Mediathek wählen' }).first().click();
  const picker = page.locator('.fi-modal-window:visible');
  await picker.getByPlaceholder('Suchen').fill(search);
  await picker.locator(`li[wire\\:key^="media-"] button[x-on\\:click*="${file}"]`).first().click();
  await picker.getByRole('button', { name: 'Einfügen' }).click();
  await expect(picker).toBeHidden();
}

/** A minimal one-page PDF. */
export function pdf(): Buffer {
  return Buffer.from(
    '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n' +
      '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n',
  );
}
