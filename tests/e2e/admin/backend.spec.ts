// SPDX-FileCopyrightText: 2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect, type Page, type Locator } from '@playwright/test';
import { execSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

/**
 * End-to-end walk through the whole Filament admin backend, the way an editor uses it.
 *
 * Every test fails on a 5xx response, an uncaught page error or a console error,
 * and leaves a screenshot in E2E_SCREENSHOT_DIR (default: test-results/admin-backend).
 *
 * The suite writes data, so run it only against a local or test instance:
 *   E2E_ARTISAN_CMD="php artisan" PLAYWRIGHT_BASE_URL=http://localhost:8000 \
 *     npx playwright test --project=admin-backend
 * The integrations page is only checked when the app runs with CIVITAS_ENABLED=true.
 */

type Fixtures = {
  tenants: { tenantA: { id: number; slug: string }; tenantB: { id: number; slug: string } };
  admin: { email: string; password: string };
};

const SHOTS = process.env.E2E_SCREENSHOT_DIR ?? 'test-results/admin-backend';
const RUN = Date.now().toString(36);
// 1x1 PNG for logo and image uploads
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64',
);

let fx: Fixtures;
let page: Page;
let admin: string;
const problems: string[] = [];

test.describe.configure({ mode: 'serial', timeout: 60_000 });

test.beforeAll(async ({ browser, baseURL }) => {
  const artisan = process.env.E2E_ARTISAN_CMD ?? 'ddev exec php artisan';
  fx = JSON.parse(execSync(`${artisan} e2e:seed-full --clean --json`, { encoding: 'utf-8' }).trim());
  admin = `/admin/${fx.tenants.tenantA.id}`;
  mkdirSync(SHOTS, { recursive: true });

  const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 900 } });
  page = await context.newPage();
  page.setDefaultTimeout(15_000);
  page.on('console', (m) => m.type() === 'error' && problems.push(`console: ${m.text()}`));
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  page.on('response', (r) => r.status() >= 500 && problems.push(`HTTP ${r.status()} ${r.request().method()} ${r.url()}`));
});

test.afterEach(async ({}, info) => {
  await page.screenshot({ path: path.join(SHOTS, `${info.title.replace(/\W+/g, '-')}.png`), fullPage: true });
  expect(problems.splice(0), 'no 5xx responses or JS errors').toEqual([]);
});

test.afterAll(async () => page?.context().close());

// ---------------------------------------------------------------- helpers

async function open(url: string): Promise<void> {
  const response = await page.goto(url);
  expect(response?.status(), url).toBeLessThan(400);
  await page.waitForLoadState('networkidle');
}

/** Matches a field label exactly, with or without the required mark. */
function label(text: string): RegExp {
  return new RegExp(`^\\s*${text}\\s*\\*?\\s*$`);
}

function field(text: string): Locator {
  return page
    .locator('[data-field-wrapper]')
    .filter({ has: page.locator('.fi-fo-field-label-content', { hasText: label(text) }) })
    .first();
}

async function choose(label: string, option: string | RegExp): Promise<void> {
  const wrapper = field(label);
  const native = wrapper.locator('select');
  if (await native.count()) {
    await native.selectOption({ label: String(option) });
    return;
  }
  await wrapper.locator('.fi-select-input-btn').click();
  await wrapper.getByRole('option', { name: option }).first().click();
}

async function upload(label: string, name: string, content: Buffer): Promise<void> {
  await field(label).locator('input[type=file]').setInputFiles({ name, mimeType: 'image/png', buffer: content });
  // FilePond uploads to Livewire before the form can be saved
  await expect(field(label).locator('.filepond--file-status-main', { hasText: /abgeschlossen|complete/i })).toBeVisible({ timeout: 15000 });
}

async function richText(label: string, text: string): Promise<void> {
  const editor = field(label).locator('[contenteditable=true]').first();
  await editor.click();
  await editor.fill(text);
}

async function submit(name: string | RegExp): Promise<void> {
  // close earlier notifications so expectSaved() only sees the one for this submit
  for (const close of await page.locator('.fi-no-notification-close-btn').all()) await close.click().catch(() => {});
  await expect(page.locator('.fi-no-notification')).toHaveCount(0);
  await page.getByRole('button', { name, exact: typeof name === 'string' }).last().click();
}

/** Submits until the browser's validation reveals the collapsed field (a pending blur request can swallow the first click). */
async function submitUntilVisible(name: string, target: Locator): Promise<void> {
  await expect(async () => {
    await submit(name);
    await expect(target).toBeVisible({ timeout: 2000 });
  }).toPass({ timeout: 15000 });
}

async function expectSaved(text: RegExp = /gespeichert/i): Promise<void> {
  await expect(page.locator('.fi-no-notification', { hasText: text }).first()).toBeVisible({ timeout: 15000 });
  await expect(page.locator('.fi-fo-field-wrp-error-message, .fi-fo-field-error-message')).toHaveCount(0);
}

async function deleteFromEdit(): Promise<void> {
  await page.getByRole('button', { name: 'Löschen', exact: true }).first().click();
  await page.locator('.fi-modal-window').getByRole('button', { name: 'Löschen', exact: true }).click();
  await page.waitForURL((url) => !url.pathname.endsWith('/edit'));
}

async function firstEditUrl(index: string): Promise<string> {
  await open(index);
  const href = await page.locator('table tbody a[href*="/edit"]').first().getAttribute('href');
  expect(href, `${index} has an editable record`).toBeTruthy();
  return href!;
}

// ---------------------------------------------------------------- login

test('login rejects a wrong password', async () => {
  await open('/admin/login');
  await page.locator('[id="form.email"]').fill(fx.admin.email);
  await page.locator('[id="form.password"]').fill('wrong-password');
  await page.getByRole('button', { name: 'Anmelden' }).click();
  await expect(page.locator('.fi-fo-field-wrp-error-message, .fi-fo-field-error-message').first()).toBeVisible();
  expect(page.url()).toContain('/admin/login');
});

test('login opens the dashboard', async () => {
  await page.locator('[id="form.password"]').fill(fx.admin.password);
  await page.getByRole('button', { name: 'Anmelden' }).click();
  await page.waitForURL(/\/admin\/\d+/);
  await open(admin);
  await expect(page.locator('.fi-sidebar')).toBeVisible();
});

// ---------------------------------------------------------------- every page renders

test('every admin page renders', async () => {
  const pages = [
    '', '/categories', '/categories/create', '/category-groups', '/category-groups/create',
    '/tiles', '/tiles/create', '/pages', '/pages/create', '/themes', '/themes/create',
    '/users', '/users/create', '/theme', '/site-settings', '/api-keys', '/data-import',
    '/manage-content', '/manage-dashboard', '/manage-data-by-period', '/dashboard-configuration', '/profile',
  ];
  for (const p of pages) await open(`${admin}${p}`);
  for (const index of ['/categories', '/category-groups', '/tiles', '/users']) {
    await open(await firstEditUrl(`${admin}${index}`));
  }
  if (process.env.CIVITAS_ENABLED === 'true') await open(`${admin}/integrations`);
});

// ---------------------------------------------------------------- resources

test('category group: create, edit, delete', async () => {
  await open(`${admin}/category-groups/create`);
  await page.getByLabel(label('Titel')).fill(`E2E Gruppe ${RUN}`);
  await submit('Erstellen');
  await page.waitForURL(/category-groups\/\d+\/edit/);
  await page.getByLabel(label('Titel')).fill(`E2E Gruppe ${RUN} geändert`);
  await submit('Speichern');
  await expectSaved();
  await page.reload();
  await expect(page.getByLabel(label('Titel'))).toHaveValue(`E2E Gruppe ${RUN} geändert`);
  await deleteFromEdit();
  await open(`${admin}/category-groups`);
  await expect(page.getByText(`E2E Gruppe ${RUN}`)).toHaveCount(0);
});

test('category: create with icon, edit, delete', async () => {
  await open(`${admin}/categories/create`);
  await choose('Kategorie-Gruppe', 'Gruppe A');
  await page.getByLabel(label('Titel')).fill(`E2E Kategorie ${RUN}`);
  await upload('Icon', 'icon.png', PNG);
  await submit('Erstellen');
  await page.waitForURL(/categories\/\d+\/edit/);
  await page.getByLabel(label('Titel')).fill(`E2E Kategorie ${RUN} b`);
  await submit('Speichern');
  await expectSaved();
  await open(`${admin}/categories`);
  await expect(page.getByText(`E2E Kategorie ${RUN} b`)).toBeVisible();
  await page.getByRole('link', { name: `E2E Kategorie ${RUN} b` }).first().click();
  await page.waitForURL(/categories\/\d+\/edit/);
  await deleteFromEdit();
});

test('tile: create with rich text and metric, translate, delete', async () => {
  await open(`${admin}/tiles/create`);
  await page.getByLabel(label('Titel')).fill(`E2E Kachel ${RUN}`);
  await richText('Beschreibung', 'E2E Beschreibung mit Text');
  await page.getByRole('tab', { name: 'Kennzahlen' }).click();
  await choose('Zeitgranularität', 'Jahr');
  // Metric and period items start collapsed; each invalid required field must reveal its item
  const metric = page.locator('.fi-fo-repeater-item').first();
  await expect(metric.getByLabel(label('Bezeichnung'))).toBeHidden();
  await submitUntilVisible('Erstellen', metric.getByLabel(label('Bezeichnung')));
  await metric.getByLabel(label('Bezeichnung')).fill('E2E Kennzahl');
  await metric.getByLabel(label('Einheit')).fill('%');
  const period = metric.locator('.fi-fo-repeater-item').first();
  await expect(period.getByLabel(label('Wert'))).toBeHidden();
  await submitUntilVisible('Erstellen', period.getByLabel(label('Wert')));
  await period.getByLabel(label('Zeitraum')).fill('2025');
  await period.getByLabel(label('Wert')).fill('12.5');
  await submit('Erstellen');
  await page.waitForURL(/tiles\/\d+\/edit/);
  const editUrl = new URL(page.url()).pathname;

  await page.locator('select#activeLocale').selectOption('en');
  // the new tile has no English title yet, so the switch empties the field
  await expect(page.getByLabel(label('Titel'))).toHaveValue('');
  await page.getByLabel(label('Titel')).fill(`E2E Tile ${RUN}`);
  await richText('Beschreibung', 'E2E description');
  // metric labels are translated too and required in every language
  await page.getByRole('tab', { name: 'Kennzahlen' }).click();
  await submitUntilVisible('Speichern', metric.getByLabel(label('Bezeichnung')));
  await metric.getByLabel(label('Bezeichnung')).fill('E2E metric');
  await submit('Speichern');
  await expectSaved();

  await open(editUrl);
  await expect(page.getByLabel(label('Titel'))).toHaveValue(`E2E Kachel ${RUN}`);
  await expect(field('Beschreibung')).toContainText('E2E Beschreibung mit Text');
  await open(`${editUrl}?activeLocale=en`);
  await expect(page.getByLabel(label('Titel'))).toHaveValue(`E2E Tile ${RUN}`);
  await deleteFromEdit();
});

// The seeded tenant has no pages, so this is its landing page (slug "/" in every locale).
test('page: create landing page with block, edit, translate, delete', async () => {
  await open(`${admin}/pages/create`);
  await page.getByLabel(label('Titel')).fill(`E2E Seite ${RUN}`);
  await page.getByRole('button', { name: 'Zu Blöcke hinzufügen' }).click();
  await page.getByRole('button', { name: 'Intro Text' }).click();
  const block = page.locator('.fi-fo-builder-item').first();
  await block.locator('input[type=text]').first().fill('E2E Überschrift');
  await block.locator('[contenteditable=true]').first().fill('E2E Absatz');
  await submit('Erstellen');
  await page.waitForURL(/pages\/\d+\/edit/);
  await expect(page.locator('.fi-fo-builder-item').first().locator('input[type=text]').first()).toHaveValue('E2E Überschrift');

  const toggle = page.locator('.fi-fo-builder-item [role=switch]').first();
  const before = await toggle.getAttribute('aria-checked');
  await toggle.click();
  await expect(toggle).not.toHaveAttribute('aria-checked', before!);
  await submit('Speichern');
  await expectSaved();
  await expect(page.locator('select#activeLocale')).toHaveValue('de');

  await page.locator('select#activeLocale').selectOption('en');
  await expect(page.getByLabel(label('Titel'))).toHaveValue('');
  await page.getByLabel(label('Titel')).fill(`E2E Page ${RUN}`);
  await submit('Speichern');
  await expectSaved();
  const editUrl = new URL(page.url()).pathname;
  await open(`${editUrl}?activeLocale=en`);
  await expect(page.getByLabel(label('Titel'))).toHaveValue(`E2E Page ${RUN}`);
  await open(`${editUrl}?activeLocale=de`);
  await expect(page.getByLabel(label('Titel'))).toHaveValue(`E2E Seite ${RUN}`);
  await deleteFromEdit();
});

test('theme: create, edit, delete', async () => {
  await open(`${admin}/themes/create`);
  await page.getByLabel(label('Name')).fill(`E2E Theme ${RUN}`);
  await page.getByLabel(label('Slug')).fill(`e2e-theme-${RUN}`);
  await submit('Erstellen');
  await page.waitForURL(/themes\/\d+\/edit/);
  await page.getByLabel(label('Name')).fill(`E2E Theme ${RUN} b`);
  await submit('Speichern');
  await expectSaved();
  await deleteFromEdit();
});

test('user: create editor, edit, delete', async () => {
  await open(`${admin}/users/create`);
  await page.getByLabel(label('Vorname')).fill('E2E');
  await page.getByLabel(label('Nachname')).fill(`Redakteur ${RUN}`);
  await page.getByLabel(label('E-Mail / Benutzername')).fill(`e2e-editor-${RUN}@example.com`);
  await page.getByLabel(label('Passwort')).fill('e2e-editor-password-123');
  await choose('Rolle', 'Redakteur');
  await choose('Dashboard-Freigaben', 'E2E Tenant A');
  await submit('Erstellen');
  // user create and edit return to the list
  await page.waitForURL(/\/users$/);
  const row = page.locator('table tbody tr', { hasText: `e2e-editor-${RUN}@example.com` });
  await row.getByRole('link').first().click();
  await page.waitForURL(/users\/\d+\/edit/);
  await page.getByLabel(label('Telefon')).fill('0941 123456');
  await submit('Speichern');
  await page.waitForURL(/\/users$/);
  await row.getByRole('link').first().click();
  await page.waitForURL(/users\/\d+\/edit/);
  await expect(page.getByLabel(label('Telefon'))).toHaveValue('0941 123456');
  await deleteFromEdit();
  await open(`${admin}/users`);
  await expect(page.getByText(`e2e-editor-${RUN}@example.com`)).toHaveCount(0);
});

// ---------------------------------------------------------------- settings pages

test('branding: logo upload and colour are saved', async () => {
  await open(`${admin}/theme`);
  await upload('Logo', 'logo.png', PNG);
  await page.getByRole('tab', { name: 'Farben' }).click();
  await page.getByLabel(label('Primärfarbe')).fill('#123456');
  await submit('Speichern');
  await expectSaved();
  await page.reload();
  await page.getByRole('tab', { name: 'Farben' }).click();
  await expect(page.getByLabel(label('Primärfarbe'))).toHaveValue('#123456');
  await page.getByRole('tab', { name: 'Logo' }).click();
  await expect(field('Logo').locator('.filepond--item')).toHaveCount(1);
});

test('site settings are saved', async () => {
  await open(`${admin}/site-settings`);
  await page.getByLabel(label('Seitenname')).fill(`E2E Dashboard ${RUN}`);
  await submit('Speichern');
  await expectSaved();
  await page.reload();
  await expect(page.getByLabel(label('Seitenname'))).toHaveValue(`E2E Dashboard ${RUN}`);
});

test('dashboard texts and contact are saved', async () => {
  await open(`${admin}/manage-dashboard`);
  await page.getByLabel(label('Made-with-Text')).fill(`E2E ${RUN}`);
  await submit('Änderungen speichern');
  await expectSaved();
  await page.reload();
  await expect(page.getByLabel(label('Made-with-Text'))).toHaveValue(`E2E ${RUN}`);
});

test('hero blocks are saved', async () => {
  await open(`${admin}/manage-content`);
  const count = await page.locator('.fi-fo-builder-item').count();
  await page.getByRole('button', { name: /Zu hero-Blöcke hinzufügen/ }).click();
  await page.getByRole('menuitem', { name: 'Überschrift', exact: true }).click();
  const block = page.locator('.fi-fo-builder-item').nth(count);
  await block.getByLabel(label('Überschriftstext')).fill(`E2E Hero ${RUN}`);
  await block.getByLabel(label('HTML-Tag \\(h1–h6\\)')).fill('h2');
  await submit('Änderungen speichern');
  await expectSaved();
  await page.reload();
  await expect(page.locator('.fi-fo-builder-item input[type=text]').nth(count)).toHaveValue(`E2E Hero ${RUN}`);
});

test('dashboard configuration is saved', async () => {
  await open(`${admin}/dashboard-configuration`);
  await page.getByLabel(label('Zusatzinfo')).fill(`E2E Zusatzinfo ${RUN}`);
  await submit('Speichern');
  await expectSaved();
  await page.reload();
  await expect(page.getByLabel(label('Zusatzinfo'))).toHaveValue(`E2E Zusatzinfo ${RUN}`);
});

test('profile is saved', async () => {
  await open(`${admin}/profile`);
  await page.getByLabel(label('Telefon')).fill('0941 654321');
  await submit('Profil speichern');
  await expectSaved(/aktualisiert/);
  await page.reload();
  await expect(page.getByLabel(label('Telefon'))).toHaveValue('0941 654321');
});

test('password change works with the new password', async () => {
  const newPassword = `${fx.admin.password}-${RUN}`;
  await open(`${admin}/profile`);
  await page.locator('input[type=password]').nth(0).fill(fx.admin.password);
  await page.locator('input[type=password]').nth(1).fill(newPassword);
  await page.locator('input[type=password]').nth(2).fill(newPassword);
  await submit('Passwort speichern');
  await expectSaved(/geändert/);
  fx.admin.password = newPassword;
});

// ---------------------------------------------------------------- API keys, data

test('api key: create shows the token once, delete', async () => {
  await open(`${admin}/api-keys`);
  await page.getByRole('button', { name: 'Token erstellen' }).click();
  const modal = page.locator('.fi-modal-window').last();
  await modal.getByLabel(label('Token-Name')).fill(`e2e-key-${RUN}`);
  // the modal's warning text has no label of its own
  await expect(modal.getByText('Warning', { exact: true })).toHaveCount(0);
  await modal.getByRole('button', { name: 'Token erstellen' }).click();
  await expect(page.getByText('Ihr API Token')).toBeVisible();
  await page.getByRole('button', { name: 'Ich habe meinen Token kopiert' }).click();
  await expect(page.getByText('Ihr API Token')).toHaveCount(0);
  const row = page.locator('table tbody tr', { hasText: `e2e-key-${RUN}` });
  await expect(row).toHaveCount(1);
  await row.getByRole('button', { name: 'Löschen' }).click();
  await page.locator('.fi-modal-window').getByRole('button', { name: 'Token löschen' }).click();
  await expect(row).toHaveCount(0);
});

test('data import: dry run and import of the example bundle', async () => {
  const response = await page.request.get('/api/v1/import/examples/valid-full');
  expect(response.ok()).toBeTruthy();
  const bundle = path.join(tmpdir(), `cividash-e2e-bundle-${RUN}.json`);
  writeFileSync(bundle, await response.body());

  await open(`${admin}/data-import`);
  await field('Bundle-Datei').locator('input[type=file]').setInputFiles(bundle);
  await expect(field('Bundle-Datei').locator('.filepond--file-status-main', { hasText: /abgeschlossen|complete/i })).toBeVisible({ timeout: 15000 });
  await submit('Dry-Run starten');
  await expectSaved(/Dry-Run erfolgreich/);
  await expect(page.getByText('Ergebnis (dry_run)')).toBeVisible();
  // the commit asks with a native browser confirm
  page.once('dialog', (dialog) => dialog.accept());
  await submit('Import bestätigen');
  await expectSaved(/committet/);
  await open(`${admin}/tiles`);
  await expect(page.getByText('Energieverbrauch').first()).toBeVisible();
});

test('data by period: a value is saved', async () => {
  await open(`${admin}/manage-data-by-period`);
  // values of the bundle imported above
  await page.locator('main select').filter({ has: page.locator('option', { hasText: '2023' }) }).selectOption({ label: '2023' });
  const input = page.getByLabel(label('Gesamtverbrauch'));
  await expect(input).toHaveValue('3620');
  await input.fill('3700');
  await submit('Speichern');
  await expectSaved();
  await page.reload();
  await page.locator('main select').filter({ has: page.locator('option', { hasText: '2023' }) }).selectOption({ label: '2023' });
  await expect(page.getByLabel(label('Gesamtverbrauch'))).toHaveValue(/^3700(\.0+)?$/);
});

// ---------------------------------------------------------------- tenants

test('tenant menu switches the dashboard', async () => {
  await open(admin);
  await page.locator('.fi-tenant-menu-trigger').click();
  await page.getByRole('menuitem', { name: /E2E Tenant B/ }).first().click();
  await page.waitForURL(new RegExp(`/admin/${fx.tenants.tenantB.id}(/|$)`));
  await open(`/admin/${fx.tenants.tenantB.id}/tiles`);
  await expect(page.getByText('Kachel B-1')).toBeVisible();
  await expect(page.getByText('Kachel A-1')).toHaveCount(0);
});

test('a new dashboard can be registered', async () => {
  await open('/admin/new');
  await page.getByLabel(label('Dashboard-Name')).fill(`E2E Neu ${RUN}`);
  await page.getByLabel(label('Slug')).fill(`e2e-neu-${RUN}`);
  await submit('Neues Dashboard anlegen');
  await page.waitForURL(/\/admin\/\d+(\/|$)/);
  await expect(page.locator('.fi-tenant-menu-trigger')).toContainText(`E2E Neu ${RUN}`);
});

test('logout returns to the login page', async () => {
  await open(admin);
  await page.locator('.fi-user-menu-trigger').click();
  await page.getByRole('menuitem', { name: 'Abmelden' }).click();
  await page.waitForURL(/\/admin\/login/);
  // the changed password from the profile test logs in again
  await page.locator('[id="form.email"]').fill(fx.admin.email);
  await page.locator('[id="form.password"]').fill(fx.admin.password);
  await page.getByRole('button', { name: 'Anmelden' }).click();
  await page.waitForURL(/\/admin\/\d+/);
});
