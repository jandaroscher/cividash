# Tenant Resolution für Public API –

> **Status:** Implementiert (v1.0)  
> **Middleware:** `ResolveTenantFromRequest`

---

## Übersicht

Die Public API (GET-Endpoints) ist ohne Authentifizierung zugänglich, benötigt aber einen Tenant-Kontext für die Daten-Isolation. Die Middleware `ResolveTenantFromRequest` löst den Tenant automatisch auf.

---

## Auflösungs-Priorität

Die Middleware versucht den Tenant in folgender Reihenfolge aufzulösen:

```
1. Token (Bearer Token mit tenant_id)
   ↓ falls nicht vorhanden
2. Domain (Request-Host → tenants.domain)
   ↓ falls kein Match
3. Default (Tenant mit slug='default')
```

---

## 1. Token-basierte Auflösung

Wenn ein gültiger Bearer Token vorhanden ist und dieser eine `tenant_id` hat:

```http
GET /api/tiles
Authorization: Bearer 1|abc123...
```

**Voraussetzung:** Token wurde mit `tenant_id` erstellt:

```php
$token = $user->createToken('API Token', ['public-read']);
$token->accessToken->tenant_id = $tenant->id;
$token->accessToken->save();
```

**Vorteil:** Ermöglicht API-Zugriff auf spezifischen Tenant unabhängig von der Domain.

---

## 2. Domain-basierte Auflösung

Wenn kein Token vorhanden ist, wird der Request-Host gegen `tenants.domain` geprüft:

```http
GET /api/tiles
Host: regensburg.example.org
```

**Tenant-Konfiguration:**

| Tenant | Domain |
|--------|--------|
| Stadt Regensburg | `regensburg.example.org` |
| Demo City | `demo-city.example.org` |

**Hinweise:**
- `www.`-Prefix wird automatisch entfernt
- Domain-Matching ist case-insensitive

---

## 3. Default-Fallback

Wenn weder Token noch Domain matchen:

```http
GET /api/tiles
Host: unknown.example.com
# → Verwendet Tenant mit slug='default'
```

---

## Config-Endpoint

Der Endpoint `/api/config/tenant` zeigt den aufgelösten Tenant:

```http
GET /api/config/tenant
Host: regensburg.example.org
```

**Response:**
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

**`resolved_by` Werte:**
- `token` – Aufgelöst via Bearer Token
- `domain` – Aufgelöst via Request-Host
- `default` – Fallback zum Default-Tenant

---

## Datenbank-Schema

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

## Integration mit BelongsToTenant

Das Trait `BelongsToTenant` nutzt den aufgelösten Tenant automatisch:

```php
// ResolveTenantFromRequest setzt:
$request->attributes->set('resolved_tenant', $tenant);

// BelongsToTenant prüft:
protected static function resolveTenant(): ?Tenant
{
    // 1. Filament-Kontext
    if ($tenant = Filament::getTenant()) return $tenant;
    
    // 2. Middleware-aufgelöster Tenant
    if ($request->attributes->has('resolved_tenant')) {
        return $request->attributes->get('resolved_tenant');
    }
    
    // 3. Legacy: Query-Parameter / Header
    // ...
}
```

---

## Anwendungsfälle

### 1. SPA mit Domain-Routing

```
regensburg.example.org → Tenant "Stadt Regensburg"
demo-city.example.org  → Tenant "Demo City"
```

Das Frontend ruft `/api/tiles` auf, die Middleware erkennt den Tenant via Domain.

### 2. API-Client mit Token

```bash
# Client hat Token für spezifischen Tenant
curl -H "Authorization: Bearer $TOKEN" \
  https://api.example.org/api/tiles
```

Der Token enthält `tenant_id`, daher ist die Domain irrelevant.

### 3. Entwicklung / Testing

```bash
# Ohne spezielle Konfiguration → Default-Tenant
curl http://localhost/api/tiles
```

---

## Logging

Die Middleware loggt die Auflösung:

```
[INFO] Tenant resolved via token: stadt-regensburg (ID: 2)
[INFO] Tenant resolved via domain: demo-city.example.org → demo-city (ID: 3)
[WARNING] No tenant found, using default: default (ID: 1)
```

---

## Geschützte Routen

Alle Public GET-Routes nutzen die Middleware:

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

## Siehe auch

- [Admin API](./admin-api.md) – Authentifizierte API
- [Multi-Tenancy](../architecture/multi-tenancy.md) – Tenant-Konzept
