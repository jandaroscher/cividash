# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0](https://github.com/jandaroscher/cividash/compare/v1.0.0...v1.1.0) (2026-10-22)


### Features

* **api-keys:** give new API keys a default expiry date ([#43](https://github.com/jandaroscher/cividash/issues/43)) ([03baf7b](https://github.com/jandaroscher/cividash/commit/03baf7b2741fc3f416490b1de1c1c696f2164eea))
* **api:** serve all API routes under /api/v1 ([#21](https://github.com/jandaroscher/cividash/issues/21)) ([3b1b1aa](https://github.com/jandaroscher/cividash/commit/3b1b1aa80be147d9133f259702b1faf7ceb9488d))
* **auth:** allow switching off the password login while SSO is enabled ([#44](https://github.com/jandaroscher/cividash/issues/44)) ([c0859d8](https://github.com/jandaroscher/cividash/commit/c0859d84f48a66d846114836bd8a9708abd580b7))
* **tiles:** embed tiles on third-party websites ([#29](https://github.com/jandaroscher/cividash/issues/29)) ([2449504](https://github.com/jandaroscher/cividash/commit/2449504acd9cc2365118a86b5cc7d880e3da515d))


### Bug Fixes

* **auth:** drop the remember-me cookie after Keycloak SSO login ([#42](https://github.com/jandaroscher/cividash/issues/42)) ([55cfcb7](https://github.com/jandaroscher/cividash/commit/55cfcb779a3bbf0716d4c013f3430deb0e57207c))
* create an empty start page for new tenants ([#39](https://github.com/jandaroscher/cividash/issues/39)) ([52d0f5e](https://github.com/jandaroscher/cividash/commit/52d0f5ef250ec1061cd692889808fb0dcaeb8e6f))
* **deps:** update league/commonmark, dompurify and source-map-js ([#26](https://github.com/jandaroscher/cividash/issues/26)) ([4891337](https://github.com/jandaroscher/cividash/commit/489133789786ec255647926b478f354d20de8afc))
* **docker:** pin the runtime image to PHP 8.2.35 ([#38](https://github.com/jandaroscher/cividash/issues/38)) ([7edda72](https://github.com/jandaroscher/cividash/commit/7edda725d0bcc071cd7d8fae7a9b5e91fe30a42d))
* **embed:** align tile buttons and match the embed panel to content styles ([#30](https://github.com/jandaroscher/cividash/issues/30)) ([d86cf5d](https://github.com/jandaroscher/cividash/commit/d86cf5dbbb060777414365b22c6ff90d00669bd7))
* load tile animations reliably in the embed view ([#31](https://github.com/jandaroscher/cividash/issues/31)) ([85ab986](https://github.com/jandaroscher/cividash/commit/85ab9865d85f317b3b39f2c5a369803b28113544))
* **theme:** use inverse text token in Demo City tile card ([#25](https://github.com/jandaroscher/cividash/issues/25)) ([8506207](https://github.com/jandaroscher/cividash/commit/8506207c851ae21b42bce744576b904547233e72))


### Documentation

* **architecture:** describe password and optional SSO login for the admin UI ([#28](https://github.com/jandaroscher/cividash/issues/28)) ([065c1ce](https://github.com/jandaroscher/cividash/commit/065c1cecbc987d5142663c6c51c6a7ce16345337))
* GitHub as canonical repository, security headers, drop Apache basic-auth leftovers ([#37](https://github.com/jandaroscher/cividash/issues/37)) ([382b23d](https://github.com/jandaroscher/cividash/commit/382b23d723a06fab81f12c046b9637c26c1ecb61))
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
