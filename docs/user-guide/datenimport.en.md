# Data Import

Tiles, categories, metrics, and yearly values can be imported into a tenant as a JSON bundle — instead of creating each record one-by-one in the backend. The import runs in two steps: first a dry-run with preview, then commit.

The bundle format is identical to the JSON output of the export feature. A bundle exported from one tenant can be imported directly into another tenant (round-trip).

## Prerequisites

- Admin role in the target tenant
- JSON file conforming to the schema (see _Bundle format_ below)
- Maximum file size: 20 MB

## Bundle format (JSON)

A bundle has an envelope and a `data` array. Each row describes a tile with a metric and a value for one year.

```json
{
  "schema_version": "1.0",
  "mode": "upsert",
  "data": [
    {
      "tile.slug": "energieverbrauch",
      "tile.title": { "de": "Energieverbrauch", "en": "Energy Consumption" },
      "category.keys": ["handlungsfelder/energie"],
      "metric.key": "kwh_total",
      "metric.label": { "de": "Gesamtverbrauch", "en": "Total consumption" },
      "metric.unit": { "de": "GWh", "en": "GWh" },
      "value.year": 2024,
      "value.value": 3750
    }
  ]
}
```

**Required fields per row:**

- `tile.slug` — URL-safe tile key (lowercase, hyphens).
- `tile.title.de` — Required when creating a new tile.
- `value.year` + `metric.key` + `metric.label.de` — Required when a value is set.

**Optional structure data** at the bundle level:

- `category_groups` — Category groups (e.g. "Action fields").
- `categories` — Individual categories referencing a `group_key`.

**Download the schema and an example bundle:** Two buttons at the top-right of the import page: _Download schema_ (JSON Schema draft-07 for validation in your editor or CI pipeline) and _Example bundle_ (a complete valid example to start from). The schemas are also available as public API endpoints at `/api/import/schemas/{bundle|row|category|category-group}`.

## Modes

| Mode | Behaviour |
|---|---|
| `upsert` (default) | Create new records, update existing ones. No deletions. |
| `replace` | _(v1.1 — not yet implemented; the server rejects with HTTP 422 `unsupported_mode`)_ Additionally delete records not present in the bundle. |

## Running an import

1. Navigate to _System → Data Import_ in the backend.
2. Drag-and-drop or click to upload the JSON file.
3. Click **Run dry-run** and review the result:
    - **Changes table**: counts of created, updated, deleted, and unchanged records per entity.
    - **Errors**: Block the import. Include row number, field path, and explanation.
    - **Warnings**: Do not block the import (e.g. if the bundle tenant does not match the logged-in tenant).
4. If errors are present: fix the file and upload again.
5. On a green dry-run: click **Commit import**. All changes are written in a single transaction.

## Dry-run and commit

Internally, the dry-run runs the same code as the commit but discards the database transaction at the end. A successful dry-run guarantees that the commit will also succeed — assuming the database does not change between dry-run and commit due to other users.

## Atomic transaction

The import is atomic: if a single row fails (wrong type, unknown category, missing required title), the entire import is rolled back — nothing lands in the database.

## Interpreting error messages

Every error has the following structure:

- **Row number** (where relevant) — index in the `data` array, zero-based.
- **Code** — Machine-readable. Examples: `required`, `invalid_type`, `invalid_format`, `invalid_enum_value`, `unresolved_reference`.
- **Path** — Dot-notation of the affected field, e.g. `data[3].tile.title.de`.
- **Message** — Human-readable explanation.

## History

Below the result area, the _Recent import runs_ table lists past runs with time, file, mode, status, error count, duration, and user. Both dry-runs and commits are logged.

## Tenant context

The tenant is always derived from the logged-in session (or the API token). A `tenant.slug` field in the bundle is ignored — only a warning is shown if the bundle tenant differs from the token tenant. Data is always imported into the logged-in tenant.

## Admin API (programmatic)

For automated pipelines, use the `POST /api/admin/import` endpoint with bearer token authentication. Rate limit: 5 runs per minute per tenant. Documentation available at `/docs` (Scribe).

## Limitations in v1.0

- **JSON only.** CSV support arrives in v1.1.
- **No assets** (images, favicon). These are still uploaded in the branding area.
- **No content pages.** `docs/upload/README.md` describes the planned scope for pages in a later version.
