# Changelog

## 1.0.0 (2026-09-23)


### Features

* **admin:** CiviDash logo and favicon in the Filament panel ([193f5d2](https://github.com/jandaroscher/cividash/commit/193f5d234485d468c76ddd96f843093af87b371a))
* bulk write-back of tiles to core ([e395f70](https://github.com/jandaroscher/cividash/commit/e395f703fe51435d4522babfad02b521cb083607))
* keycloak sso for the civitas demo stack ([ba40558](https://github.com/jandaroscher/cividash/commit/ba40558eb39ec7036a997146d656d7b9c01eb9fb))


### Bug Fixes

* **api:** return 401 JSON for unauthenticated API requests without JSON Accept header ([dceb2db](https://github.com/jandaroscher/cividash/commit/dceb2dba6a5d9edc62cddb859dfb7a73ea23447c))
* **deploy:** load the schema dump on MariaDB and gate deploys on migrate ([6ea849f](https://github.com/jandaroscher/cividash/commit/6ea849f53e15177204d2e183138545353b0fa838))
* **deploy:** pre-build keycloak and stop the migrate restart loop ([beeab0f](https://github.com/jandaroscher/cividash/commit/beeab0f770006c61a7d4826ec61428e4613fecc5))
* **deps:** bump js-yaml and brace-expansion to patched versions ([c04e09b](https://github.com/jandaroscher/cividash/commit/c04e09b117a7877f034736a2c3ad95e7b9205e16))
* **docker:** detect Docker embedded DNS via own hostname, not the FPM host ([f2db44b](https://github.com/jandaroscher/cividash/commit/f2db44b40eb2a2533e20a137026a03f231f56897))
* **docker:** detect Docker embedded DNS via own service name ([6748e73](https://github.com/jandaroscher/cividash/commit/6748e7360d96b33b3b931d7db37262d2f4f4699a))
* **docker:** make production images work on Kubernetes and fresh databases ([2bf838d](https://github.com/jandaroscher/cividash/commit/2bf838df4e8c28787067255d5404d840a9a3c0c9))
* **docker:** pick the nginx resolver by platform instead of probing ([887b587](https://github.com/jandaroscher/cividash/commit/887b587b31f03f5b9e3eb44d7601b9f76b6cd3e3))
* **docker:** probe nameservers for the nginx resolver instead of taking the first one ([06369c3](https://github.com/jandaroscher/cividash/commit/06369c3b4013d9c0a09e0df39a1c96608ce7c585))
* **docker:** probe search domains for the FPM upstream on Kubernetes ([1da89e3](https://github.com/jandaroscher/cividash/commit/1da89e311bb4e76202e9781b531be3575df1e74a))
* **docker:** qualify the FPM upstream with the search domain on Kubernetes ([8b52837](https://github.com/jandaroscher/cividash/commit/8b52837b212f83db2c1cbcf0544a826c79d23fa8))
* **docker:** run cividash-web rootless on nginx-unprivileged, port 8080 ([8469ea1](https://github.com/jandaroscher/cividash/commit/8469ea1cf8373a3792ffa75cda37816e83a57554))
* **frontend:** render category groups as a column grid instead of a grey bar ([6602bab](https://github.com/jandaroscher/cividash/commit/6602babc43c794de0d0db82c1b29442abfe87ed7))
* **frontend:** self-host Open Sans, Roboto and Inter via fontsource instead of system fallback ([3e213e9](https://github.com/jandaroscher/cividash/commit/3e213e934a61ff635405d815a118400acac9f4c9))
* **keycloak:** cap the JVM heap below the container limit ([fae4396](https://github.com/jandaroscher/cividash/commit/fae4396c60dcf6b40c3f3af5ed05794108384c84))
* **privacy:** remove third-party font and avatar requests from the frontend ([860cf1e](https://github.com/jandaroscher/cividash/commit/860cf1ea868c544bfff06c914a3749e56aa594b0))
* **security:** enforce admin role for session-authenticated admin API, sanitize download block, verify Keycloak email before linking ([e7e92d1](https://github.com/jandaroscher/cividash/commit/e7e92d16572e545ecdd6377afad60280e8b15e7c))
* **security:** enforce admin-api ability on tenant user API, add security.txt, drop legacy tenant fallbacks ([9c0a7f0](https://github.com/jandaroscher/cividash/commit/9c0a7f08a6873356e8a756159987475276e41e78))
* **security:** sanitize uploaded SVG icons and logos ([429924f](https://github.com/jandaroscher/cividash/commit/429924ff109e648161e9de621bf22bf389290d17))
* skip slug validation for landing pages so the homepage saves ([6f9bacd](https://github.com/jandaroscher/cividash/commit/6f9bacddcfc03cf80ed22b8acf926e68ced1ecba))
* **tiles:** include indicator labels in tile search ([99e750c](https://github.com/jandaroscher/cividash/commit/99e750cd72553a9a763b1d2349882eae257befb1))
* **tiles:** keep tile subheader sentence intact on stray periods ([9ecdd56](https://github.com/jandaroscher/cividash/commit/9ecdd5663aadaca892008b0c0588a405de79ad24))


### Documentation

* add user guide section 6.1.3 Themes / Templates ([199bccd](https://github.com/jandaroscher/cividash/commit/199bccd606a32309bb024d7797795a5a215f81a4))
* concept for optional bidirectional CIVITAS auto-sync ([225333d](https://github.com/jandaroscher/cividash/commit/225333d9347c7f8be6f182ca38c417d23ea9644c))
* describe the AI-assisted development workflow ([ed7cec1](https://github.com/jandaroscher/cividash/commit/ed7cec11fefa7dd1cff9cd0936b1678f7857d6ab))
* describe the retained root page after the Demo City reset ([41a62dc](https://github.com/jandaroscher/cividash/commit/41a62dc40f3734661e03e6aa7116993b4241fbc9))
* document landing-page fixed slug + refresh edit-page screenshots ([a09cedd](https://github.com/jandaroscher/cividash/commit/a09cedd4fd17dd5e4c0637c96863bb672c59b233))
* document the privacy defaults ([8969324](https://github.com/jandaroscher/cividash/commit/89693243dc36c42b338aa0a4d58e688a5a59fe68))
* translate remaining German developer documentation to English ([e28c2b9](https://github.com/jandaroscher/cividash/commit/e28c2b957dcae845c5076c6e9266be7571ccde87))
* update documentation and SSO tests ([e61b07f](https://github.com/jandaroscher/cividash/commit/e61b07f96d53d3fd1e35d55246ab81fc39da5d02))
* update issue links ([b544c20](https://github.com/jandaroscher/cividash/commit/b544c20378eed2635f021e1cdb6499b14cdb38d2))
* update the deployment guide ([e03ef02](https://github.com/jandaroscher/cividash/commit/e03ef022e6b14e963ce04ff4e35478e647c694a8))
* user guide corrections round 3: ch.4/6/7 restructure + WIP markers ([4d33edb](https://github.com/jandaroscher/cividash/commit/4d33edba06e1bfd2f238b7b83c9e5fc37e937afd))

## Changelog

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

- Database migrations up to September 2026 are squashed into schema dumps (`database/schema/*.sql` for MariaDB, MySQL, PostgreSQL and SQLite). Fresh installations load the dump, existing installations are unaffected. Settings migrations stay as files because they carry default values.
- Stop tracking generated Filament CSS/JS assets in git; they are published via
  `filament:assets` (already wired into `composer.json`'s `post-autoload-dump` and the
  production Docker image), so every install/deploy regenerates them automatically
