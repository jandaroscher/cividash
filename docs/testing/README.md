# Testing guide

This guide shows how to run the test suites of CiviDash. Run each suite in the order
below. The faster suites come first.

| Suite | Tool | Location | Runs in CI |
| --- | --- | --- | --- |
| Backend unit and feature tests | PHPUnit | `tests/Unit/`, `tests/Feature/` | Yes |
| Frontend unit tests | Vitest | `tests/js/` | Yes |
| Frontend end-to-end tests | Playwright | `tests/e2e/frontend/` | Yes, on frontend changes |
| Other end-to-end and accessibility tests | Playwright, axe-core | `tests/e2e/` | No |

## PHPUnit

`phpunit.xml` sets an in-memory SQLite database, so the suite needs no database server.

```bash
ddev exec php artisan test                                   # full suite
ddev exec php artisan test --filter=ManageBrandingTest       # one test class
ddev exec php artisan test tests/Feature/Api/                # one directory
ddev exec vendor/bin/pint --test                             # code style check
```

Without DDEV, run the same commands without `ddev exec`. You need PHP 8.4 or later and
`composer install`.

## Vitest

```bash
npx vitest run                                      # full suite
npx vitest run tests/js/stores/branding.test.js     # one file
npm run lint                                        # ESLint
```

## Playwright end-to-end and accessibility tests

The Playwright specs send real HTTP requests to a running CiviDash instance. They do not
start a server.

### Prepare the instance

1. Install the Playwright browser once: `npx playwright install chromium`.
2. Build the frontend assets: `npm run build`. The SPA and `/admin` need the Vite
   manifest.
3. Seed the database. Some specs also seed their own fixtures through Artisan.

### Set the target URL

`playwright.config.ts` reads the base URL from `PLAYWRIGHT_BASE_URL`. The default is the
DDEV site `http://open-source-dashboard.ddev.site`. The specs use relative paths, so one
variable changes the target of all of them.

`tests/e2e/tenant-resolution.spec.ts` tests tenant resolution by domain. It sends its
requests to the same base URL and sets the tenant domain in the `Host` header, so the
test domains need no DNS entries.

`tests/e2e/frontend/export.spec.ts` (project `exports`) opens the `demo-city` tenant on
the `demo-city.` subdomain of the base URL host. It needs tiles and pages in that tenant,
which `e2e:seed-full` does not create. Seed them with `php artisan dashboard:reset --force`.
The command reads a dashboard file in the legacy JSON format from `SEED_DASHBOARD_JSON`
(default `storage/app/seeds/dashboard.json`, not in the repository) or downloads it from
`SEED_DASHBOARD_JSON_URL`; see [Seeding](../deployment/seeding.md). **It deletes the
content of the default and Demo City tenants**, so run it only on a test database. Without DDEV, use `localhost` in `APP_URL` and `PLAYWRIGHT_BASE_URL`;
the seeders derive the tenant domains from `APP_URL`, and `demo-city.localhost` resolves to
the local machine.

Specs that seed fixtures run `${E2E_ARTISAN_CMD} e2e:seed-full --json`.
`E2E_ARTISAN_CMD` defaults to `ddev exec php artisan`.

### Run with DDEV

```bash
ddev exec php artisan e2e:seed-full --clean --json
npm run test:e2e                                  # all projects

# The frontend specs (the same five projects as CI) need the demo-city tenant.
# dashboard:reset deletes the content of the default and Demo City tenants.
ddev exec php artisan dashboard:reset --force
PLAYWRIGHT_BASE_URL=http://demo-city.open-source-dashboard.ddev.site \
  npx playwright test --project=dashboard-navigation --project=filter-search \
  --project=locale-switching --project=content-pages --project=branding
npx playwright test --project=accessibility       # accessibility audit only
```

### Run without DDEV

Use `php artisan serve` and a SQLite file:

```bash
export APP_URL=http://localhost:8000 DB_CONNECTION=sqlite DB_DATABASE="$PWD/database/e2e.sqlite"
rm -f "$DB_DATABASE" && touch "$DB_DATABASE"
php artisan migrate --seed
npm run build
php artisan serve --port=8000 --no-reload &

export PLAYWRIGHT_BASE_URL=http://localhost:8000 E2E_ARTISAN_CMD="php artisan"
npx playwright test --project=accessibility
```

### Admin backend walk-through

`tests/e2e/admin/backend.spec.ts` (project `admin-backend`) uses the admin panel like an
editor: it logs in, opens every page, creates, edits and deletes each resource, saves the
settings pages with uploads, translates a tile and a page, imports the example bundle and
registers a new dashboard. Each test fails on a 5xx response or a JavaScript error and
leaves a screenshot in `test-results/admin-backend` (`E2E_SCREENSHOT_DIR` overrides it).
It writes data, so run it only against a local or test instance:

```bash
npx playwright test --project=admin-backend
```

Start the app with `CIVITAS_ENABLED=true` to include the integrations page.

### Keycloak login

`tests/e2e/admin/keycloak-sso.spec.ts` (project `admin-keycloak-sso`) signs in to the admin
panel through a real Keycloak that requires PKCE, as CIVITAS/CORE 2 does. It runs only when
`E2E_KEYCLOAK_PASSWORD` is set. Start Keycloak from `docker/keycloak`; its realm requires
PKCE for the client `cividash-dashboard`:

```bash
docker build -t cividash-keycloak docker/keycloak
docker run -d --network host -e CLIENT_SECRET=<secret> -e DEMO_PASSWORD=<password> \
  -e DASHBOARD_URL=http://localhost:8000 -e KC_HTTP_ENABLED=true -e KC_HOSTNAME_STRICT=false \
  cividash-keycloak
```

Start the app with `KEYCLOAK_SSO_ENABLED=true`, `KEYCLOAK_BASE_URL=http://localhost:8080`,
`KEYCLOAK_CLIENT_ID=cividash-dashboard`, `KEYCLOAK_CLIENT_SECRET=<secret>` and
`KEYCLOAK_REDIRECT_URI=http://localhost:8000/admin/auth/keycloak/callback`, then run:

```bash
E2E_KEYCLOAK_PASSWORD=<password> npx playwright test --project=admin-keycloak-sso
```

### Accessibility audit

`tests/e2e/accessibility/a11y-audit.spec.ts` runs axe-core with the tags `wcag2a` and
`wcag2aa` on the home page, the tiles page and the admin login. A test fails on every
violation with the impact `serious` or `critical`, including `color-contrast`.

Do not disable axe rules or exclude elements to make the audit pass. Fix the cause:

- Admin panel: the colour palettes in `AdminPanelProvider::panel()` (`->colors()`) and
  `resources/css/admin-contrast.css`.
- SPA: the branding defaults in `resources/js/stores/branding.js`, the settings
  migrations in `database/settings/` and the demo tokens in `TenantSeeder`.

Each tenant can set its own branding colours. The audit checks the seeded colours only.
Check the contrast of a new tenant palette separately.

## CI behaviour

GitHub Actions runs two workflows on every pull request against `main` and on every push
to `main`:

- `ci.yml`: ESLint, Vitest, `npm audit`, the Vite build, `composer audit` and an SBOM.
- `ci-postgres.yml`: Pint, the full PHPUnit suite on PostgreSQL 16, and Scribe.

Both workflows skip changes that touch only `docs/**` or Markdown files.

`e2e-frontend.yml` runs the frontend specs (projects `dashboard-navigation`,
`filter-search`, `locale-switching`, `content-pages`, `branding` and `rate-limit`) on pull requests and
pushes to `main` that change the application, its data, its frontend or the specs. You can
also start it by hand. It seeds the demo-city tenant from
`tests/Fixtures/dashboard-full.json` on SQLite and uses the file cache and file sessions,
because several PHP workers writing the database cache at once lock SQLite.

The specs clear the application cache before each test, because the public API allows
`PUBLIC_API_RATE_LIMIT` requests per minute per IP (default 300), each page load makes
about ten API calls, and the whole suite runs from one IP.

CI does not run the other Playwright projects. Run them locally before you open a pull
request that changes the admin panel or tenant resolution. Note the result in the pull
request.

The Playwright config has CI settings: with `CI` set, it forbids
`test.only` and retries a failed test once. It always uses one worker, because several
specs reset the same database.

## Related documents

- [E2E tests for tenant resolution](e2e-tenant-resolution.md)
- [UAT template for acceptance tests](uat-template.md)
