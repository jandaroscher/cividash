# Multi-tenancy

CiviDash runs all dashboards in a single database. A dashboard in the product is a `Tenant` in code.

- **Tenant model**: `App\Models\Tenant` with `name`, `slug` (unique, read-only after creation), `domain` (unique, nullable), `frontend_base_url` (nullable), and the `tenant_user` pivot. A user can have a `default_tenant_id`.
- **Filament tenancy**: the admin panel is tenant-aware (`AdminPanelProvider` with `->tenant(Tenant::class)`), including the `RegisterTenant` and `EditTenantProfile` pages.
- **Tenant pages**:
  - `RegisterTenant`: creates a tenant, links the current user, sets default_tenant if empty.
  - `EditTenantProfile`: edits name, domain, and frontend URL of the active tenant. The slug is read-only.
- **Scoping**: tenant-bound models use the `App\Models\Concerns\BelongsToTenant` trait (sets `tenant_id` on create and scopes queries when Filament provides a tenant).
- **Backfill**: the artisan command `ddev exec php artisan tenancy:backfill` creates the default tenant (slug `default`) and assigns all existing data as well as users (incl. default_tenant) to it.
- **Seeder**: `TenantSeeder` creates the demo tenant "Stadt Regensburg", links the first user, and calls the backfill.
- **Tests**: feature tests under `tests/Feature/Tenancy` (schema/backfill/seeder/scoping) and unit tests `tests/Unit/UserTenancyTest` for user contracts.

### Usage
1) **Local setup**:  
   ```bash
   ddev start
   ddev exec composer install
   ddev exec php artisan migrate
   ddev exec php artisan tenancy:backfill
   ddev exec php artisan db:seed --class=TenantSeeder
   ```
2) **Create a tenant (UI)**: create a tenant in the Filament admin via `RegisterTenant`. The user is linked, default_tenant is set.
3) **Switch tenant**: choose between tenants in the Filament tenant switcher; data is filtered via the global scope.
4) **New data**: created records automatically get `tenant_id` from the active tenant.

### Notes
- The implementation is single-DB tenancy; multi-DB stays out of scope.
- Direct access to another tenant's IDs is prevented by scopes (404/empty).
- Seeds/migrations without a Filament tenant context bypass the scope (the backfill relies on this).
- **Tenant isolation**: outside console commands (see below), the global scope filters by the tenant resolved via `ResolvesCurrentTenant::resolveTenant()`:
  - **Filament context**: uses `Filament::getTenant()`
  - **API with token**: via the `ResolveTenantFromRequest` middleware's `resolved_tenant` request attribute, using `tenant_id` from `personal_access_tokens`
  - **API via domain**: also via that request attribute, using the `tenants.domain` mapping
  - **Without any of the above**: `BelongsToTenant`'s own fallback uses the default tenant (slug `default`) and logs a warning (`Log::warning`) — separate from the middleware's own default-tenant priority described below, which logs at debug level, not a warning
  - **Console commands**: the scope is skipped (commands should use `withoutGlobalScope('tenant')` if needed)
- **API usage**: API requests use `ResolveTenantFromRequest` first. If it does not attach a tenant, `BelongsToTenant` can use the legacy `tenant` request parameter or `X-Tenant` header fallback, subject to the resolver's access checks (see [Tenant Resolution](../api/tenant-resolution.md)):
  - **Token-based**: bearer token with `tenant_id` (for API clients)
  - **Domain-based**: the request host is checked against `tenants.domain` (for SPAs)

### Domain configuration

Tenants can be linked to a domain:

```php
$tenant->update([
    'domain' => 'regensburg.example.org',
    'frontend_base_url' => 'https://regensburg.example.org'
]);
```

### Tenant-scoped API tokens

API tokens can be bound to a tenant:

```php
$token = $user->createToken('API Token', ['public-read']);
$token->accessToken->tenant_id = $tenant->id;
$token->accessToken->save();
```
