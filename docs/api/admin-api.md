# Admin API

---

## OpenAPI documentation

The complete, interactive API documentation is available at:

- HTML docs: `/docs/` (e.g. `https://example.com/docs/`)
- OpenAPI 3.0 spec: `/docs/openapi.yaml`
- Postman collection: `/docs/collection.json`

> The documentation is generated as static files and needs no Scribe package at runtime.

### Regenerating the documentation (locally)

```bash
ddev exec php artisan scribe:generate
```

There is no automatic regeneration in CI; run this command manually after changing API endpoints or annotations.

---

## Overview

The Admin API allows programmatic management of tiles, years, metrics, and configurations. All endpoints authenticate via Laravel Sanctum (bearer token), authorize via the token ability (`admin-api` or `*`), and automatically scope operations to the active tenant.

---

## Authentication & authorization

### Prerequisites for API access

1. Token ability: a personal access token must have the `admin-api` or `*` ability. See below for session-authenticated requests.
2. Tenant context: an explicit, non-default tenant context must be resolvable, either from the token's `tenant_id` or from a domain match (see [Tenant isolation](#tenant-isolation)). The token does not strictly need its own `tenant_id` if the request's domain already resolves a tenant; the API only rejects the request with 400 when neither source resolves a tenant.

> Session authentication (Filament cookie login without a bearer token) is currently not enabled for `/api/*`, since neither `statefulApi()` nor the session middleware is registered on the `api` route group (`bootstrap/app.php`); a Filament session cookie therefore does not authenticate here. Should this be enabled in the future, `admin.api` additionally requires the user's `is_admin` role on a session login (Sanctum's `TransientToken`, which reports every ability as present), instead of relying on the token ability.

### Creating a token (code example)

```php
// In Filament Admin or via Tinker
$user = User::find(1);

// Create a token WITH tenant context (REQUIRED for the Admin API)
$tenant = Tenant::find(1); // or $user->tenants()->first()
$token = $user->createToken('Admin API Token', ['admin-api']);
$token->accessToken->tenant_id = $tenant->id;
$token->accessToken->save();

echo $token->plainTextToken;
// => "1|abc123def456..."
```

> This token has its own `tenant_id`. A token without one still works for the Admin API as long as the request's domain resolves a tenant; requests that resolve to neither are rejected with 400 Bad Request.

### Request header

```http
Authorization: Bearer 1|abc123def456...
```

### HTTP status codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Resource created |
| 204 | Successfully deleted (no content) |
| 400 | Tenant context missing – token without `tenant_id` or no domain resolution |
| 401 | Not authenticated |
| 403 | Not authorized (missing permission) |
| 404 | Resource not found (or belongs to another tenant) |
| 422 | Validation error |

---

## Endpoints

### Tiles

| Method | Endpoint | Description |
|--------|----------|--------------|
| `POST` | `/api/admin/tiles` | Create a new tile |
| `PATCH` | `/api/admin/tiles/{id}` | Update a tile |
| `DELETE` | `/api/admin/tiles/{id}` | Delete a tile |

#### POST /api/admin/tiles

```json
{
  "title": { "de": "Neue Kachel", "en": "New Tile" },
  "description": { "de": "Beschreibung", "en": "Description" },
  "icon": "chart-bar"
}
```

Response (201):
```json
{
  "data": {
    "id": 42,
    "tenant_id": 1,
    "title": { "de": "Neue Kachel", "en": "New Tile" },
    ...
  }
}
```

#### PATCH /api/admin/tiles/{id}

Partial update – only the fields provided are updated.

```json
{
  "title": { "de": "Aktualisierter Titel" }
}
```

> `tenant_id` cannot be changed via PATCH; the field is ignored.

---

### Time periods

| Method | Endpoint | Description |
|--------|----------|--------------|
| `POST` | `/api/admin/time-periods` | Create a time period |
| `PATCH` | `/api/admin/time-periods/{id}` | Update a time period |
| `DELETE` | `/api/admin/time-periods/{id}` | Delete a time period |

#### POST /api/admin/time-periods

```json
{
  "tile_id": 42,
  "period_key": "2024"
}
```

---

### Metric definitions

| Method | Endpoint | Description |
|--------|----------|--------------|
| `POST` | `/api/admin/metric-definitions` | Create a new metric definition |
| `PATCH` | `/api/admin/metric-definitions/{id}` | Update a definition |
| `DELETE` | `/api/admin/metric-definitions/{id}` | Delete a definition |

#### POST /api/admin/metric-definitions

```json
{
  "tile_id": 42,
  "key": "population",
  "label": { "de": "Einwohnerzahl", "en": "Population" },
  "unit": { "de": "Personen", "en": "People" },
  "format": "number"
}
```

---

### Metric values

| Method | Endpoint | Description |
|--------|----------|--------------|
| `POST` | `/api/admin/metric-values` | Create a new metric value |
| `PATCH` | `/api/admin/metric-values/{id}` | Update a value |
| `DELETE` | `/api/admin/metric-values/{id}` | Delete a value |

#### POST /api/admin/metric-values

```json
{
  "metric_definition_id": 5,
  "time_period_id": 10,
  "value": 150000
}
```

---

### Branding configuration

| Method | Endpoint | Description |
|--------|----------|--------------|
| `POST` | `/api/admin/config/branding` | Set branding completely |
| `PATCH` | `/api/admin/config/branding` | Partially update branding |

#### PATCH /api/admin/config/branding

```json
{
  "primary_color": "#0d47a1",
  "header_background_color": "#FFFFFF"
}
```

---

## Tenant isolation

### Explicit tenant context (required)

Admin operations require an explicit tenant context: either token-based (`personal_access_tokens.tenant_id` must be set) or domain-based (the request host must match `tenants.domain`).

Without an explicit tenant context, the API responds with 400 Bad Request:

```json
{
  "message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.",
  "error": "missing_tenant_context"
}
```

### Automatic scoping

All admin operations are automatically scoped to the resolved tenant. On create, `tenant_id` is set automatically from the request context, not from the payload. On read, update, and delete, only resources of the own tenant are visible.

### Cross-tenant access

Attempts to modify resources of another tenant end in 404 Not Found, not 403:

```bash
# User belongs to Tenant A, tries to edit a tile of Tenant B
PATCH /api/admin/tiles/999
# => 404 Not Found
```

### tenant_id manipulation

The `tenant_id` field is automatically stripped from all payloads:

```json
// Request
{ "title": { "de": "Test" }, "tenant_id": 999 }

// Actually stored
{ "title": { "de": "Test" }, "tenant_id": 1 }  // user's tenant
```

---

## Middleware stack

```php
Route::middleware(['auth:sanctum', 'admin.api', 'resolve.tenant', 'admin.tenant'])
     ->prefix('admin')
     ->group(...)
```

1. `auth:sanctum` verifies the bearer token, 401 on missing or invalid token.
2. `admin.api` (`EnsureAdminApiAccess`) checks a personal access token for the `admin-api` or `*` ability, otherwise 403. As a safeguard in case `/api/*` is opened to session logins in the future (see note above), it additionally requires `is_admin` for any user without a PAT (Sanctum's `TransientToken`), otherwise 403 as well.
3. `resolve.tenant` (`ResolveTenantFromRequest`) resolves the tenant, with priority token tenant_id over domain match, falling back to the default tenant when neither matches, and sets `resolved_tenant` as well as `resolved_tenant_by` on the request.
4. `admin.tenant` (`EnsureAdminTenantResolved`) checks whether `resolved_tenant_by` is not `default`, and responds with 400 if there is no explicit tenant context — this is where the default-tenant fallback from step 3 gets rejected for admin routes.

> Difference from the public API: the public API allows a fallback to the default tenant. The Admin API requires an explicit tenant context (token tenant_id or domain match).

---

## Example: full workflow

```bash
# 1. Create a token with admin-api ability + tenant_id (one-off, see above)
# The token must be assigned a tenant_id on the backend!

# 2. Create a new tile
curl -X POST https://example.com/api/admin/tiles \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"title": {"de": "Energie", "en": "Energy"}}'

# 3. Add a time period
curl -X POST https://example.com/api/admin/time-periods \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"tile_id": 42, "period_key": "2024"}'

# 4. Create a metric definition
curl -X POST https://example.com/api/admin/metric-definitions \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"tile_id": 42, "key": "co2", "label": {"de": "CO2-Ausstoß"}}'

# 5. Set a metric value
curl -X POST https://example.com/api/admin/metric-values \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"metric_definition_id": 1, "time_period_id": 1, "value": 1250}'
```

> The token or the request domain must resolve a tenant. Requests that resolve to neither:
> ```bash
> # Token without tenant_id, and no domain match, results in 400
> {"message": "Tenant context required for admin API...", "error": "missing_tenant_context"}
> ```

---

## Test coverage

The Admin API is thoroughly tested:

| Test class | Tests | Focus |
|------------|-------|-------|
| `AdminTileApiTest` | 12 tests | CRUD + tenant scoping |
| `AdminTimePeriodApiTest` | 12 tests | CRUD + tenant scoping |
| `AdminMetricDefinitionApiTest` | 13 tests | CRUD + tenant scoping |
| `AdminMetricValueApiTest` | 13 tests | CRUD + tenant scoping |
| `AdminApiPermissionTest` | 7 tests | Auth + abilities |
| `TenantResolutionApiTest` | 9 tests | Token/domain resolution |
| `BrandingConfigApiTest` | 15 tests | Config endpoints |

Over 80 admin-specific tests in total. Every admin resource test includes an explicit 400 test for missing tenant context.

---

## See also

- [Multi-Tenancy](../architecture/multi-tenancy.md) – tenant concept
- [Tenant Resolution](./tenant-resolution.md) – public API tenant resolution
