import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

/**
 * E2E Tests for API Key Lifecycle
 *
 * Verifies that:
 * - A valid API token can authenticate and access data
 * - After revocation, the same token returns 401
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

test.describe('API Key Lifecycle', () => {
  test.beforeAll(async () => {
    try {
      const artisanCmd = process.env.E2E_ARTISAN_CMD ?? 'ddev exec php artisan';
      const output = execSync(`${artisanCmd} e2e:seed-full --clean --json`, {
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

  test.describe('Valid token access', () => {
    test('lifecycle token can access /api/tiles', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/tiles`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.lifecycle}`,
          Accept: 'application/json',
        },
      });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();
      expect(data.data).toBeDefined();
    });

    test('lifecycle token can access /api/config/tenant', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/config/tenant`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.lifecycle}`,
          Accept: 'application/json',
        },
      });

      expect(response.ok()).toBeTruthy();

      const data = await response.json();
      expect(data.data.slug).toBe('e2e-tenant-a');
      expect(data.data.resolved_by).toBe('token');
    });
  });

  test.describe('Token revocation', () => {
    test('revoked token returns 401', async ({ request }) => {
      // Step 1: Verify the lifecycle token works before revocation
      const beforeResponse = await request.get(`${BASE_URL}/api/tiles`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.lifecycle}`,
          Accept: 'application/json',
        },
      });
      expect(beforeResponse.ok()).toBeTruthy();

      // Step 2: Revoke the token via artisan tinker
      // We delete the token directly from the database
      execSync(
        `${process.env.E2E_ARTISAN_CMD ?? 'ddev exec php artisan'} tinker --execute="\\Laravel\\Sanctum\\PersonalAccessToken::find(${fixtures.tokens.lifecycleId})?->delete();"`,
        {
          encoding: 'utf-8',
          cwd: process.cwd(),
        }
      );

      // Step 3: Verify the revoked token returns 401
      const afterResponse = await request.get(`${BASE_URL}/api/tiles`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.lifecycle}`,
          Accept: 'application/json',
        },
      });

      // The token should no longer be valid
      // Public routes with resolve.tenant may still work (falling back to default tenant),
      // but the token-based resolution should fail. Let's check /api/user which requires auth.
      const userResponse = await request.get(`${BASE_URL}/api/user`, {
        headers: {
          Authorization: `Bearer ${fixtures.tokens.lifecycle}`,
          Accept: 'application/json',
        },
      });

      expect(userResponse.status()).toBe(401);
    });
  });

  test.describe('Invalid token handling', () => {
    test('completely invalid token returns 401 on protected route', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/user`, {
        headers: {
          Authorization: 'Bearer invalid-token-that-does-not-exist',
          Accept: 'application/json',
        },
      });

      expect(response.status()).toBe(401);
    });

    test('empty Authorization header returns 401 on protected route', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/user`, {
        headers: {
          Authorization: '',
          Accept: 'application/json',
        },
      });

      expect(response.status()).toBe(401);
    });

    test('missing Authorization header returns 401 on protected route', async ({ request }) => {
      const response = await request.get(`${BASE_URL}/api/user`, {
        headers: {
          Accept: 'application/json',
        },
      });

      expect(response.status()).toBe(401);
    });
  });
});
