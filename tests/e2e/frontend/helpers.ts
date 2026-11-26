// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { execSync } from 'node:child_process';
import type { Page } from '@playwright/test';

/**
 * Reset the public API rate limiter (PUBLIC_API_RATE_LIMIT requests/min per IP,
 * default 300). Every SPA page load makes about ten API calls, and the whole
 * suite loads pages back to back from one IP, so each spec starts with an empty
 * bucket. The limiter lives in the application cache.
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

/** Escape a value for use inside a RegExp, so data such as "CO2 (t)" matches literally. */
export function escapeRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
