# Contributing to CiviDash

Thank you for considering a contribution to CiviDash.
The maintainers develop on GitHub (`jandaroscher/cividash`), which is mirrored to openCode
(<https://gitlab.opencode.de/regensburg_next/cividash>). Report issues at
<https://github.com/jandaroscher/cividash/issues> and open pull requests on GitHub. The
openCode mirror is overwritten from GitHub, so merge requests there cannot be merged.

## Getting started

The project runs on [DDEV](https://ddev.com/). Node.js 22 or newer is required
outside of DDEV (see `.nvmrc`, `nvm use`).

```bash
ddev start
ddev exec composer install
ddev exec php artisan key:generate
ddev exec npm install
ddev exec php artisan migrate
ddev exec php artisan tenancy:backfill
ddev exec php artisan db:seed
ddev exec npm run build
```

For local development with hot reload:

```bash
composer run dev
```

See `AGENTS.md` in the repository root for the full architecture overview and command
reference.

## Branching and commits

- Branch naming: `feat/short-description`, `fix/short-description`,
  `chore/short-description`. Optionally prefix the description with the number of an
  issue in the public tracker, e.g. `fix/123-short-description`.
- Commit message format (Conventional Commits): `type(scope)?: description`.
  Types: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `ci`, `perf`. Breaking
  changes use `type!:` and add a `BREAKING CHANGE:` footer, e.g.
  `feat!: drop the legacy theme format`.
  [release-please](https://github.com/googleapis/release-please) derives the version
  and CHANGELOG from these commits (`feat` -> minor, `fix` -> patch, `!` -> major).
  PRs are squash-merged, so the PR title becomes the commit message and must follow
  this format.

## Development process

- Work is generally test-driven: write or update tests alongside the change.
- Run only the tests affected by your change during development, then the full suite
  before opening a pull request:

  ```bash
  ddev exec php artisan test                                   # full backend suite
  ddev exec php artisan test --filter=SomeTestClass             # single test class
  npm run test:e2e                                               # Playwright E2E tests
  npx vitest run tests/js/components/SomeComponent.test.js       # single JS test file
  ```

- Code style:

  ```bash
  ddev exec vendor/bin/pint --test   # PHP formatting check (Laravel Pint)
  ddev exec vendor/bin/pint          # PHP formatting fix (Laravel Pint)
  npm run lint:fix                   # ESLint fix for Vue/JS
  ```

- Never hardcode user-facing strings; use translation keys (`de`/`en`) as described in
  `AGENTS.md`.
- Do not commit debug code (`dd()`, `console.log`) or secrets.

## Before you open a PR

Run these locally and make sure they pass:

```bash
ddev exec vendor/bin/pint --test   # PHP formatting check
npm run lint                       # ESLint for Vue/JS
ddev exec php artisan test         # Backend test suite
npx vitest run                     # Frontend test suite
```

Then fill out the merge request template: GitLab applies
[.gitlab/merge_request_templates/Default.md](.gitlab/merge_request_templates/Default.md)
automatically, GitHub applies [.github/PULL_REQUEST_TEMPLATE.md](.github/PULL_REQUEST_TEMPLATE.md).
Bug reports and feature requests should use the issue templates under
[.gitlab/issue_templates](.gitlab/issue_templates) (GitLab) or
[.github/ISSUE_TEMPLATE](.github/ISSUE_TEMPLATE) (GitHub).

## Submitting changes

1. Fork or branch, make your change, and ensure tests and linters pass.
2. Open a pull/merge request describing the change and linking the related ticket, if
   any.
3. Pull requests are reviewed by [CodeRabbit](https://coderabbit.ai) and by human
   maintainers. Address all review comments before merge.
4. All tests must be green and there must be no open review comments before a PR is
   merged.

## AI-assisted development

The core team uses AI coding assistants (e.g. Claude Code) during development, and
CodeRabbit for automated review. Every contribution, AI-assisted or not, is reviewed
and owned by a human, who is responsible for its correctness and consequences.
Contributions must meet the same merge requirements as any other change: all applicable
required checks must pass, no open review threads, and Conventional Commits. Do not
include secrets, customer data, or internal documents in any contribution.

## Licence headers

The project follows the [REUSE specification](https://reuse.software). Start
every new source file (PHP, Blade, Vue, JS/TS, CSS, shell, YAML) with an SPDX
header naming the copyright holder (you or your employer; maintainers use
JANDA+ROSCHER GmbH & Co. KG), for example:

```php
// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+
```

`reuse annotate --copyright "<holder>" --license EUPL-1.2+ <file>`
writes it in the right comment style. Files without comment syntax (JSON,
images, Markdown) are covered by `REUSE.toml`; files copied or adapted from
third-party packages (published configs, views, translations) get their own
entry there with the upstream copyright and licence. CI runs `reuse lint` on every pull request.

## Developer Certificate of Origin

This project requires the Developer Certificate of Origin (DCO 1.1) instead of a CLA.
Every commit that lands on `main` must be signed off by a person, confirming they have
the right to submit the change under the project's license. This applies to the core
team and to external contributors alike. External contributors sign off their own
commits:

```bash
git commit -s
```

This adds a `Signed-off-by` trailer to your commit message. See
[developercertificate.org](https://developercertificate.org/) for the full text.

Pull requests are squash-merged, and the maintainer adds their sign-off to the squash
commit. Put the `Signed-off-by` line in the last block of the message, next to the
`Co-authored-by` lines GitHub adds, with no blank line in between; otherwise git does not
read it as a trailer. When merging an external contribution, keep the contributor's
`Signed-off-by` lines in the squash message as well. An AI assistant never signs off: the person who submits AI-assisted work signs
off for it, and a `Co-authored-by` trailer may show the assistant's involvement. The
`DCO` workflow checks every commit pushed to `main` and fails when a person's sign-off
is missing. Commits authored by bots (Dependabot, release-please) are exempt.

## Releases

release-please opens the release PR from the Conventional Commits on `main`. Merging
it creates the release; the release workflow then publishes the images and attaches
SPDX SBOMs (source lockfiles, `cividash-app`, `cividash-web`) to the GitHub release.
CI also builds the source SBOM for every pull request, so a broken scan shows up early.

Update [NOTICE](NOTICE) when a release adds or removes a bundled third-party asset.

## Code of conduct

By participating in this project, you agree to abide by the
[Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting security issues

Please do not report security vulnerabilities through public issues. See
[SECURITY.md](SECURITY.md) for the responsible disclosure process.
