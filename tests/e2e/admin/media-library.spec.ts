// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect, type Page } from '@playwright/test';
import { artisan, login, pick, png, runId, seedMedia, upload, type MediaFixtures, type Uploaded } from './media-helpers';

/**
 * The media library itself: uploading, finding and describing files, using them on a
 * page, refusing to delete what is in use, and keeping each dashboard's files apart.
 */

let fixtures: MediaFixtures;

const image = (name: string) => ({ name: `${name}.png`, mimeType: 'image/png', buffer: png(64, 48) });

/** Creates a subpage with one Text & Image block that shows `media`, found in the picker by `search`. */
async function createPageWithImage(page: Page, panel: string, slug: string, media: Uploaded, search = slug): Promise<void> {
  await page.goto(`${panel}/pages/create`);
  await page.getByLabel('Titel').first().fill(`Seeblick ${slug}`);
  await page.getByRole('button', { name: 'Zu Blöcke hinzufügen' }).click();
  await page.getByRole('button', { name: 'Text & Bild' }).click();
  const block = page.locator('.fi-fo-builder-item').first();
  await block.locator('[contenteditable="true"]').fill('Blick auf den See.');
  await pick(page, block, search, media.file);
  // A dashboard without pages suggests a landing page, which has no slug
  await page.getByRole('combobox', { name: /^Layout/ }).selectOption('subpage');
  // The slug follows the title until it is set, so set it last
  await page.getByLabel('URL-Slug').fill(slug);
  await page.getByRole('button', { name: 'Erstellen', exact: true }).click();
  await page.waitForURL(/\/pages\/(\d+)\/edit/);
}

test.describe('Media library', () => {
  test.beforeAll(() => {
    fixtures = seedMedia();
  });

  test.afterAll(() => {
    artisan('e2e:seed-media --clean');
  });

  test('an uploaded image is found, described in the library and shown with that description on a page', async ({ page, request }) => {
    test.setTimeout(120_000);
    const name = runId('lake');
    const title = `Abendstimmung am See ${name}`;
    const panel = await login(page, fixtures);

    // Upload: the file lands in the dashboard's own folder
    const media = await upload(page, panel, image(name));
    expect(media.file).not.toContain(name);
    await expect(page.getByText(new RegExp(`^/storage/media/${fixtures.defaultTenant}/`))).toBeVisible();
    await expect(page.getByText('Wird nirgends verwendet.')).toBeVisible();

    // Search finds it in the library
    await page.goto(`${panel}/media`);
    await page.getByPlaceholder('Suche').fill(name);
    await expect(page.locator(`main img[src*="${media.file}"]`)).toHaveCount(1);

    // Describe it once in the library
    await page.goto(media.url);
    await page.getByLabel('Alternativer Text').fill('Ein See im Abendlicht');
    await page.getByLabel('Titel', { exact: true }).fill(title);
    await page.getByLabel('Copyright').fill('© Stadtwerke');
    await page.getByRole('button', { name: 'Speichern', exact: true }).first().click();
    await expect(page.getByText('Gespeichert')).toBeVisible();

    // Use it in a block without own texts
    await createPageWithImage(page, panel, name, media, title);
    const pageId = page.url().match(/\/pages\/(\d+)\/edit/)![1];

    // The public API and page fall back to the library's description
    const props = (await request.get(`/api/v1/content/pages/${pageId}?locale=de`).then((r) => r.json())).blocks[0].props;
    expect(props.image).toBe(`media/${fixtures.defaultTenant}/${media.file}`);
    expect(props.image_alt).toBe('Ein See im Abendlicht');
    expect(props.image_title).toBe(title);
    expect(props.image_copyright).toBe('© Stadtwerke');

    await page.goto(`/${name}`);
    const shown = page.getByAltText('Ein See im Abendlicht');
    await expect(shown).toBeVisible({ timeout: 15_000 });
    expect(await shown.getAttribute('src')).toMatch(/\/storage\/(_resized\/\d+\/)?media\/\d+\/.+\.png$/);
    expect(await shown.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);

    // The library lists the page as a usage
    await page.goto(media.url);
    await expect(page.getByRole('link', { name: `Seite: Seeblick ${name}` })).toBeVisible();
  });

  test('an image in use cannot be deleted, an unused one can', async ({ page }) => {
    test.setTimeout(120_000);
    const used = runId('used');
    const unused = runId('unused');
    const panel = await login(page, fixtures);

    const usedMedia = await upload(page, panel, image(used));
    await createPageWithImage(page, panel, used, usedMedia);
    const unusedMedia = await upload(page, panel, image(unused));

    // Refused: the notice names the reason and the item stays
    await page.goto(usedMedia.url);
    await page.getByRole('button', { name: 'Löschen' }).first().click();
    await page.locator('.fi-modal-window:visible').getByRole('button', { name: 'Löschen' }).click();
    await expect(page.getByText('Die Datei wird noch verwendet und kann nicht gelöscht werden.')).toBeVisible();
    await page.goto(usedMedia.url);
    await expect(page.getByRole('link', { name: `Seite: Seeblick ${used}` })).toBeVisible();

    // Allowed: the unused item is gone
    await page.goto(unusedMedia.url);
    await page.getByRole('button', { name: 'Löschen' }).first().click();
    await page.locator('.fi-modal-window:visible').getByRole('button', { name: 'Löschen' }).click();
    await page.waitForURL(/\/media$/);
    expect((await page.goto(unusedMedia.url))?.status()).toBe(404);
  });

  test('another dashboard sees none of these files, neither in its library nor in the picker', async ({ page }) => {
    test.setTimeout(120_000);
    const name = runId('private');
    const panel = await login(page, fixtures);
    const media = await upload(page, panel, image(name));
    // In its own dashboard the search finds it
    await page.goto(`${panel}/media`);
    await page.getByPlaceholder('Suche').fill(name);
    await expect(page.locator(`main img[src*="${media.file}"]`)).toHaveCount(1);

    const other = `/admin/${fixtures.otherTenant}`;
    await page.goto(`${other}/media`);
    await page.getByPlaceholder('Suche').fill(name);
    await expect(page.getByText('Keine Mediathek')).toBeVisible();
    await expect(page.locator(`main img[src*="${media.file}"]`)).toHaveCount(0);

    await page.goto(`${other}/pages/create`);
    await page.getByRole('button', { name: 'Zu Blöcke hinzufügen' }).click();
    await page.getByRole('button', { name: 'Text & Bild' }).click();
    await page.locator('.fi-fo-builder-item').first().getByRole('button', { name: 'Aus Mediathek wählen' }).click();
    const picker = page.locator('.fi-modal-window:visible');
    await expect(picker.getByPlaceholder('Suchen')).toBeVisible();
    await picker.getByPlaceholder('Suchen').fill(name);
    await expect(picker.getByText('Keine Dateien in der Mediathek gefunden.')).toBeVisible();
    await expect(picker.locator(`img[src*="${media.file}"]`)).toHaveCount(0);

    // Opening the item's address in the other dashboard finds nothing
    expect((await page.goto(`${other}/media/${media.id}/edit`))?.status()).toBe(404);
  });
});
