# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1](https://github.com/jandaroscher/cividash/compare/v1.0.0...v1.0.1) (2026-09-23)


### Documentation

* show the CiviDash logo in the README ([#17](https://github.com/jandaroscher/cividash/issues/17)) ([e5d41eb](https://github.com/jandaroscher/cividash/commit/e5d41eb94c8fca95e0f65445969d3bbc51d5593d))

## 1.0.0 (2026-09-23)

### ⚠ Breaking Changes

* The application database engine switches from MariaDB to PostgreSQL 16. Existing MariaDB deployments cannot be upgraded in place and must be recreated against PostgreSQL.

### Features

**Dashboard & Tiles**
* KPI tiles with time series, category-group filtering, and full-text search across tiles
* Deep-linkable tile detail overlays with focus trap and jump marks to page sections
* Contextual in-app help system
* Configurable time granularity per tile
* Scroll-to-top button, download block, and tile hint field on tile background pages
* JSON and CSV export of dashboard data with shareable links carrying Open Graph preview metadata

**CMS & Content Management**
* Filament-based admin panel with a Fabricator page builder, including hero, text-image, slider, section, FAQ, and download content blocks
* Page and tile administration with SEO metadata, dynamic navigation, drag-and-drop ordering of tiles, categories, and category groups
* Bulk import of tiles, metrics, and categories from JSON bundles, plus a period-based bulk data management page for cross-tile updates
* User management and API key management for the admin panel
* Theme management: per-theme settings and component overrides, theme bundle import/export, and a configurable font scheme per theme

**Multi-Tenancy**
* Multi-dashboard support via tenant-scoped Filament panels, with subdomain-based tenant pre-selection and tenant-scoped settings
* Nightly demo data reset for public demo dashboards

**CIVITAS/CORE Integration**
* Integration with CIVITAS/CORE: read and write-back of dashboard indicators as NGSI-LD data, including bulk write-back of tiles

**Single Sign-On**
* Keycloak SSO login for the CIVITAS demo stack

**Deployment**
* Production Docker images (`cividash-app`, `cividash-web`) and a smoke-test compose stack for self-hosted deployment, including upload size limits for media and bundle imports

### Bug Fixes

**Dashboard & Tiles**
* Keep the tile grid at a consistent width and tile size across breakpoints
* Fix search on pinned pages, icon fallback, empty accordion, and FAQ rendering on tile pages
* Render category filter groups as a column grid instead of a grey bar
* Include indicator labels in tile search, and keep tile subheader sentences intact on stray periods
* Enforce locale- and parent-aware slug uniqueness for pages, and skip slug validation for the fixed landing-page slug

**Deployment**
* Fix production Docker images to work reliably on Kubernetes and against fresh databases, including DNS resolution, nginx upstream, and rootless container fixes

**Security & Privacy**
* Sanitize uploaded SVG icons and logos, and the download content block
* Enforce the admin role for session-authenticated admin API requests, and verify the Keycloak email before account linking
* Enforce the admin-API ability on the tenant user API and drop legacy tenant fallbacks
* Remove third-party font and avatar requests from the frontend; self-host fonts instead (privacy)
* Return a proper 401 JSON response for unauthenticated API requests without a JSON Accept header
* Update vulnerable development dependencies (js-yaml, brace-expansion) to patched versions

### Documentation

* CIVITAS/CORE architecture, data flow, and NGSI-LD data model documentation
* Deployment overview, Docker reference, and DDEV guide for standalone CiviDash
* CMS user guide, in German and English
* Description of the AI-assisted development workflow
* Description of the retained root page after the demo-city data reset
* Statement that CiviDash ships without tracking

## Pre-release history

Entries below predate the switch to Conventional Commits and release-please
(release preparation). From the 1.0.0 release onward, this file is maintained
automatically from commit messages.

### Added

- KPI tiles with time series
- Filtering by category groups
- Full-text search
- Multilingual content (German/English)
- Multi-dashboard / multi-tenancy
- Filament admin with page builder
- Data import (JSON bundles) and export (JSON/CSV)
- CIVITAS/CORE integration
- Theme management with per-theme settings and component overrides
- Theme bundle import/export
- Configurable font scheme per theme
- Upload size limits for media and bundle imports

### Changed

- Database migrations up to September 2026 are squashed into schema dumps (`database/schema/*.sql` for MariaDB, MySQL, PostgreSQL and SQLite). Fresh installations load the dump, existing installations are unaffected. Settings migrations stay as files because they carry default values.
- Stop tracking generated Filament CSS/JS assets in git; they are published via
  `filament:assets` (already wired into `composer.json`'s `post-autoload-dump` and the
  production Docker image), so every install/deploy regenerates them automatically
