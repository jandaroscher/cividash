# Timezone and Timestamps

## Overview

The dashboard uses the `APP_TIMEZONE` environment variable to control the timezone for all timestamps in the backend. The default is `Europe/Berlin`.

The only exception is the "server time" display in the "Overview" module, which always shows UTC. All other timestamps (API keys, Carbon instances, database records) use the configured timezone.

## Configuration

### `.env`

```dotenv
APP_TIMEZONE=Europe/Berlin
```

### `config/app.php`

```php
'timezone' => env('APP_TIMEZONE', 'Europe/Berlin'),
```

If no value is set in `.env`, `Europe/Berlin` is used as the fallback.

### Common values

| Timezone | Usage |
|----------|-------|
| `Europe/Berlin` | Germany (default) |
| `Europe/Vienna` | Austria |
| `Europe/Zurich` | Switzerland |
| `UTC` | Coordinated Universal Time |

A complete list of all supported timezones can be found in the [PHP documentation](https://www.php.net/manual/en/timezones.php).

## Effects

| Area | Timezone | Example |
|------|----------|---------|
| Overview → server time | UTC | `UTC 2026-03-03 14:30:00` |
| Overview → local time | `APP_TIMEZONE` | `Europe/Berlin 2026-03-03 15:30:00` |
| API keys → created / last used | `APP_TIMEZONE` | automatic |
| All `Carbon::now()` calls | `APP_TIMEZONE` | automatic |
| Database timestamps (`created_at`, `updated_at`) | `APP_TIMEZONE` | automatic |

## Daylight saving time (DST)

PHP uses the IANA timezone database (tzdata). The switch between standard and daylight saving time happens automatically, without manual intervention.

Example `Europe/Berlin`:
- Winter: CET (UTC+1)
- Summer: CEST (UTC+2)

PHP updates usually bring updated tzdata with them. On Linux systems, the database can also be updated via the `tzdata` package.

Important: UTC offsets (e.g. `+02:00`) don't know about DST, so always use named timezones such as `Europe/Berlin`.

## Troubleshooting

### Timestamps are off by one hour

- Check that `APP_TIMEZONE` is set correctly in `.env`.
- Make sure a named timezone is used, not a UTC offset.

### Wrong times after a PHP update

- Check whether the `tzdata` package on the server is up to date:
  ```bash
  # Debian/Ubuntu
  apt list --installed 2>/dev/null | grep tzdata

  # Alpine
  apk info tzdata
  ```
- Check the PHP version and timezone database:
  ```bash
  php -r "echo timezone_version_get();"
  ```

### Timezone has no effect

- Check that `config/app.php` reads the value from `.env`: `env('APP_TIMEZONE', 'Europe/Berlin')`.
- Clear the config cache:
  ```bash
  php artisan config:clear
  ```
