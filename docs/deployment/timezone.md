# Zeitzone & Zeitstempel

## Übersicht

Das Dashboard verwendet die Umgebungsvariable `APP_TIMEZONE`, um die Zeitzone für alle Zeitstempel im Backend zu steuern. Standardmäßig ist `Europe/Berlin` eingestellt.

Die einzige Ausnahme ist die "Serverzeit"-Anzeige im Modul "Übersicht" — diese zeigt immer UTC. Alle anderen Zeitstempel (API-Keys, Carbon-Instanzen, Datenbankeinträge) verwenden die konfigurierte Zeitzone.

---

## Konfiguration

### `.env`

```dotenv
APP_TIMEZONE=Europe/Berlin
```

### `config/app.php`

```php
'timezone' => env('APP_TIMEZONE', 'Europe/Berlin'),
```

Wird kein Wert in `.env` gesetzt, gilt `Europe/Berlin` als Fallback.

### Gängige Werte

| Zeitzone | Verwendung |
|----------|------------|
| `Europe/Berlin` | Deutschland (Standard) |
| `Europe/Vienna` | Österreich |
| `Europe/Zurich` | Schweiz |
| `UTC` | Koordinierte Weltzeit |

Eine vollständige Liste aller unterstützten Zeitzonen findet sich in der [PHP-Dokumentation](https://www.php.net/manual/de/timezones.php).

---

## Auswirkungen

| Bereich | Zeitzone | Beispiel |
|---------|----------|----------|
| Übersicht → Serverzeit | UTC | `UTC 2026-03-03 14:30:00` |
| Übersicht → Lokale Zeit | `APP_TIMEZONE` | `Europe/Berlin 2026-03-03 15:30:00` |
| API-Keys → Erstellt / Zuletzt verwendet | `APP_TIMEZONE` | automatisch |
| Alle `Carbon::now()`-Aufrufe | `APP_TIMEZONE` | automatisch |
| Datenbank-Timestamps (`created_at`, `updated_at`) | `APP_TIMEZONE` | automatisch |

---

## Sommer-/Winterzeit (DST)

PHP nutzt die **IANA-Timezone-Datenbank** (tzdata). Die Umstellung zwischen Sommer- und Winterzeit wird automatisch gehandhabt — kein manueller Eingriff nötig.

**Beispiel `Europe/Berlin`:**
- Winter: CET (UTC+1)
- Sommer: CEST (UTC+2)

PHP-Updates bringen in der Regel eine aktualisierte tzdata mit. Auf Linux-Systemen kann die Datenbank auch über das Paket `tzdata` aktualisiert werden.

**Wichtig:** UTC-Offsets (z. B. `+02:00`) kennen **kein** DST — immer benannte Zeitzonen wie `Europe/Berlin` verwenden.

---

## Troubleshooting

### Zeitstempel sind um 1 Stunde verschoben

**Lösung:**
- Prüfe, ob `APP_TIMEZONE` in `.env` korrekt gesetzt ist
- Stelle sicher, dass eine benannte Zeitzone (nicht ein UTC-Offset) verwendet wird

### Nach PHP-Update falsche Zeiten

**Lösung:**
- Prüfe, ob das `tzdata`-Paket auf dem Server aktuell ist:
  ```bash
  # Debian/Ubuntu
  apt list --installed 2>/dev/null | grep tzdata

  # Alpine
  apk info tzdata
  ```
- PHP-Version und Timezone-Datenbank prüfen:
  ```bash
  php -r "echo timezone_version_get();"
  ```

### Zeitzone hat keine Auswirkung

**Lösung:**
- Prüfe, ob `config/app.php` den Wert aus `.env` liest: `env('APP_TIMEZONE', 'Europe/Berlin')`
- Config-Cache leeren:
  ```bash
  php artisan config:clear
  ```
