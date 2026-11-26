// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect, type APIRequestContext, type Locator, type Page } from '@playwright/test';
import { admin, artisan, login, pdf, pick, png, seedMedia, upload, type MediaFixtures, type Uploaded } from './media-helpers';

/**
 * Every kind of field that takes a file picks it from the media library, and the
 * public API then delivers the picked file.
 */

let fixtures: MediaFixtures;

async function uploadImage(page: Page, panel: string, title: string): Promise<Uploaded> {
  return upload(page, panel, { name: `${title}.png`, mimeType: 'image/png', buffer: png(96, 64) });
}

/** The field whose label starts with `label`, so its own picker button can be clicked. */
function field(scope: Page | Locator, label: string, which: 'first' | 'last' = 'first') {
  return scope
    .locator('label.fi-fo-field-label', { hasText: new RegExp(`^\\s*${label}`) })
    [which]()
    .locator('xpath=ancestor::div[@data-field-wrapper][1]');
}

/** Opens a collapsed entry inside `scope`, if there is one. */
async function expand(scope: Locator): Promise<void> {
  const toggle = scope
    .locator(
      'xpath=descendant-or-self::*[contains(concat(" ", @class, " "), " fi-collapsed ")]//li[contains(@class, "fi-fo-repeater-item-header-collapsible-actions")]',
    )
    .filter({ visible: true });
  if (await toggle.count()) await toggle.first().click();
}

async function json(request: APIRequestContext, url: string) {
  const response = await request.get(url);
  expect(response.ok(), url).toBeTruthy();

  return response.json();
}

test.describe('Media library in content fields', () => {
  test.beforeAll(() => {
    fixtures = seedMedia();
  });

  test.afterAll(() => {
    artisan('e2e:seed-media --reset-branding');
  });

  test('page blocks: Text & Image, a slider item and a download', async ({ page, request }) => {
    test.setTimeout(180_000);
    const run = `blocks-${Date.now()}`;
    const panel = await login(page, fixtures.defaultTenant);
    const photo = await uploadImage(page, panel, `${run}-photo`);
    const slide = await uploadImage(page, panel, `${run}-slide`);
    const report = await upload(page, panel, { name: `${run}-report.pdf`, mimeType: 'application/pdf', buffer: pdf() });

    await page.goto(`${panel}/pages/create`);
    await page.getByLabel('Titel').first().fill(`Bericht ${run}`);
    const blocks = page.locator('.fi-fo-builder-item');

    await page.getByRole('button', { name: 'Zu Blöcke hinzufügen' }).last().click();
    await page.getByRole('button', { name: 'Text & Bild' }).click();
    await blocks.nth(0).locator('[contenteditable="true"]').fill('Der Jahresbericht.');
    await pick(page, blocks.nth(0), `${run}-photo`, photo.file);

    await page.getByRole('button', { name: 'Zu Blöcke hinzufügen' }).last().click();
    await page.getByRole('button', { name: 'Slider', exact: true }).click();
    await expect(blocks).toHaveCount(2);
    await expand(blocks.last());
    // New Slider and Downloads blocks start with one empty entry
    await pick(page, blocks.last(), `${run}-slide`, slide.file);

    await page.getByRole('button', { name: 'Zu Blöcke hinzufügen' }).last().click();
    await page.getByRole('button', { name: 'Downloads', exact: true }).click();
    await expect(blocks).toHaveCount(3);
    await expand(blocks.last());
    await pick(page, blocks.last(), `${run}-report`, report.file);

    await page.getByRole('combobox', { name: /^Layout/ }).selectOption('subpage');
    await page.getByLabel('URL-Slug').fill(run);
    await page.getByRole('button', { name: 'Erstellen', exact: true }).click();
    await page.waitForURL(/\/pages\/\d+\/edit/);
    const pageId = page.url().match(/\/pages\/(\d+)\/edit/)![1];

    const props = (await json(request, `/api/v1/content/pages/${pageId}?locale=de`)).blocks.map((b: { props: unknown }) => b.props);
    const folder = `media/${fixtures.defaultTenant}`;
    expect(props[0].image).toBe(`${folder}/${photo.file}`);
    expect(props[1].items[0].image).toBe(`${folder}/${slide.file}`);
    expect(props[2].items[0].file).toBe(`${folder}/${report.file}`);

    await page.goto(`/${run}`);
    await expect(page.locator(`img[src*="${photo.file}"]`).first()).toBeVisible({ timeout: 15_000 });
    await expect(page.locator(`a[href*="${report.file}"]`).first()).toBeAttached();
  });

  test('tile icon, SEO image and metric icon', async ({ page, request }) => {
    test.setTimeout(180_000);
    const run = `tile-${Date.now()}`;
    const panel = await login(page, fixtures.defaultTenant);
    const icon = await uploadImage(page, panel, `${run}-icon`);
    const seo = await uploadImage(page, panel, `${run}-seo`);
    const metricIcon = await uploadImage(page, panel, `${run}-metric`);

    await page.goto(`${panel}/tiles/create`);
    await page.getByLabel('Titel').first().fill(`Wasser ${run}`);
    await pick(page, field(page, 'Icon'), `${run}-icon`, icon.file);
    await pick(page, field(page, 'SEO-Bild'), `${run}-seo`, seo.file);

    await page.getByRole('tab', { name: 'Kennzahlen' }).click();
    // A new tile starts with one collapsed metric
    const metric = page.locator('.fi-fo-repeater-item').first();
    await expand(metric);
    await metric.getByLabel('Bezeichnung').fill('Verbrauch');
    await pick(page, field(metric, 'Icon'), `${run}-metric`, metricIcon.file);
    // …with one collapsed period value
    await expand(metric);
    await metric.getByPlaceholder('z.B. 2023').fill('2024');
    await field(metric, 'Wert').locator('input').fill('42');

    await page.getByRole('button', { name: 'Erstellen', exact: true }).click();
    await page.waitForURL(/\/tiles\/\d+\/edit/);
    const tileId = page.url().match(/\/tiles\/(\d+)\/edit/)![1];

    const tile = (await json(request, `/api/v1/tiles/${tileId}?locale=de`)).data;
    expect(tile.icon).toContain(`/storage/media/${fixtures.defaultTenant}/${icon.file}`);
    expect(tile.meta.image).toContain(`/storage/media/${fixtures.defaultTenant}/${seo.file}`);
    expect(tile.metric_definitions[0].icon).toContain(`/storage/media/${fixtures.defaultTenant}/${metricIcon.file}`);
  });

  test('category icon', async ({ page, request }) => {
    test.setTimeout(120_000);
    const run = `category-${Date.now()}`;
    const panel = await login(page, fixtures.defaultTenant);
    const icon = await uploadImage(page, panel, `${run}-icon`);

    await page.goto(`${panel}/categories/create`);
    await field(page, 'Kategorie-Gruppe').locator('button.fi-select-input-btn').click();
    await page.getByRole('option', { name: 'Handlungsfelder' }).first().click();
    await page.getByLabel('Titel').first().fill(`Klima ${run}`);
    await pick(page, field(page, 'Icon'), `${run}-icon`, icon.file);
    await page.getByRole('button', { name: 'Erstellen', exact: true }).click();
    await page.waitForURL(/\/categories\/\d+\/edit/);

    const categories = (await json(request, `/api/v1/categories/${fixtures.categoryGroup}?locale=de`)).data;
    const category = categories.find((c: { title?: string }) => JSON.stringify(c).includes(`Klima ${run}`));
    expect(category, JSON.stringify(categories)).toBeTruthy();
    expect(JSON.stringify(category)).toContain(`media/${fixtures.defaultTenant}/${icon.file}`);
  });

  test('dashboard logo and favicon', async ({ page, request }) => {
    test.setTimeout(120_000);
    const run = `brand-${Date.now()}`;
    const panel = await login(page, fixtures.defaultTenant, admin);
    const logo = await uploadImage(page, panel, `${run}-logo`);
    const favicon = await uploadImage(page, panel, `${run}-favicon`);

    await page.goto(`${panel}/theme`);
    await pick(page, field(page, 'Logo'), `${run}-logo`, logo.file);
    await pick(page, field(page, 'Favicon'), `${run}-favicon`, favicon.file);
    await page.getByRole('button', { name: 'Speichern' }).last().click();
    await expect(page.getByText('Gespeichert')).toBeVisible();

    expect((await json(request, '/api/v1/config/branding')).data.logo_url).toContain(`/storage/media/${fixtures.defaultTenant}/${logo.file}`);
    expect((await json(request, '/api/v1/config/general')).data.favicon_url).toContain(`/storage/media/${fixtures.defaultTenant}/${favicon.file}`);
  });

  test('footer sponsor logo', async ({ page, request }) => {
    test.setTimeout(120_000);
    const run = `sponsor-${Date.now()}`;
    const panel = await login(page, fixtures.defaultTenant, admin);
    const logo = await uploadImage(page, panel, `${run}-logo`);

    await page.goto(`${panel}/site-settings`);
    await page.getByRole('button', { name: 'Förderer hinzufügen' }).click();
    // Sponsors saved earlier stay in the list, the new one is added last
    const sponsor = field(page, 'Förderer-Logo', 'last');
    await pick(page, sponsor, `${run}-logo`, logo.file);
    await page.getByLabel('Förderer-Name').last().fill(`Stiftung ${run}`);
    await page.getByRole('button', { name: 'Speichern' }).last().click();
    await expect(page.getByText('Gespeichert')).toBeVisible();

    const sponsors = (await json(request, '/api/v1/config/footer?locale=de')).data.sponsors;
    const saved = sponsors.find((s: { name?: string }) => s.name === `Stiftung ${run}`);
    expect(saved, JSON.stringify(sponsors)).toBeTruthy();
    expect(saved.image).toBe(`media/${fixtures.defaultTenant}/${logo.file}`);
  });
});
