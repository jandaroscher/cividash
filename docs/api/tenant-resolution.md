# Tenant Resolution for the Public API

> Middleware: `ResolveTenantFromRequest`

---

## Overview

The public API (GET endpoints) is accessible without authentication but needs a tenant context for data isolation. The `ResolveTenantFromRequest` middleware resolves the tenant automatically.

---

## Resolution priority

The middleware tries to resolve the tenant in the following order:

```
1. Token (bearer token with tenant_id)
   ↓ if not present
2. Domain (request host → tenants.domain)
   ↓ if no match
3. Default (tenant with slug='default')
```

---

## 1. Token-based resolution

If a valid bearer token is present and it has a `tenant_id`:

```http
GET /api/tiles
Authorization: Bearer 1|abc123...
```

Prerequisite: the token was created with `tenant_id`.

```php
$token = $user->createToken('API Token', ['public-read']);
$token->accessToken->tenant_id = $tenant->id;
$token->accessToken->save();
```

This allows API access to a specific tenant regardless of the domain.

---

## 2. Domain-based resolution

If no tenant is resolved from the token — no bearer token, an invalid token, a token without `tenant_id`, or a `tenant_id` whose tenant no longer exists — the request host is checked against `tenants.domain`:

```http
GET /api/tiles
Host: regensburg.example.org
```

Tenant configuration:

| Tenant | Domain |
|--------|--------|
| Stadt Regensburg | `regensburg.example.org` |
| Demo City | `demo-city.example.org` |

A `www.` prefix is stripped automatically, and domain matching is case-insensitive.

---

## 3. Default fallback

If neither token nor domain match:

```http
GET /api/tiles
Host: unknown.example.com
# → uses the tenant with slug='default'
```

---

## Config endpoint

The `/api/config/tenant` endpoint shows the resolved tenant:

```http
GET /api/config/tenant
Host: regensburg.example.org
```

Response:
```json
{
  "data": {
    "slug": "stadt-regensburg",
    "name": "Stadt Regensburg",
    "domain": "regensburg.example.org",
    "frontend_base_url": "https://regensburg.example.org",
    "resolved_by": "domain"
  }
}
```

`resolved_by` values:
- `token` – resolved via bearer token
- `domain` – resolved via request host
- `default` – fallback to the default tenant

---

## Database schema

### personal_access_tokens

```sql
ALTER TABLE personal_access_tokens 
ADD COLUMN tenant_id BIGINT NULL
REFERENCES tenants(id) ON DELETE CASCADE;
```

### tenants

```sql
ALTER TABLE tenants 
ADD COLUMN domain VARCHAR(255) NULL UNIQUE,
ADD COLUMN frontend_base_url VARCHAR(255) NULL;
```

---

## Integration with BelongsToTenant

The `BelongsToTenant` trait uses the resolved tenant automatically:

```php
// ResolveTenantFromRequest sets:
$request->attributes->set('resolved_tenant', $tenant);

// BelongsToTenant checks:
protected static function resolveTenant(): ?Tenant
{
    // 1. Filament context
    if ($tenant = Filament::getTenant()) return $tenant;
    
    // 2. Tenant resolved by the middleware
    if ($request->attributes->has('resolved_tenant')) {
        return $request->attributes->get('resolved_tenant');
    }
    
    // 3. Legacy: query parameter / header
    // ...
}
```

---

## Use cases

### 1. SPA with domain routing

```
regensburg.example.org → Tenant "Stadt Regensburg"
demo-city.example.org  → Tenant "Demo City"
```

The frontend calls `/api/tiles`, and the middleware recognizes the tenant via the domain.

### 2. API client with token

```bash
# Client has a token for a specific tenant
curl -H "Authorization: Bearer $TOKEN" \
  https://api.example.org/api/tiles
```

The token contains `tenant_id`, so the domain is irrelevant.

### 3. Development / testing

```bash
# Without special configuration → default tenant
curl http://localhost/api/tiles
```

---

## Logging

The middleware logs every resolution as a single structured `debug`-level entry on the `daily` channel, for all three outcomes (token, domain, and default fallback alike) — there is no separate warning for the default case:

```php
Log::channel('daily')->debug('Tenant resolved', [
    'resolved_by' => $resolvedBy,    // 'token' | 'domain' | 'default'
    'tenant_id' => $tenant?->id,
    'tenant_slug' => $tenant?->slug,
    'token_id' => $tokenId,
    'route' => $request->path(),
    'host' => $request->getHost(),
]);
```

---

## Protected routes

All public GET routes use the middleware:

```php
Route::middleware('resolve.tenant')->group(function () {
    Route::get('/tiles', ...);
    Route::get('/filters', ...);
    Route::get('/config/branding', ...);
    Route::get('/config/tenant', ...);
    // ...
});
```

---

## See also

- [Admin API](./admin-api.md) – authenticated API
- [Multi-Tenancy](../architecture/multi-tenancy.md) – tenant concept
