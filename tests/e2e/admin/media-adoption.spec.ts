// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect, type APIRequestContext } from '@playwright/test';
import { artisan, login, seedMedia, type MediaFixtures } from './media-helpers';

/**
 * Content saved before the media library stores file paths. Adopting it copies each
 * file into the dashboard's library and stores the copy's id, while the public pages
 * keep showing the same images with the alt texts the editors entered.
 */

let fixtures: MediaFixtures;
let legacy: NonNullable<MediaFixtures['legacy']>;

async function bytes(request: APIRequestContext, url: string): Promise<Buffer> {
  const response = await request.get(url);
  expect(response.ok(), url).toBeTruthy();

  return response.body();
}

test.describe('Media library: adopting existing images', () => {
  test.beforeAll(() => {
    fixtures = seedMedia('--legacy');
    legacy = fixtures.legacy!;
  });

  test.afterAll(() => {
    artisan('e2e:seed-media --reset-branding');
  });

  test('pages, tiles and settings keep their images after the files move into the library', async ({ page, request }) => {
    test.setTimeout(120_000);
    const pageApi = (locale: string) => `/api/v1/content/pages/${legacy.page}?locale=${locale}`;
    const tileApi = `/api/v1/tiles/${legacy.tile}?locale=de`;

    // Before: everything points at the old paths
    const before = (await request.get(pageApi('de')).then((r) => r.json())).blocks;
    expect(before[0].props.image).toBe(legacy.files.harbour);
    const originals = {
      harbour: await bytes(request, `/storage/${legacy.files.harbour}`),
      square: await bytes(request, `/storage/${legacy.files.square}`),
      water: await bytes(request, `/storage/${legacy.files.water}`),
      seo: await bytes(request, `/storage/${legacy.files.seo}`),
      favicon: await bytes(request, `/storage/${legacy.files.favicon}`),
    };

    artisan('media:adopt-existing');

    // After: the page API serves the library copies with the editors' own alt texts
    for (const [locale, alt, sliderAlt] of [
      ['de', 'Boote im Hafen am Morgen', 'Marktplatz mit Brunnen'],
      ['en', 'Boats in the harbour in the morning', 'Market square with fountain'],
    ]) {
      const blocks = (await request.get(pageApi(locale)).then((r) => r.json())).blocks;
      expect(blocks[0].props.image).toMatch(new RegExp(`^media/${fixtures.defaultTenant}/[a-z0-9]+-harbour-${legacy.run}\\.png$`));
      expect(blocks[0].props.image_alt).toBe(alt);
      expect(blocks[0].props.image_copyright).toBe('© Stadtarchiv');
      expect(blocks[1].props.items[0].image).toMatch(new RegExp(`^media/${fixtures.defaultTenant}/.+-square-${legacy.run}\\.png$`));
      expect(blocks[1].props.items[0].image_alt).toBe(sliderAlt);
      expect(await bytes(request, `/storage/${blocks[0].props.image}`)).toEqual(originals.harbour);
      expect(await bytes(request, `/storage/${blocks[1].props.items[0].image}`)).toEqual(originals.square);
    }

    const tile = (await request.get(tileApi).then((r) => r.json())).data;
    expect(tile.icon).toMatch(new RegExp(`/storage/media/${fixtures.defaultTenant}/.+-water-${legacy.run}\\.png$`));
    expect(tile.meta.image).toMatch(new RegExp(`/storage/media/${fixtures.defaultTenant}/.+-seo-${legacy.run}\\.png$`));
    expect(await bytes(request, tile.icon)).toEqual(originals.water);
    expect(await bytes(request, tile.meta.image)).toEqual(originals.seo);

    const favicon = (await request.get('/api/v1/config/general').then((r) => r.json())).data.favicon_url;
    expect(favicon).toMatch(new RegExp(`/storage/media/${fixtures.defaultTenant}/.+-favicon-${legacy.run}\\.png$`));
    expect(await bytes(request, favicon)).toEqual(originals.favicon);

    // The logo on the global defaults belongs to no dashboard: it stays a path every dashboard inherits
    const logo = (await request.get('/api/v1/config/branding').then((r) => r.json())).data.logo_url;
    expect(logo).toBe(`/storage/${legacy.files.logo}`);

    // The public page renders the copies with the alt text of each language
    await page.goto(`/${legacy.slugs.de}`);
    const harbourDe = page.getByAltText('Boote im Hafen am Morgen');
    await expect(harbourDe).toBeVisible({ timeout: 15_000 });
    expect(await harbourDe.getAttribute('src')).toContain(`-harbour-${legacy.run}.png`);
    expect(await harbourDe.evaluate((img: HTMLImageElement) => img.naturalWidth)).toBeGreaterThan(0);

    await page.goto(`/en/${legacy.slugs.en}`);
    const harbourEn = page.getByAltText('Boats in the harbour in the morning');
    await expect(harbourEn).toBeVisible({ timeout: 15_000 });
    expect(await harbourEn.getAttribute('src')).toContain(`-harbour-${legacy.run}.png`);

    // The library lists the copies of this dashboard, not the global logo
    const panel = await login(page, fixtures.defaultTenant);
    await page.goto(`${panel}/media`);
    await page.getByPlaceholder('Suche').fill(legacy.run);
    await expect(page.locator(`main img[src*="-${legacy.run}.png"]`)).toHaveCount(5);
    for (const name of ['harbour', 'square', 'water', 'seo', 'favicon']) {
      await expect(page.locator(`main img[src*="-${name}-${legacy.run}.png"]`)).toBeVisible();
    }
    await expect(page.locator(`main img[src*="-logo-${legacy.run}.png"]`)).toHaveCount(0);

    // A copy knows where it is used
    await page
      .locator('[wire\\:key*="table.records."]')
      .filter({ has: page.locator(`img[src*="-harbour-${legacy.run}.png"]`) })
      .getByRole('link', { name: 'Bearbeiten' })
      .click();
    await expect(page.getByRole('link', { name: `Seite: Alte Bilder ${legacy.run}` })).toBeVisible();
  });
});
