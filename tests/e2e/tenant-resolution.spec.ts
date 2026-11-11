// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * E2E Tests for Domain-based Tenant Resolution
 *
 * These tests verify the ResolveTenantFromRequest middleware behavior:
 * - Priority: Token > Domain > Default
 * - Domain normalization (www. stripping, lowercase)
 * - /api/v1/config/tenant endpoint returns correct resolved_by
 *
 * Requests go to PLAYWRIGHT_BASE_URL and carry the tenant domain in the Host
 * header, so no DNS entries for the test domains are needed.
 * The seed command assigns these domains to the test tenants.
 */

// Test fixture data - populated by beforeAll hook
let fixtures: {
  tokens: {
    tenantA: string;
    tenantB: string;
  };
  tenants: {
    default: { id: number; slug: string; domain: string | null };
    tenantA: { id: number; slug: string; domain: string };
    tenantB: { id: number; slug: string; domain: string };
  };
};

// Tenant domains assigned by e2e:seed-tenant-resolution
const HOSTS = {
  tenantA: 'a.open-source-dashboard.ddev.site',
  tenantB: 'b.open-source-dashboard.ddev.site',
  wwwTenantA: 'www.a.open-source-dashboard.ddev.site',
};

const TENANT_CONFIG = '/api/v1/config/tenant';

test.describe('Domain-based Tenant Resolution', () => {
  test.beforeAll(async () => {
    // Seed test data and capture fixtures
    try {
      const artisanCmd = process.env.E2E_ARTISAN_CMD ?? 'ddev exec php artisan';
      const output = execSync(`${artisanCmd} e2e:seed-tenant-resolution --json`, {
        encoding: 'utf-8',
        cwd: process.cwd(),
      });
      
      fixtures = JSON.parse(output.trim());
      
      if (!fixtures.success) {
        throw new Error('Seeding failed');
      }
    } catch (error) {
      console.error('Failed to seed test data. Check E2E_ARTISAN_CMD (default: ddev exec php artisan).');
      throw error;
    }
  });

  test.describe('Domain → Tenant Mapping', () => {
    test('resolves tenant A from a.open-source-dashboard.ddev.site domain', async ({ request }) => {
      const response = await request.get(TENANT_CONFIG, { headers: { Host: HOSTS.tenantA } });
      
      expect(response.ok()).toBeTruthy();
      
      const data = await response.json();
      
      expect(data.data).toMatchObject({
        slug: 'tenant-a',
        resolved_by: 'domain',
      });
    });

    test('resolves tenant B from b.open-source-dashboard.ddev.site domain', async ({ request }) => {
      const response = await request.get(TENANT_CONFIG, { headers: { Host: HOSTS.tenantB } });
      
      expect(response.ok()).toBeTruthy();
      
      const data = await response.json();
      
      expect(data.data).toMatchObject({
        slug: 'tenant-b',
        resolved_by: 'domain',
      });
    });
  });

  test.describe('www. Prefix Stripping', () => {
    test('strips www. prefix and resolves tenant A', async ({ request }) => {
      const response = await request.get(TENANT_CONFIG, { headers: { Host: HOSTS.wwwTenantA } });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();

      expect(data.data).toMatchObject({
        slug: 'tenant-a',
        resolved_by: 'domain',
      });
    });
  });

  test.describe('Precedence: Token > Domain > Default', () => {
    test('token takes precedence over domain (tenant A token on tenant B domain)', async ({ request }) => {
      // Request tenant B domain but with tenant A token
      // Token should win -> resolved_by = 'token', slug = 'tenant-a'
      const response = await request.get(TENANT_CONFIG, {
        headers: {
          Host: HOSTS.tenantB,
          'Authorization': `Bearer ${fixtures.tokens.tenantA}`,
        },
      });
      
      expect(response.ok()).toBeTruthy();
      
      const data = await response.json();
      
      expect(data.data).toMatchObject({
        slug: 'tenant-a',
        resolved_by: 'token',
      });
    });

    test('domain takes precedence over default (no token, known domain)', async ({ request }) => {
      // Request tenant A domain without token
      // Domain should match -> resolved_by = 'domain'
      const response = await request.get(TENANT_CONFIG, { headers: { Host: HOSTS.tenantA } });
      
      expect(response.ok()).toBeTruthy();
      
      const data = await response.json();
      
      expect(data.data).toMatchObject({
        slug: 'tenant-a',
        resolved_by: 'domain',
      });
    });
  });

  test.describe('Default Fallback', () => {
    test('resolves default tenant from its configured domain', async ({ request }) => {
      // The seeders derive the default domain from APP_URL.
      const domain = fixtures.tenants.default.domain;
      test.skip(!domain, 'default tenant has no domain');

      const response = await request.get(TENANT_CONFIG, { headers: { Host: domain! } });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();

      expect(data.data).toMatchObject({
        slug: 'default',
        resolved_by: 'domain',
      });
    });

    test('unknown domain without token falls back to the default tenant', async ({ request }) => {
      const response = await request.get(TENANT_CONFIG, { headers: { Host: 'unknown.example' } });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();

      expect(data.data).toMatchObject({
        slug: 'default',
        resolved_by: 'default',
      });
    });
  });

  test.describe('API Response Structure', () => {
    test('/api/v1/config/tenant returns expected fields', async ({ request }) => {
      const response = await request.get(TENANT_CONFIG, { headers: { Host: HOSTS.tenantA } });
      
      expect(response.ok()).toBeTruthy();
      
      const data = await response.json();
      
      // Verify response structure
      expect(data).toHaveProperty('data');
      expect(data.data).toHaveProperty('slug');
      expect(data.data).toHaveProperty('name');
      expect(data.data).toHaveProperty('domain');
      expect(data.data).toHaveProperty('frontend_base_url');
      expect(data.data).toHaveProperty('resolved_by');
      
      // Verify resolved_by is one of the expected values
      expect(['token', 'domain', 'default']).toContain(data.data.resolved_by);
    });
  });
});
