# Database Seeding

## Overview

The project uses several artisan commands to populate the database with initial data. This page describes all available seeding commands, their usage, and the recommended execution order.

## Available seeding commands

### 1. DatabaseSeeder (`db:seed`)

```bash
php artisan db:seed
```

General seeder for basic application data. Creates initial user accounts and other base data. It currently creates a test user:

- Name: "Test User"
- Email: `test@example.com`
- Password: set via the `SEED_ADMIN_PASSWORD` environment variable, otherwise the seeder
  generates a random 20-character password and prints it once on the console (`Seeded admin
  test@example.com with password: ...`). The same applies to `demo@example.com`, created by the
  `TenantSeeder`. Without the variable set, note the password down right after seeding, it is
  not shown again.

When to run:
- on the first installation of the application
- when no users exist yet in the database

`DatabaseSeeder` needs Faker, a dev dependency. A production install with `composer install --no-dev` cannot run it; seed only the roles there and create the admin with `cividash:create-admin` (see [installation-standalone.md](installation-standalone.md), step 8).

Options:
- `--force`: forces seeding without confirmation (important for automated deployments)
- `--class=ClassName`: runs only a specific seeder

Example:
```bash
cd ~/html/cividash-backend
php artisan db:seed --force
```

### 2. Pages and navigation seeding (`pages:seed`)

```bash
php artisan pages:seed
```

Seeds Fabricator pages and header/footer navigation from the reference website (Regensburg).

Fabricator pages:
- Home (`/`)
- Kontakt/Contact (`/kontakt`, `/en/contact`)
- Download (`/download`, `/en/download`)
- Datenschutz/Privacy (`/datenschutz`, `/en/privacy`)
- Impressum/Imprint (`/impressum`, `/en/imprint`)

Navigation:
- Header: Download, Kontakt
- Footer: Impressum, Datenschutz, regensburg.de, mein.regensburg.de

Options:
- `--dry-run`: performs a dry run without saving data, only shows a summary

Example:
```bash
cd ~/html/cividash-backend

# Normal
php artisan pages:seed

# Dry run
php artisan pages:seed --dry-run
```

The command calls the following seeders:
- `PageSeeder` creates/updates the Fabricator pages
- `NavigationSeeder` creates the header and footer navigation

### 3. Dashboard seeding (`dashboard:seed`)

```bash
php artisan dashboard:seed --path=/path/to/dashboard.json
```

Seeds tiles, categories, metrics, SDG goals, and their relationships from a `dashboard.json` file (Regensburg format):

1. Handlungsfelder (categories): loads categories from `dashboard.json` and, if enabled, the associated media files.
2. Handlungsdimensionen (dimensions): creates three static dimensions (Gerechtigkeit/Justice, Produktivität/Productivity, Grün/Green) and links them to the categories.
3. SDG goals: loads SDG goals from `dashboard.json` along with their media files.
4. Tiles: loads tiles from `dashboard.json` and links them to categories, dimensions, and SDG goals.
5. Metrics: loads metrics from `dashboard.json` and links them to tiles.

Options:

- `--path=`: path to the `dashboard.json` file. Optional; the default path `storage/app/seeds/regensburg/dashboard.json` applies if omitted. This file is not part of the repository and only exists after it has been downloaded with `--url`. A relative path is also possible (e.g. `dashboard.json` at the project root).
- `--url=`: URL from which `dashboard.json` is downloaded. The file ends up locally at `storage/app/seeds/regensburg/dashboard.json` and is then seeded as usual. Recommended as a first step before using `--path` without your own value. Example: `--url=https://zukunft.regensburg.de/dashboard.json`.
- `--dry-run`: performs a dry run, shows a summary of the data to be seeded without saving it. Useful for testing before the actual seeding.

Examples:

```bash
cd ~/html/cividash-backend

# With dashboard.json at the project root
php artisan dashboard:seed --path=dashboard.json

# With an absolute path
php artisan dashboard:seed --path=/var/www/dashboard.json

# Download from a URL and seed
php artisan dashboard:seed --url=https://zukunft.regensburg.de/dashboard.json

# With the default path (storage/app/seeds/regensburg/dashboard.json)
php artisan dashboard:seed

# Dry run
php artisan dashboard:seed --path=dashboard.json --dry-run
```

The seeding configuration lives in `config/seeding.php`:

- `default_json_path`: default path to dashboard.json, overridable via the `.env` variable `SEED_DASHBOARD_JSON`
- `dashboard_json_url`: URL for downloading dashboard.json for `dashboard:reset`, `.env` variable `SEED_DASHBOARD_JSON_URL` (no default; without it, `dashboard:reset` reseeds from the existing local file. Example: `https://zukunft.regensburg.de/dashboard.json`)
- `media_download_enabled`: toggles downloading media files, `.env` variable `SEED_MEDIA_DOWNLOAD` (default: `true`)
- `media_base_url`: root URL of the source site for media downloads, `.env` variable `SEED_MEDIA_BASE_URL`. Files load from `<base>/files/`, static icons from `<base>/assets/`. No default: without it, seeding skips media downloads and logs one info message. Example: `https://zukunft.regensburg.de`

The command calls the following seeders in this order:
1. `CategorySeeder::run()` (Handlungsfelder)
2. `CategorySeeder::seedDimensions()` (Handlungsdimensionen, 3 static entries)
3. `CategorySeeder::seedSdgZiele()` (SDG goals)
4. `TileSeeder::run()` (tiles)
5. `MetricSeeder::run()` (metrics, only if the parsed data contains any)

### 4. Demo data reset (`dashboard:reset`)

```bash
php artisan dashboard:reset
php artisan dashboard:reset --force
```

Resets the demo tenants on demo instances:
- Regensburg (`stadt-regensburg`): reset to the current state of the source site (delete data, then reseed).
  - With `SEED_DASHBOARD_JSON_URL` set, the reset downloads `dashboard.json` first. Without it, the reset reseeds from the local file and aborts before deleting anything if that file is missing.
  - With `SEED_MEDIA_BASE_URL` set, the reseed downloads icons and images. Without it, the reseed skips media.
- Demo City (`demo-city`): reset to an empty state (delete data, empty sandbox for testers).

The tenants' domains are preserved (only assigned if still empty). User assignments are not merely preserved: every existing user is (re-)attached to both `stadt-regensburg` and `demo-city` via `syncWithoutDetaching()`, without detaching anyone.

Process:
1. Download the current `dashboard.json` from the configured URL (`SEED_DASHBOARD_JSON_URL`).
2. Reset Regensburg: delete all content of the tenant (in FK-safe order), then reseed with `dashboard:seed` + `pages:seed` + `tenancy:backfill`.
3. Reset Demo City: delete all content of the tenant, leaving an empty sandbox.

Deletion order (respects foreign key constraints): MetricValue, Metric, TimePeriod, MetricDefinition, BackgroundPage, category_tile (pivot), Tile, Category, CategoryGroup, Navigation, FooterNavigation, Page.

Options:
- `--force`: skips the confirmation prompt, for cron usage

Examples:
```bash
# Interactive with confirmation
php artisan dashboard:reset

# Without confirmation (for cron/automation)
php artisan dashboard:reset --force
```

The command runs as a nightly cron job (see [Scheduler and Cron Setup](#scheduler-and-cron-setup)).

## Recommended execution order

### On first installation

```bash
cd ~/html/cividash-backend

# 1. General seeder (users etc.), only if no users exist
php artisan db:seed --force

# 2. Pages and navigation
php artisan pages:seed

# 3. Dashboard data (dashboard.json is untracked, download it first)
php artisan dashboard:seed --url=https://zukunft.regensburg.de/dashboard.json
```

### On updates (when data already exists)

```bash
cd ~/html/cividash-backend

# 1. Pages and navigation (can be run multiple times)
php artisan pages:seed

# 2. Dashboard data (overwrites/updates existing data; re-download since the file is untracked)
php artisan dashboard:seed --url=https://zukunft.regensburg.de/dashboard.json
```

`db:seed` only applies to the first installation. `pages:seed` and `dashboard:seed` can be run multiple times, they update existing data.

## Troubleshooting

### Problem: "File not found" on dashboard:seed

- Check whether the `dashboard.json` file exists at the given path.
- Use an absolute path or make sure the relative path is correct from the project root.
- Check the file permissions.

### Problem: no users after the first deployment

This is normal on the first installation. Seed the database or create an admin (see [installation-standalone.md](installation-standalone.md), step 8).

### Problem: media files are not downloaded

- Check the `.env` variable `SEED_MEDIA_DOWNLOAD=true`.
- Check that the `.env` variable `SEED_MEDIA_BASE_URL` is set to the source site root (e.g. `https://zukunft.regensburg.de`). Without it, no media is downloaded.
- Check the write permissions for `storage/app/seeds/`.
- Check the network connection to the media base URL.

### Problem: seeding fails with database errors

- Make sure all migrations have run: `php artisan migrate --force`.
- Check the database connection in `.env`.
- Check the logs: `storage/logs/laravel.log`.
- Run the seeding with `--dry-run` to narrow down the problem.

## Scheduler and Cron Setup

### Overview

The Laravel scheduler runs `dashboard:reset --force` every night at 03:00, but only if `APP_ENV=production` **and** `DASHBOARD_DEMO_RESET=true` are set in `.env` (default: `false`, no reset). Without the flag set, the schedule entry is not registered at all. Locally, on staging, and on real production installations without this flag, nothing happens. Only enable it on actual demo/showcase instances, since the command deletes/overwrites tenant content (see `docs/deployment/installation-standalone.md`, Scheduler section).

Defined in `routes/console.php`:
```php
if (config('dashboard.demo_reset')) {
    Schedule::command('dashboard:reset --force')
        ->daily()
        ->at('03:00')
        ->environments(['production']);
}
```

### Setting up cron (one-off, on the production server)

For the Laravel scheduler to run at all, the server needs a single system cron job:

```bash
# Log in via SSH to the server, then:
crontab -e
```

Add the following line:
```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

The path must match the actual project path on the server.

The cron runs every minute; Laravel internally checks which commands are due and only runs the scheduled ones.

### Verification

After setting up the cron job, you can check whether the scheduler is configured correctly:

```bash
# Show all scheduled commands
php artisan schedule:list

# Run the scheduler once manually (for testing)
php artisan schedule:run

# Or test the reset command directly
php artisan dashboard:reset
```

### Environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `SEED_DASHBOARD_JSON_URL` | (empty) | URL for the nightly download of `dashboard.json`, e.g. `https://zukunft.regensburg.de/dashboard.json` |
| `SEED_MEDIA_BASE_URL` | (empty) | Source site root for media downloads, e.g. `https://zukunft.regensburg.de` |
| `SEED_DASHBOARD_JSON` | `storage/app/seeds/regensburg/dashboard.json` | Local storage path |

## Further information

- Seeder files: `database/seeders/`
- Command files: `app/Console/Commands/`
- Configuration: `config/seeding.php`

## Best practices

1. Always use `--dry-run` first:
   ```bash
   php artisan dashboard:seed --path=dashboard.json --dry-run
   ```
2. Create a database backup before seeding, especially on updates.
3. Seeding errors land in `storage/logs/laravel.log`, check there in case of problems.
4. Try seeding commands in a test environment first.
5. Version the `dashboard.json` file if possible, and document changes to the JSON structure.
