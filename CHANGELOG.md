# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

- Stop tracking generated Filament CSS/JS assets in git; they are published via
  `filament:assets` (already wired into `composer.json`'s `post-autoload-dump` and the
  production Docker image), so every install/deploy regenerates them automatically
