// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect } from '@playwright/test';

/**
 * Admin login through Keycloak, against a real Keycloak that requires PKCE (S256)
 * for the client, as CIVITAS/CORE 2 does.
 *
 * Opt-in: runs only when E2E_KEYCLOAK_PASSWORD is set. Prerequisites:
 * - Keycloak from docker/keycloak (its realm requires PKCE for cividash-dashboard),
 *   started with DASHBOARD_URL set to the app's base URL.
 * - The app with KEYCLOAK_SSO_ENABLED=true and the KEYCLOAK_* settings pointing at it.
 * - E2E_KEYCLOAK_USER (default sso-demo) and E2E_KEYCLOAK_PASSWORD: the demo user.
 */
const password = process.env.E2E_KEYCLOAK_PASSWORD;
const username = process.env.E2E_KEYCLOAK_USER ?? 'sso-demo';

test.describe('Admin login through Keycloak', () => {
  test.skip(!password, 'E2E_KEYCLOAK_PASSWORD is not set');

  test('signs in to the admin panel through Keycloak with PKCE', async ({ page }) => {
    await page.goto('/admin/login');
    await page.locator('a[href$="/admin/auth/keycloak/redirect"]').click();

    // Keycloak rejects an authorization request without a code challenge before
    // it shows the login form, so reaching the form proves the challenge was sent.
    await page.locator('#username').fill(username);
    await page.locator('#password').fill(password!);
    await page.locator('#kc-login').click();

    // The token exchange needs the matching code verifier; without it the app
    // falls back to /admin/login with an error notification.
    await page.waitForURL((url) => !url.pathname.startsWith('/admin/login') && url.pathname.startsWith('/admin'));
    await expect(page.locator('.fi-sidebar, .fi-topbar').first()).toBeVisible();
  });
});
