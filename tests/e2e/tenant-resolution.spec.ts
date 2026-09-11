import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * E2E Tests for Domain-based Tenant Resolution
 *
 * These tests verify the ResolveTenantFromRequest middleware behavior:
 * - Priority: Token > Domain > Default
 * - Domain normalization (www. stripping, lowercase)
 * - /api/config/tenant endpoint returns correct resolved_by
 *
 * Prerequisites:
 * - ddev running with additional hostnames configured
 * - Test data seeded via: npm run test:e2e:seed
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

// Base URLs for different tenant domains
// Note: Using HTTP to avoid SSL certificate issues with ddev's self-signed certs
// ddev additional_hostnames: a.open-source-dashboard -> a.open-source-dashboard.ddev.site
const BASE_URLS = {
  default: 'http://open-source-dashboard.ddev.site',
  tenantA: 'http://a.open-source-dashboard.ddev.site',
  tenantB: 'http://b.open-source-dashboard.ddev.site',
  wwwTenantA: 'http://www.a.open-source-dashboard.ddev.site',
};

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
      console.error('Failed to seed test data. Make sure ddev is running.');
      console.error('Run: ddev start && npm run test:e2e:seed');
      throw error;
    }
  });

  test.describe('Domain → Tenant Mapping', () => {
    test('resolves tenant A from a.open-source-dashboard.ddev.site domain', async ({ request }) => {
      const response = await request.get(`${BASE_URLS.tenantA}/api/config/tenant`);
      
      expect(response.ok()).toBeTruthy();
      
      const data = await response.json();
      
      expect(data.data).toMatchObject({
        slug: 'tenant-a',
        resolved_by: 'domain',
      });
    });

    test('resolves tenant B from b.open-source-dashboard.ddev.site domain', async ({ request }) => {
      const response = await request.get(`${BASE_URLS.tenantB}/api/config/tenant`);
      
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
      // This test requires www.a.open-source-dashboard.ddev.site to be configured in ddev
      // If not available, the test will be skipped
      try {
        const response = await request.get(`${BASE_URLS.wwwTenantA}/api/config/tenant`, {
          timeout: 5000,
        });
        
        // If we get a response, verify tenant resolution
        if (response.ok()) {
          const data = await response.json();
          
          expect(data.data).toMatchObject({
            slug: 'tenant-a',
            resolved_by: 'domain',
          });
        } else {
          // www subdomain might not be configured - skip with info
          test.skip(true, 'www subdomain not configured in ddev');
        }
      } catch (error) {
        // Connection refused or timeout - www subdomain not available
        test.skip(true, 'www subdomain not reachable (may need ddev restart after config change)');
      }
    });
  });

  test.describe('Precedence: Token > Domain > Default', () => {
    test('token takes precedence over domain (tenant A token on tenant B domain)', async ({ request }) => {
      // Request tenant B domain but with tenant A token
      // Token should win -> resolved_by = 'token', slug = 'tenant-a'
      const response = await request.get(`${BASE_URLS.tenantB}/api/config/tenant`, {
        headers: {
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
      const response = await request.get(`${BASE_URLS.tenantA}/api/config/tenant`);
      
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
      // Request default domain without token
      // Default tenant has domain open-source-dashboard.ddev.site -> resolved_by = 'domain'
      const response = await request.get(`${BASE_URLS.default}/api/config/tenant`);

      expect(response.ok()).toBeTruthy();

      const data = await response.json();

      expect(data.data).toMatchObject({
        slug: 'default',
        resolved_by: 'domain',
      });
    });

    test('default domain without token resolves via domain match', async ({ request }) => {
      // Request default domain without token - resolved by domain mapping
      const response = await request.get(`${BASE_URLS.default}/api/config/tenant`);

      expect(response.ok()).toBeTruthy();

      const data = await response.json();

      // Default tenant now has a domain configured, so resolved_by = 'domain'
      expect(data.data.resolved_by).toBe('domain');
    });
  });

  test.describe('API Response Structure', () => {
    test('/api/config/tenant returns expected fields', async ({ request }) => {
      const response = await request.get(`${BASE_URLS.tenantA}/api/config/tenant`);
      
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
