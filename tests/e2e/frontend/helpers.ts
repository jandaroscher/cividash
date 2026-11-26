// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { execSync } from 'node:child_process';
import type { Page } from '@playwright/test';

/**
 * Reset the public API rate limiter (60 requests/min per IP, routes/api.php).
 * Every SPA page load makes about nine API calls, so a test run that loads
 * pages back to back trips it within a minute and the app renders an empty
 * tile grid. The limiter lives in the application cache.
 */
export function resetApiThrottle(): void {
  const artisan = process.env.E2E_ARTISAN_CMD ?? 'ddev exec php artisan';
  execSync(`${artisan} cache:clear`, { stdio: 'ignore' });
}

/**
 * GET a public API path from inside the page, so it runs on the tenant host:
 * Node cannot resolve *.localhost tenant hosts, Chromium can.
 */
export async function fetchApi<T = unknown>(page: Page, path: string): Promise<T> {
  return page.evaluate(async (url) => {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`${url} answered ${response.status}`);
    const json = await response.json();
    return json.data ?? json;
  }, path);
}
