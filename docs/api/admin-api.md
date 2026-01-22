# Admin API –

> **Status:** Implementiert (v1.0)  
> **Branch:** `feature/admin-api`  

---

## OpenAPI Dokumentation

Die vollständige, interaktive API-Dokumentation ist verfügbar unter:

- **HTML-Docs:** `/docs/` (z.B. `https://example.com/docs/`)
- **OpenAPI 3.0 Spec:** `/docs/openapi.yaml`
- **Postman Collection:** `/docs/collection.json`

> **Hinweis:** Die Dokumentation wird als statische Dateien generiert und benötigt kein Scribe-Package zur Laufzeit.

### Regenerieren der Dokumentation (lokal)

```bash
ddev exec php artisan scribe:generate
```

Die Dokumentation wird automatisch im CI bei jedem Deployment neu generiert.

---

## Übersicht

Die Admin API ermöglicht das programmgesteuerte Management von Tiles, Jahren, Metriken und Konfigurationen. Alle Endpoints sind:

- **Authentifiziert** via Laravel Sanctum (Bearer Token)
- **Autorisiert** via `admin_api_enabled` User-Flag + Token-Ability
- **Tenant-scoped** – Operationen sind automatisch auf den aktiven Tenant beschränkt

---

## Authentifizierung & Autorisierung

### Voraussetzungen für API-Zugriff

1. **User-Flag**: `admin_api_enabled = true` auf dem User-Model
2. **Token-Ability**: Token muss `admin-api` oder `*` Ability haben

### Token erstellen (Code-Beispiel)

```php
// Im Filament Admin oder via Tinker
$user = User::find(1);
$user->forceFill(['admin_api_enabled' => true])->save();

// Token MIT Tenant-Kontext erstellen (ERFORDERLICH für Admin API)
$tenant = Tenant::find(1); // oder $user->tenants()->first()
$token = $user->createToken('Admin API Token', ['admin-api']);
$token->accessToken->tenant_id = $tenant->id;
$token->accessToken->save();

echo $token->plainTextToken;
// => "1|abc123def456..."
```

> **Wichtig:** Der Token MUSS eine `tenant_id` haben. Tokens ohne Tenant-Kontext werden mit **400 Bad Request** abgelehnt.

### Request-Header

```http
Authorization: Bearer 1|abc123def456...
```

### HTTP Status Codes

| Code | Bedeutung |
|------|-----------|
| 200 | Erfolg |
| 201 | Resource erstellt |
| 204 | Erfolgreich gelöscht (kein Content) |
| 400 | **Tenant-Kontext fehlt** – Token ohne `tenant_id` oder keine Domain-Auflösung |
| 401 | Nicht authentifiziert |
| 403 | Nicht autorisiert (fehlende Permission) |
| 404 | Resource nicht gefunden (oder anderer Tenant) |
| 422 | Validierungsfehler |

---

## Endpoints

### Tiles

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| `POST` | `/api/admin/tiles` | Neue Tile erstellen |
| `PATCH` | `/api/admin/tiles/{id}` | Tile aktualisieren |
| `DELETE` | `/api/admin/tiles/{id}` | Tile löschen |

#### POST /api/admin/tiles

```json
{
  "title": { "de": "Neue Kachel", "en": "New Tile" },
  "description": { "de": "Beschreibung", "en": "Description" },
  "icon": "chart-bar"
}
```

**Response (201):**
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

Partial Update – nur übergebene Felder werden aktualisiert.

```json
{
  "title": { "de": "Aktualisierter Titel" }
}
```

> **Hinweis:** `tenant_id` kann nicht via PATCH geändert werden (wird ignoriert).

---

### Tile Years

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| `POST` | `/api/admin/tile-years` | Neues Jahr erstellen |
| `PATCH` | `/api/admin/tile-years/{id}` | Jahr aktualisieren |
| `DELETE` | `/api/admin/tile-years/{id}` | Jahr löschen |

#### POST /api/admin/tile-years

```json
{
  "tile_id": 42,
  "year": 2024
}
```

---

### Metric Definitions

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| `POST` | `/api/admin/metric-definitions` | Neue Metrik-Definition |
| `PATCH` | `/api/admin/metric-definitions/{id}` | Definition aktualisieren |
| `DELETE` | `/api/admin/metric-definitions/{id}` | Definition löschen |

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

### Metric Values

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| `POST` | `/api/admin/metric-values` | Neuen Metrik-Wert erstellen |
| `PATCH` | `/api/admin/metric-values/{id}` | Wert aktualisieren |
| `DELETE` | `/api/admin/metric-values/{id}` | Wert löschen |

#### POST /api/admin/metric-values

```json
{
  "metric_definition_id": 5,
  "tile_year_id": 10,
  "value": 150000
}
```

---

### Branding Configuration

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| `POST` | `/api/admin/config/branding` | Branding vollständig setzen |
| `PATCH` | `/api/admin/config/branding` | Branding partiell aktualisieren |

#### PATCH /api/admin/config/branding

```json
{
  "primary_color": "#0d47a1",
  "header_background_color": "#FFFFFF"
}
```

---

## Tenant-Isolation

### Expliziter Tenant-Kontext (Pflicht)

Admin-Operationen erfordern einen **expliziten Tenant-Kontext**:

- **Token-basiert:** `personal_access_tokens.tenant_id` muss gesetzt sein
- **Domain-basiert:** Request-Host muss mit `tenants.domain` matchen

**Ohne expliziten Tenant-Kontext → 400 Bad Request:**

```json
{
  "message": "Tenant context required for admin API. Provide a token with tenant_id or use a configured domain.",
  "error": "missing_tenant_context"
}
```

### Automatisches Scoping

Alle Admin-Operationen sind automatisch auf den aufgelösten Tenant beschränkt:

- **Create:** `tenant_id` wird automatisch aus dem Request-Kontext gesetzt (nicht aus Payload)
- **Read/Update/Delete:** Nur Ressourcen des eigenen Tenants sind sichtbar

### Cross-Tenant-Zugriff

Versuche, Ressourcen eines anderen Tenants zu modifizieren, resultieren in **404 Not Found** (nicht 403):

```bash
# User ist Tenant A, versucht Tile von Tenant B zu bearbeiten
PATCH /api/admin/tiles/999
# => 404 Not Found
```

### tenant_id Manipulation

Das Feld `tenant_id` wird aus allen Payloads **automatisch entfernt**:

```json
// Request
{ "title": { "de": "Test" }, "tenant_id": 999 }

// Tatsächlich gespeichert
{ "title": { "de": "Test" }, "tenant_id": 1 }  // User's Tenant
```

---

## Middleware-Stack

```
Route::middleware(['auth:sanctum', 'admin.api', 'resolve.tenant', 'admin.tenant'])
     ->prefix('admin')
     ->group(...)
```

1. **auth:sanctum** – Verifiziert Bearer Token → 401 bei fehlendem/ungültigem Token
2. **admin.api** (`EnsureAdminApiAccess`) – Prüft:
   - `$user->admin_api_enabled === true`
   - Token hat `admin-api` oder `*` Ability
   - → 403 bei fehlender Berechtigung
3. **resolve.tenant** (`ResolveTenantFromRequest`) – Löst Tenant auf:
   - Priorität: Token-tenant_id > Domain-Match > ~~Default~~
   - Setzt `resolved_tenant` + `resolved_tenant_by` auf Request
4. **admin.tenant** (`EnsureAdminTenantResolved`) – **Hardening:**
   - Prüft ob `resolved_tenant_by` NICHT `default` ist
   - → 400 wenn kein expliziter Tenant-Kontext vorhanden

> **Unterschied zur Public API:** Die Public API erlaubt Fallback auf den Default-Tenant. Die Admin API erfordert einen expliziten Tenant-Kontext (Token-tenant_id oder Domain-Match).

---

## Beispiel: Vollständiger Workflow

```bash
# 1. Token mit admin-api Ability + tenant_id erstellen (einmalig, siehe oben)
# Der Token muss im Backend mit einer tenant_id versehen werden!

# 2. Neue Tile erstellen
curl -X POST https://example.com/api/admin/tiles \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"title": {"de": "Energie", "en": "Energy"}}'

# 3. Jahr hinzufügen
curl -X POST https://example.com/api/admin/tile-years \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"tile_id": 42, "year": 2024}'

# 4. Metrik-Definition erstellen
curl -X POST https://example.com/api/admin/metric-definitions \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"tile_id": 42, "key": "co2", "label": {"de": "CO2-Ausstoß"}}'

# 5. Metrik-Wert setzen
curl -X POST https://example.com/api/admin/metric-values \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"metric_definition_id": 1, "tile_year_id": 1, "value": 1250}'
```

> **Wichtig:** Tokens MÜSSEN mit `tenant_id` versehen sein. Bei Requests ohne Tenant-Kontext:
> ```bash
> # Token ohne tenant_id führt zu 400
> {"message": "Tenant context required for admin API...", "error": "missing_tenant_context"}
> ```

---

## Test-Abdeckung

Die Admin API ist umfangreich getestet:

| Testklasse | Tests | Fokus |
|------------|-------|-------|
| `AdminTileApiTest` | 12 Tests | CRUD + Tenant-Scoping |
| `AdminTileYearApiTest` | 12 Tests | CRUD + Tenant-Scoping |
| `AdminMetricDefinitionApiTest` | 13 Tests | CRUD + Tenant-Scoping |
| `AdminMetricValueApiTest` | 13 Tests | CRUD + Tenant-Scoping |
| `AdminApiPermissionTest` | 7 Tests | Auth + Abilities |
| `TenantResolutionApiTest` | 9 Tests | Token/Domain Resolution |
| `BrandingConfigApiTest` | 15 Tests | Config-Endpoints |

**Gesamt:** 80+ Admin-spezifische Tests

Jeder Admin-Resource-Test enthält nun einen **expliziten 400-Test** für fehlenden Tenant-Kontext.

---

## Siehe auch

- [Multi-Tenancy](../architecture/multi-tenancy.md) – Tenant-Konzept
- [Tenant Resolution](./tenant-resolution.md) – Public API Tenant-Auflösung
- [API-Entwurf v0](./zukunftsbarometer_os_architektur_skizze_api_entwurf_v_0.md) – Original-Entwurf
