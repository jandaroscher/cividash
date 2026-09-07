# E2E Tests: Domain-based Tenant Resolution

This document describes how to run the Playwright-based E2E tests for domain-based tenant resolution.

## Overview

These tests verify the `ResolveTenantFromRequest` middleware behavior, which cannot be reliably tested with Laravel's built-in test client (the `getHost()` method doesn't work as expected in feature tests).

The tests use Playwright's API testing capabilities (no browser UI) to make real HTTP requests against a running ddev environment.

### What is tested

1. Domain to tenant mapping: requests to `a.open-source-dashboard.ddev.site` resolve to tenant A.
2. www. prefix stripping: requests to `www.a.open-source-dashboard.ddev.site` resolve to tenant A.
3. Precedence (token, then domain, then default): a token for tenant A used on tenant B's domain resolves to tenant A.
4. Default fallback: unknown domains without tokens fall back to the default tenant.

## Prerequisites

### 1. ddev running

```bash
ddev start
```

### 2. ddev hostnames configured

The `.ddev/config.yaml` should include:

```yaml
additional_hostnames:
  - a
  - b
additional_fqdns:
  - www.a.open-source-dashboard.ddev.site
```

After changing the config, restart ddev:

```bash
ddev restart
```

### 3. Node dependencies installed

```bash
npm install
```

### 4. Playwright browsers (optional, not needed for API tests)

```bash
npx playwright install
```

## Running the Tests

### Seed test data

Before running tests, seed the deterministic test fixtures:

```bash
npm run test:e2e:seed
# or directly:
ddev exec php artisan e2e:seed-tenant-resolution --json
```

This creates:
- Default tenant (`slug=default`)
- Tenant A (`slug=tenant-a`, `domain=a.open-source-dashboard.ddev.site`)
- Tenant B (`slug=tenant-b`, `domain=b.open-source-dashboard.ddev.site`)
- E2E test user with Sanctum tokens for both tenants

### Run tests

```bash
npm run test:e2e
```

Or with more verbose output:

```bash
npx playwright test --reporter=list
```

### View HTML report

After running tests, view the HTML report:

```bash
npx playwright show-report
```

## Test Structure

```
tests/e2e/
└── tenant-resolution.spec.ts   # All tenant resolution tests
```

### Test file organization

- Domain to tenant mapping: basic domain-to-tenant resolution
- www. prefix stripping: verifies www. is stripped before domain lookup
- Precedence (token, then domain, then default): token always wins over domain
- Default fallback: unknown domains resolve to default tenant
- API response structure: verifies /api/config/tenant response format

## CI Integration

For CI environments, ensure:

1. ddev or equivalent Docker setup is available
2. Database is migrated: `ddev exec php artisan migrate`
3. Test data is seeded before running tests
4. Network access to ddev URLs is available

Example CI workflow:

```yaml
- name: Start ddev
  run: ddev start

- name: Run migrations
  run: ddev exec php artisan migrate

- name: Seed E2E data
  run: npm run test:e2e:seed

- name: Run E2E tests
  run: npm run test:e2e
```

## Troubleshooting

### "Connection refused" errors

- Ensure ddev is running: `ddev status`
- Check ddev logs: `ddev logs`

### "www subdomain not reachable"

The www.a.* subdomain test may be skipped if:
- `additional_fqdns` is not configured in `.ddev/config.yaml`
- ddev hasn't been restarted after config changes

Fix: Add the FQDN to config and run `ddev restart`

### Seed command not found

- Clear Laravel's command cache: `ddev exec php artisan clear-compiled`
- Dump autoloader: `ddev exec composer dump-autoload`

### SSL certificate errors

Playwright is configured to ignore HTTPS errors (`ignoreHTTPSErrors: true`) since ddev uses self-signed certificates.

## Related Documentation

- [Admin API Documentation](../api/admin-api.md) - Tenant resolution middleware details
- [Tenant Resolution Middleware](../../app/Http/Middleware/ResolveTenantFromRequest.php) - Implementation
