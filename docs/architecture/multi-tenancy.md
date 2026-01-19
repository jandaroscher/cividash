## Multi-Tenancy (Single-DB) – /

- **Tenant-Modell**: `App\Models\Tenant` mit `name`, `slug` (unique, read-only nach Erstellung), `domain` (unique, nullable), `frontend_base_url` (nullable) und Pivot `tenant_user`. Nutzer kann einen `default_tenant_id` haben.
- **Filament Tenancy**: Admin-Panel ist tenant-fähig (`AdminPanelProvider` mit `->tenant(Tenant::class)`), inkl. Seiten `RegisterTenant` und `EditTenantProfile`.
- **Tenant-Seiten**:
  - `RegisterTenant`: legt Mandant an, verknüpft aktuellen User, setzt default_tenant falls leer.
  - `EditTenantProfile`: Bearbeiten von Name, Domain und Frontend-URL des aktiven Tenants. Der Slug ist read-only.
- **Scoping**: Tenant-gebundene Modelle nutzen das Trait `App\Models\Concerns\BelongsToTenant` (setzt `tenant_id` bei Create und scoped Abfragen, wenn Filament einen Tenant liefert).
- **Backfill**: Artisan-Command `ddev exec php artisan tenancy:backfill` erstellt Default-Tenant (Slug `default`) und weist alle Bestandsdaten sowie User (inkl. default_tenant) zu.
- **Seeder**: `TenantSeeder` erzeugt Demo-Tenant „Stadt Regensburg“, verknüpft ersten User und ruft Backfill auf.
- **Tests**: Feature-Tests unter `tests/Feature/Tenancy` (Schema/Backfill/Seeder/Scoping) und Unit-Tests `tests/Unit/UserTenancyTest` für User-Contracts.

### Nutzung
1) **Lokale Einrichtung**:  
   ```bash
   ddev start
   ddev exec composer install
   ddev exec php artisan migrate
   ddev exec php artisan tenancy:backfill
   ddev exec php artisan db:seed --class=TenantSeeder
   ```
2) **Tenant anlegen (UI)**: Im Filament-Admin via `RegisterTenant` einen Mandanten erstellen. Der User wird verknüpft, default_tenant gesetzt.  
3) **Tenant wechseln**: Im Filament-Tenant-Switcher zwischen Mandanten wählen; Daten werden per Global Scope gefiltert.  
4) **Neue Daten**: Erstellte Records erhalten automatisch `tenant_id` aus dem aktiven Tenant.

### Hinweise
- Implementation ist Single-DB-Tenancy; Multi-DB bleibt out of scope.
- Direkter Zugriff auf fremde Tenant-IDs wird durch Scopes verhindert (404/leer).
- Bei Seeds/Migrationen ohne Filament-Tenant-Kontext greift kein Scope (Backfill nutzt das).
- **Tenant-Isolation**: Der Global Scope filtert immer nach Tenant:
  - **Filament-Kontext**: Verwendet `Filament::getTenant()`
  - **API mit Token**: Verwendet `tenant_id` aus `personal_access_tokens` (neu in)
  - **API via Domain**: Verwendet `tenants.domain` Mapping (neu in)
  - **Ohne Tenant-Kontext**: Verwendet Default-Tenant (Slug `default`) mit Warnung im Log
  - **Console-Commands**: Scope wird übersprungen (Commands sollten `withoutGlobalScope('tenant')` verwenden, wenn nötig)
- **API-Nutzung (veraltet)**: Die alten Methoden funktionieren weiterhin:
  - Query-Parameter: `/api/tiles?tenant=stadt-regensburg`
  - Header: `X-Tenant: stadt-regensburg`
- **API-Nutzung (empfohlen)**: Neue Methoden (siehe [Tenant Resolution](../api/tenant-resolution.md)):
  - **Token-basiert**: Bearer Token mit `tenant_id` (für API-Clients)
  - **Domain-basiert**: Request-Host wird gegen `tenants.domain` geprüft (für SPAs)

### Domain-Konfiguration (neu)

Tenants können mit einer Domain verknüpft werden:

```php
$tenant->update([
    'domain' => 'regensburg.example.org',
    'frontend_base_url' => 'https://regensburg.example.org'
]);
```

### Tenant-scoped API Tokens (neu)

API-Tokens können an einen Tenant gebunden werden:

```php
$token = $user->createToken('API Token', ['public-read']);
$token->accessToken->tenant_id = $tenant->id;
$token->accessToken->save();
```
