# Developer guide

For people who change CiviDash's code. Start with the
[architecture overview](architecture.md), then the rules in
[CONTRIBUTING.md](../CONTRIBUTING.md). `AGENTS.md` in the repository root holds the full
command reference and coding conventions.

## Local setup

Local development runs on DDEV; the setup commands are in the
[README quickstart](../README.md#quickstart) and in [deployment/ddev.md](deployment/ddev.md),
which also covers extra tenant hostnames, git worktrees and the database engine.
`composer run dev` starts the Laravel server, a queue listener, the log tailer and the
Vite dev server together.

To work against a local CIVITAS/CORE (Stellio NGSI-LD) broker, use
[docker/civitas/README.md](../docker/civitas/README.md). To check the production images,
run the smoke stack described in [deployment/docker.md](deployment/docker.md).

## Tests

[testing/README.md](testing/README.md) explains the three suites and how to run them:
PHPUnit (`tests/Unit/`, `tests/Feature/`) and Vitest (`tests/js/`) run in CI; Playwright
end-to-end and accessibility tests (`tests/e2e/`) run locally only, with the setup in
[testing/e2e-tenant-resolution.md](testing/e2e-tenant-resolution.md).
[testing/uat-template.md](testing/uat-template.md) is the (German) acceptance test
template for an installation.

Before you open a pull request, run:

```bash
ddev exec vendor/bin/pint --test   # PHP formatting
npm run lint                       # ESLint
ddev exec php artisan test         # backend suite
npx vitest run                     # frontend suite
```

## API

- [api/versioning.md](api/versioning.md): all routes live under `/api/v1/`; the unversioned
  paths are deprecated aliases.
- [api/tenant-resolution.md](api/tenant-resolution.md): how public requests find their
  tenant.
- [api/admin-api.md](api/admin-api.md): token-based admin endpoints. After changing
  endpoints or their annotations, regenerate the reference with `php artisan scribe:generate`;
  CI does not do this.
- [upload/README.md](upload/README.md): JSON schemas and examples for import bundles.

## Commits, pull requests and DCO

Details are in [CONTRIBUTING.md](../CONTRIBUTING.md). In short:

- Branch off `main` (`feat/…`, `fix/…`, `chore/…`) and open the pull request on GitHub.
  The openCode mirror does not accept changes.
- Use Conventional Commits (`type(scope)?: description`). Pull requests are
  squash-merged, so the PR title becomes the commit message; release-please derives the
  version and changelog from it.
- Sign off every commit (`git commit -s`, DCO 1.1). An AI assistant never signs off; the
  person who submits the work does, and a `Co-authored-by` trailer may name the
  assistant.
- New source files start with an SPDX header (`EUPL-1.2+`); CI runs `reuse lint`.
- No hardcoded user-facing strings: use the `de`/`en` translation keys.

## Documentation map

| Area | Read when |
|------|-----------|
| [architecture/](architecture.md) | you change how components fit together, tenancy or the CORE sync |
| [deployment/](deployment/README.md) | you touch Docker images, the entrypoint, seeding or scheduling |
| [api/](api/admin-api.md) | you add or change endpoints |
| [upload/](upload/README.md) | you change the import format |
| [testing/](testing/README.md) | you write or run tests |
| [user-guide/](user-guide/en/00-introduction.md) | you change admin panel behaviour that editors see; keep the German and English guides in sync |
