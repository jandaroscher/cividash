# CiviDash

CiviDash is an open-source sustainability dashboard for municipalities, built on
Laravel (backend/admin via Filament) and Vue 3 (frontend). It supports multi-tenancy
(multiple dashboards/tenants) and multilingual content (German/English), with KPI
tiles, filtering, full-text search, a Filament-based page builder and data import. The
related CIVITAS/CORE add-on is maintained separately.

- Repository: https://gitlab.opencode.de/regensburg_next/cividash
- CIVITAS/CORE add-on: https://gitlab.opencode.de/regensburg_next/cividash-addon
- Installation guide: [docs/deployment/installation-standalone.md](docs/deployment/installation-standalone.md)
- Contributing: [CONTRIBUTING.md](CONTRIBUTING.md)
- Security policy: [SECURITY.md](SECURITY.md)

## Features

- KPI tiles with time series
- Filtering by category groups
- Full-text search
- Multilingual content (German/English)
- Multi-dashboard / multi-tenancy
- Filament admin with page builder
- Data import (JSON bundles) and export (JSON/CSV)
- CIVITAS/CORE integration

## Tech stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12, PHP 8.2, Sanctum |
| Admin | Filament 3.3, Filament Fabricator |
| Frontend | Vue 3, Vue Router 4, Pinia |
| Styling | Tailwind CSS 4 |
| Build | Vite 6 |
| Database | PostgreSQL 16 |

## Quickstart

Local development runs through [DDEV](https://ddev.com/). Node.js 20 or newer is
required outside of DDEV (e.g. for editor tooling); see `.nvmrc` (`nvm use`).

```bash
ddev start                              # Start environment
ddev exec composer install              # Install PHP dependencies
ddev exec php artisan key:generate      # Generate app key (DDEV creates .env itself)
ddev exec npm install                   # Install Node dependencies
ddev exec php artisan migrate           # Run migrations
ddev exec php artisan tenancy:backfill  # Setup multi-tenancy (creates default tenant)
ddev exec php artisan db:seed           # Seed demo data (tenants, roles)
ddev exec npm run build                 # Build frontend assets (public routes need the Vite manifest)
```


```bash
ddev delete --omit-snapshot && ddev start
```

## Deployment

- Standalone (bare server / VM): [docs/deployment/installation-standalone.md](docs/deployment/installation-standalone.md)
- Docker: [docs/deployment/docker.md](docs/deployment/docker.md)

See [docs/deployment/README.md](docs/deployment/README.md) for the full deployment
overview, including DDEV.

## Documentation

- [API documentation](docs/api) and the generated Scribe reference at `/docs`
- [Architecture](docs/architecture)
- [Deployment](docs/deployment)
- [Testing](docs/testing)
- [Data upload](docs/upload)
- [User guide](docs/user-guide) (CMS usage, German and English)
- [CHANGELOG.md](CHANGELOG.md)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

See [SECURITY.md](SECURITY.md) for how to report vulnerabilities.

## License



