# Architecture overview

CiviDash is a headless Laravel 12 application with a Vue 3 single-page frontend and a
Filament 3.3 admin panel. This page gives the big picture and links the detailed
documents.

## Components

- **Public frontend (Vue SPA):** citizen-facing dashboard. Laravel serves one Blade view
  (`resources/views/app.blade.php`) with server-rendered meta tags; Vue Router renders all
  pages in the browser from API data.
- **Public API (`/api/v1/...`):** read-only JSON for tiles, pages, configuration, filters
  and exports. The generated reference is served at `/docs` once built with
  `php artisan scribe:generate`; the release images do not include it.
- **Admin panel (Filament, `/admin`):** editorial back office with the page builder,
  tiles, categories, media, users and roles. It is the only part that uses a session
  cookie.
- **Admin API:** token-based (Sanctum) endpoints, among them the bulk data import.
- **Background workloads:** a queue worker and the Laravel scheduler, both run from the
  same `cividash-app` image as PHP-FPM.
- **PostgreSQL** for all application data, and the `public` disk (local volume or
  S3-compatible storage) for uploaded media.

Services, ports and configuration keys are listed in the
[README](../README.md#services-and-ports).

## Request flow

```text
browser -> ingress / gateway -> cividash-web (nginx, :8080)
             static files, /build         -> served by nginx
             everything else              -> cividash-fpm (PHP-FPM, :9000) -> PostgreSQL
```

nginx serves built assets and static files itself and passes all other paths to
`index.php` (`docker/production/nginx/nginx.conf`). Laravel then answers with the SPA
shell, an API response, a tile embed or the Filament panel.

Details: [headless-architecture.md](architecture/headless-architecture.md).

## Multi-tenancy

All dashboards (tenants) share one database. Tenant-bound models carry a `tenant_id` and
are scoped automatically. The public API resolves the tenant from a bearer token, then from
the request host (`tenants.domain`), then falls back to the `default` tenant.

Details: [multi-tenancy.md](architecture/multi-tenancy.md) and
[tenant resolution](api/tenant-resolution.md).

## Data import

Editors import tiles, categories, metrics and yearly values as JSON bundles, either in
the admin panel or through `POST /api/v1/admin/import`. An import can be previewed as a dry
run, which is rolled back; a commit runs in one transaction. The bundle format equals the export format.

Details: [upload bundles and schemas](upload/README.md) and the
[data import user guide](user-guide/datenimport.en.md).

## CIVITAS/CORE integration

In production CiviDash runs as an add-on of a CIVITAS/CORE platform: CORE's Keycloak
provides admin login (optional, `KEYCLOAK_*`) and machine tokens, and the scheduled
`integration:sync-civitas` command pulls indicator data from the CORE context broker
(Stellio via NGSI-LD by default, or a SensorThings server with `CIVITAS_DRIVER=sensorthings`) into tiles and metrics (`CIVITAS_*`).

- [civitas-core/architecture.md](architecture/civitas-core/architecture.md): operating
  context, roles and access paths
- [civitas-core/data-flow.md](architecture/civitas-core/data-flow.md): the sync, step by
  step
- [civitas-core/ngsi-ld-data-model.md](architecture/civitas-core/ngsi-ld-data-model.md):
  the ingested entity model
- Diagrams: [architecture](architecture/civitas-core/diagrams/architecture.svg)
  ([source](architecture/civitas-core/diagrams/architecture.mmd)) and
  [data flow](architecture/civitas-core/diagrams/data-flow.svg)
  ([source](architecture/civitas-core/diagrams/data-flow.mmd))

## Further topics

- [svg-sanitizing.md](architecture/svg-sanitizing.md): how uploaded SVG files are cleaned
- [tile-background-blocks.md](architecture/tile-background-blocks.md): how tile background
  content is stored
