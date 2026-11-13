# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0](https://github.com/jandaroscher/cividash/compare/v1.1.3...v1.2.0) (2026-11-13)


### Features

* **admin:** show overview stats as figures and drop the contact card ([#96](https://github.com/jandaroscher/cividash/issues/96)) ([1bd09ac](https://github.com/jandaroscher/cividash/commit/1bd09acbcf62c4dc103f103b51f6647e4f8510db))
* **opencode:** sync metadata, name the city as contact ([#92](https://github.com/jandaroscher/cividash/issues/92)) ([4cc3897](https://github.com/jandaroscher/cividash/commit/4cc38976c7741c9981f3bba62bd1a5c676fe7b19))


### Bug Fixes

* **deps:** bump phpseclib to 4.0.3 for PKSA-h7cz-q7zq-mdkt ([#95](https://github.com/jandaroscher/cividash/issues/95)) ([e7c7357](https://github.com/jandaroscher/cividash/commit/e7c735751462b90e68f5f9fd1d91c9bc7e817080))
* **integration:** include /v1 in the NGSI-LD base URL for CORE ([#94](https://github.com/jandaroscher/cividash/issues/94)) ([80125d7](https://github.com/jandaroscher/cividash/commit/80125d732085c4173dae04527cc59eb3495e46a0))


### Documentation

* **civitas:** align the CORE test broker and the E2E domain specs ([#86](https://github.com/jandaroscher/cividash/issues/86)) ([3bb9feb](https://github.com/jandaroscher/cividash/commit/3bb9febbaf43c529d5cea19b6f2d45c5b5b6f265))
* **e2e:** drop the stale DDEV requirement from spec headers ([#91](https://github.com/jandaroscher/cividash/issues/91)) ([fe159e6](https://github.com/jandaroscher/cividash/commit/fe159e600ef53528390a14842c20fa92060804ca))
* **media:** name 1.1.2 as the last release with prefixed S3 keys ([#83](https://github.com/jandaroscher/cividash/issues/83)) ([f86a09c](https://github.com/jandaroscher/cividash/commit/f86a09cd6225ea379b495f04b4b92ba6d500a9ef))
* **readme:** point administrations to the admin panel and user guide ([#93](https://github.com/jandaroscher/cividash/issues/93)) ([b8d5d15](https://github.com/jandaroscher/cividash/commit/b8d5d15881edd94d9f854acf4eaca27a3818e8ba))

## [1.1.3](https://github.com/jandaroscher/cividash/compare/v1.1.2...v1.1.3) (2026-11-10)


### Bug Fixes

* **export:** make the JSON export re-importable as an upload bundle ([#80](https://github.com/jandaroscher/cividash/issues/80)) ([f493707](https://github.com/jandaroscher/cividash/commit/f493707adf971696459d70ed83d036862778e2c9))
* **media:** serve S3 media through the app so the bucket can stay private ([#82](https://github.com/jandaroscher/cividash/issues/82)) ([3dfe8f6](https://github.com/jandaroscher/cividash/commit/3dfe8f6e8e67e142c7786632d1e65881a52f9caa))

## [1.1.2](https://github.com/jandaroscher/cividash/compare/v1.1.1...v1.1.2) (2026-11-09)


### Features

* **blocks:** add title, alt text and copyright to every content image ([#46](https://github.com/jandaroscher/cividash/issues/46)) ([dc70b71](https://github.com/jandaroscher/cividash/commit/dc70b710094ec63e5ebe269f4bc278a8fc842990))


### Bug Fixes

* **admin:** accessible table toggles and https asset URLs behind proxies ([#75](https://github.com/jandaroscher/cividash/issues/75)) ([c96380d](https://github.com/jandaroscher/cividash/commit/c96380d174811ed63c24093460ed4cb189dc0eca))
* **seeders:** cite TDDDG, DDG and MStV in sample legal pages ([#40](https://github.com/jandaroscher/cividash/issues/40)) ([35751e9](https://github.com/jandaroscher/cividash/commit/35751e9734b382ab82c5784218709a2145da3fb5))


### Documentation

* add operator and developer guides and architecture overview ([#79](https://github.com/jandaroscher/cividash/issues/79)) ([96f8e8d](https://github.com/jandaroscher/cividash/commit/96f8e8df8dc83ffd8de456dbfd75cfcc5e1921b4))
* **user-guide:** retake screenshots and document image metadata ([#78](https://github.com/jandaroscher/cividash/issues/78)) ([5bf01c9](https://github.com/jandaroscher/cividash/commit/5bf01c910bbba61c643d20f4bf6c95dd55c9a557))

## [1.1.1](https://github.com/jandaroscher/cividash/compare/v1.1.0...v1.1.1) (2026-11-06)


### Bug Fixes

* **admin:** serve the Inter font locally instead of from Bunny Fonts ([#70](https://github.com/jandaroscher/cividash/issues/70)) ([d3cfe83](https://github.com/jandaroscher/cividash/commit/d3cfe83763b54e28e304ab4395c2b09cffb83712))
* **ci:** keep the tag checkout full in the release images workflow ([#65](https://github.com/jandaroscher/cividash/issues/65)) ([c28e315](https://github.com/jandaroscher/cividash/commit/c28e315ecd0a587d52fe59beb5658f1aba217539))
* **deps:** resolve Composer dependencies for PHP 8.2 ([#67](https://github.com/jandaroscher/cividash/issues/67)) ([0b7e42e](https://github.com/jandaroscher/cividash/commit/0b7e42e386091cd113af69bdab452a2c78d76d80))
* **deps:** update Symfony components for the November security release ([#71](https://github.com/jandaroscher/cividash/issues/71)) ([5b5a1e1](https://github.com/jandaroscher/cividash/commit/5b5a1e1befb3a9809ce04febe0fd103f7fbebf70))
* **privacy:** no session or personal data for anonymous visitors ([#72](https://github.com/jandaroscher/cividash/issues/72)) ([05e87e0](https://github.com/jandaroscher/cividash/commit/05e87e00770849cefac3266d5e13bf35d334292f))

## [1.1.0](https://github.com/jandaroscher/cividash/compare/v1.0.1...v1.1.0) (2026-11-04)


### Features

* **api-keys:** give new API keys a default expiry date ([#43](https://github.com/jandaroscher/cividash/issues/43)) ([03baf7b](https://github.com/jandaroscher/cividash/commit/03baf7b2741fc3f416490b1de1c1c696f2164eea))
* **api:** serve all API routes under /api/v1 ([#21](https://github.com/jandaroscher/cividash/issues/21)) ([3b1b1aa](https://github.com/jandaroscher/cividash/commit/3b1b1aa80be147d9133f259702b1faf7ceb9488d))
* **auth:** allow switching off the password login while SSO is enabled ([#44](https://github.com/jandaroscher/cividash/issues/44)) ([c0859d8](https://github.com/jandaroscher/cividash/commit/c0859d84f48a66d846114836bd8a9708abd580b7))
* **tiles:** embed tiles on third-party websites ([#29](https://github.com/jandaroscher/cividash/issues/29)) ([2449504](https://github.com/jandaroscher/cividash/commit/2449504acd9cc2365118a86b5cc7d880e3da515d))


### Bug Fixes

* **auth:** drop the remember-me cookie after Keycloak SSO login ([#42](https://github.com/jandaroscher/cividash/issues/42)) ([55cfcb7](https://github.com/jandaroscher/cividash/commit/55cfcb779a3bbf0716d4c013f3430deb0e57207c))
* create an empty start page for new tenants ([#39](https://github.com/jandaroscher/cividash/issues/39)) ([52d0f5e](https://github.com/jandaroscher/cividash/commit/52d0f5ef250ec1061cd692889808fb0dcaeb8e6f))
* **deps:** update league/commonmark, dompurify and source-map-js ([#26](https://github.com/jandaroscher/cividash/issues/26)) ([4891337](https://github.com/jandaroscher/cividash/commit/489133789786ec255647926b478f354d20de8afc))
* **deps:** update symfony/mime to 7.4.22 for CVE-2026-45070 ([#54](https://github.com/jandaroscher/cividash/issues/54)) ([d35d208](https://github.com/jandaroscher/cividash/commit/d35d2082a16ff99e86b0b6b906485db7fb15f557))
* **docker:** pin the runtime image to PHP 8.2.35 ([#38](https://github.com/jandaroscher/cividash/issues/38)) ([7edda72](https://github.com/jandaroscher/cividash/commit/7edda725d0bcc071cd7d8fae7a9b5e91fe30a42d))
* **embed:** align tile buttons and match the embed panel to content styles ([#30](https://github.com/jandaroscher/cividash/issues/30)) ([d86cf5d](https://github.com/jandaroscher/cividash/commit/d86cf5dbbb060777414365b22c6ff90d00669bd7))
* **frontend:** show zero indicator values as 0 ([#47](https://github.com/jandaroscher/cividash/issues/47)) ([65cdb76](https://github.com/jandaroscher/cividash/commit/65cdb765113f5df4d17f7f0389a9b13b5e77c635))
* load tile animations reliably in the embed view ([#31](https://github.com/jandaroscher/cividash/issues/31)) ([85ab986](https://github.com/jandaroscher/cividash/commit/85ab9865d85f317b3b39f2c5a369803b28113544))
* **theme:** use inverse text token in Demo City tile card ([#25](https://github.com/jandaroscher/cividash/issues/25)) ([8506207](https://github.com/jandaroscher/cividash/commit/8506207c851ae21b42bce744576b904547233e72))


### Documentation

* **architecture:** describe password and optional SSO login for the admin UI ([#28](https://github.com/jandaroscher/cividash/issues/28)) ([065c1ce](https://github.com/jandaroscher/cividash/commit/065c1cecbc987d5142663c6c51c6a7ce16345337))
* GitHub as canonical repository, security headers, drop Apache basic-auth leftovers ([#37](https://github.com/jandaroscher/cividash/issues/37)) ([382b23d](https://github.com/jandaroscher/cividash/commit/382b23d723a06fab81f12c046b9637c26c1ecb61))
* record the 1.0.1 release on main ([#59](https://github.com/jandaroscher/cividash/issues/59)) ([45d1735](https://github.com/jandaroscher/cividash/commit/45d173568ba20c3c71c8fbdabb29f411ea22984b))
* show the CiviDash logo in the README ([#17](https://github.com/jandaroscher/cividash/issues/17)) ([e5d41eb](https://github.com/jandaroscher/cividash/commit/e5d41eb94c8fca95e0f65445969d3bbc51d5593d))

## 1.0.1 (2026-10-21)

### Security

* Pin the production runtime image to PHP 8.2.35, which fixes CVE-2026-109618 and CVE-2026-109619.

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
