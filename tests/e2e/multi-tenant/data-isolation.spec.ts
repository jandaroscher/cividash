import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * E2E Tests for Multi-Tenant Data Isolation
 *
 * Verifies that API tokens scoped to different tenants
 * only return data belonging to their respective tenant.
 *
 * Prerequisites:
 * - ddev running
 * - Test data seeded via: ddev php artisan e2e:seed-full --json
 */

let fixtures: {
  success: boolean;
  tokens: {
    tenantA: string;
    tenantB: string;
    lifecycle: string;
    lifecycleId: number;
  };
  tenants: {
    tenantA: { id: number; slug: string; domain: string };
    tenantB: { id: number; slug: string; domain: string };
  };
  user: {
    id: number;
    email: string;
    password: string;
  };
};

const BASE_URL = 'http://open-source-dashboard.ddev.site';

test.describe('Multi-Tenant Data Isolation', () => {
  test.beforeAll(async () => {
    try {
      const output = execSync('ddev php artisan e2e:seed-full --clean --json', {
        encoding: 'utf-8',
        cwd: process.cwd(),
      });

      fixtures = JSON.parse(output.trim());

      if (!fixtures.success) {
        throw new Error('Seeding failed');
      }
    } catch (error) {
      console.error('Failed to seed test data. Make sure ddev is running.');
      console.error('Run: ddev start && ddev php artisan e2e:seed-full --json');
      throw error;
    }
  });

  test.describe('Token A sees only Tenant A data', () => {
    test('GET /api/tiles with Token A returns only Tenant A tiles', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/tiles?locale=de`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.tenantA}`,
          Accept: 'application/json',
        },
      });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();
      expect(data.data).toBeDefined();
      expect(data.data.length).toBeGreaterThan(0);

      // Every tile title should contain "A" (from "Tile A-1", "Tile A-2", etc.)
      for (const tile of data.data) {
        const title = tile.title ?? '';
        expect(title).toContain('A-');
      }
    });

    test('GET /api/config/tenant with Token A resolves to Tenant A', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/config/tenant`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.tenantA}`,
          Accept: 'application/json',
        },
      });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();
      expect(data.data.slug).toBe('e2e-tenant-a');
      expect(data.data.resolved_by).toBe('token');
    });
  });

  test.describe('Token B sees only Tenant B data', () => {
    test('GET /api/tiles with Token B returns only Tenant B tiles', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/tiles?locale=de`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.tenantB}`,
          Accept: 'application/json',
        },
      });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();
      expect(data.data).toBeDefined();
      expect(data.data.length).toBeGreaterThan(0);

      // Every tile title should contain "B"
      for (const tile of data.data) {
        const title = tile.title ?? '';
        expect(title).toContain('B-');
      }
    });

    test('GET /api/config/tenant with Token B resolves to Tenant B', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/config/tenant`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.tenantB}`,
          Accept: 'application/json',
        },
      });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();
      expect(data.data.slug).toBe('e2e-tenant-b');
      expect(data.data.resolved_by).toBe('token');
    });
  });

  test.describe('Cross-tenant data is not visible', () => {
    test('Token A does not see any Tenant B tiles', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/tiles?locale=de`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.tenantA}`,
          Accept: 'application/json',
        },
      });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();
      for (const tile of data.data) {
        const title = tile.title ?? '';
        expect(title).not.toContain('B-');
      }
    });

    test('Token B does not see any Tenant A tiles', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/tiles?locale=de`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.tenantB}`,
          Accept: 'application/json',
        },
      });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();
      for (const tile of data.data) {
        const title = tile.title ?? '';
        expect(title).not.toContain('A-');
      }
    });

    test('Tenant A and Tenant B return different tile counts or content', async ({ request }) => {
      const [responseA, responseB] = await Promise.all([
        request.get(`${BASE_URL}/api/tiles?locale=de`, {
          headers: {
            Authorization: `Bearer ${fixtures.tokens.tenantA}`,
            Accept: 'application/json',
          },
        }),
        request.get(`${BASE_URL}/api/tiles?locale=de`, {
          headers: {
            Authorization: `Bearer ${fixtures.tokens.tenantB}`,
            Accept: 'application/json',
          },
        }),
      ]);

      expect(responseA.ok()).toBeTruthy();
      expect(responseB.ok()).toBeTruthy();

      const dataA = await responseA.json();
      const dataB = await responseB.json();

      // Both tenants should have tiles
      expect(dataA.data.length).toBeGreaterThan(0);
      expect(dataB.data.length).toBeGreaterThan(0);

      // Tile IDs should not overlap
      const idsA = dataA.data.map((t: { id: number }) => t.id);
      const idsB = dataB.data.map((t: { id: number }) => t.id);
      const overlap = idsA.filter((id: number) => idsB.includes(id));

      expect(overlap).toHaveLength(0);
    });
  });
});
