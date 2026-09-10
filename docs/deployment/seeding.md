# Datenbank-Seeding

## Übersicht

Das Projekt nutzt mehrere Artisan-Befehle, um die Datenbank mit initialen Daten zu befüllen. Diese Seite beschreibt alle verfügbaren Seeding-Befehle, ihre Verwendung und die empfohlene Ausführungsreihenfolge.

## Verfügbare Seeding-Befehle

### 1. DatabaseSeeder (`db:seed`)

```bash
php artisan db:seed
```

Genereller Seeder für grundlegende Anwendungsdaten. Erstellt initiale User-Accounts und andere Basisdaten. Aktuell legt er einen Test-User an:

- Name: "Test User"
- E-Mail: `test@example.com`
- Passwort: über die Umgebungsvariable `SEED_ADMIN_PASSWORD` gesetzt, sonst generiert der Seeder
  ein zufälliges 20-Zeichen-Passwort und gibt es einmalig auf der Konsole aus (`Seeded admin
  test@example.com with password: ...`). Dasselbe gilt für `demo@example.com`, angelegt vom
  `TenantSeeder`. Ohne gesetzte Variable das Passwort direkt nach dem Seeding notieren, es wird
  nicht erneut angezeigt.

Wann ausführen:
- bei der ersten Installation der Anwendung
- wenn noch keine User in der Datenbank existieren
- läuft automatisch beim Deployment (siehe `deploy/post_deploy.sh`)

Das Deployment-Skript (`deploy/post_deploy.sh`) führt den `DatabaseSeeder` automatisch aus, wenn noch keine User in der Datenbank existieren:

```bash
# Nur wenn USER_COUNT = 0
php artisan db:seed --force
```

Optionen:
- `--force`: erzwingt das Seeding ohne Bestätigung (wichtig für automatisierte Deployments)
- `--class=ClassName`: führt nur einen bestimmten Seeder aus

Beispiel:
```bash
cd ~/html/cividash-backend
php artisan db:seed --force
```

### 2. Pages- und Navigation-Seeding (`pages:seed`)

```bash
php artisan pages:seed
```

Seedet Fabricator-Pages und Header-/Footer-Navigation von der Referenz-Website (Regensburg).

Fabricator Pages:
- Home (`/`)
- Kontakt/Contact (`/kontakt`, `/en/contact`)
- Download (`/download`, `/en/download`)
- Datenschutz/Privacy (`/datenschutz`, `/en/privacy`)
- Impressum/Imprint (`/impressum`, `/en/imprint`)

Navigation:
- Header: Download, Kontakt
- Footer: Impressum, Datenschutz, regensburg.de, mein.regensburg.de

Optionen:
- `--dry-run`: führt einen Testlauf durch, ohne Daten zu speichern, zeigt nur eine Zusammenfassung

Beispiel:
```bash
cd ~/html/cividash-backend

# Normal
php artisan pages:seed

# Testlauf
php artisan pages:seed --dry-run
```

Der Befehl ruft folgende Seeder auf:
- `PageSeeder` erstellt/aktualisiert die Fabricator Pages
- `NavigationSeeder` erstellt Header- und Footer-Navigation

### 3. Dashboard-Seeding (`dashboard:seed`)

```bash
php artisan dashboard:seed --path=/pfad/zur/dashboard.json
```

Seedet Tiles, Categories, Metrics, SDG-Ziele und deren Beziehungen aus einer `dashboard.json`-Datei (Regensburg-Format):

1. Handlungsfelder (Categories): lädt Kategorien aus `dashboard.json` und, wenn aktiviert, die zugehörigen Mediendateien.
2. Handlungsdimensionen (Dimensions): erstellt drei statische Dimensionen (Gerechtigkeit/Justice, Produktivität/Productivity, Grün/Green) und verknüpft sie mit den Handlungsfeldern.
3. SDG-Ziele (SDG Goals): lädt SDG-Ziele aus `dashboard.json` samt zugehöriger Mediendateien.
4. Tiles (Kacheln): lädt Tiles aus `dashboard.json` und verknüpft sie mit Handlungsfeldern, Handlungsdimensionen und SDG-Zielen.
5. Metrics (Kennzahlen): lädt Metrics aus `dashboard.json` und verknüpft sie mit Tiles.

Optionen:

- `--tenant=`: Tenant-Identifier für eine künftige Multi-Tenant-Unterstützung, aktuell noch nicht implementiert.
- `--dry-run`: führt einen Testlauf durch, zeigt eine Zusammenfassung der zu seedenden Daten, ohne sie zu speichern. Nützlich zum Testen vor dem eigentlichen Seeding.

Beispiele:

```bash
cd ~/html/cividash-backend

# Mit dashboard.json im Root-Verzeichnis
php artisan dashboard:seed --path=dashboard.json

# Mit absolutem Pfad
php artisan dashboard:seed --path=/var/www/dashboard.json

# Von URL herunterladen und seeden
php artisan dashboard:seed --url=https://zukunft.regensburg.de/dashboard.json

# Mit Standardpfad (storage/app/seeds/regensburg/dashboard.json)
php artisan dashboard:seed

# Testlauf
php artisan dashboard:seed --path=dashboard.json --dry-run
```

Die Seeding-Konfiguration liegt in `config/seeding.php`:

- `default_json_path`: Standardpfad zur dashboard.json, überschreibbar über die `.env`-Variable `SEED_DASHBOARD_JSON`
- `dashboard_json_url`: URL zum Herunterladen der dashboard.json für `dashboard:reset`, `.env`-Variable `SEED_DASHBOARD_JSON_URL` (Standard: `https://zukunft.regensburg.de/dashboard.json`)
- `media_download_enabled`: schaltet das Herunterladen von Mediendateien ein oder aus, `.env`-Variable `SEED_MEDIA_DOWNLOAD` (Standard: `true`)
- `media_base_url`: Basis-URL für Medien-Downloads, `.env`-Variable `SEED_MEDIA_BASE_URL` (Standard: `https://zukunft.regensburg.de/files`)

Der Befehl ruft folgende Seeder in dieser Reihenfolge auf:
1. `CategorySeeder` (Handlungsfelder)
2. `HandlungsdimensionSeeder` (Handlungsdimensionen)
3. `SDGZielSeeder` (SDG-Ziele)
4. `TileSeeder` (Tiles)
5. `MetricSeeder` (Metrics)

### 4. Demo-Daten-Reset (`dashboard:reset`)

```bash
php artisan dashboard:reset
php artisan dashboard:reset --force
```

Setzt die Demo-Tenants für die öffentliche Testphase zurück:
- Regensburg (`stadt-regensburg`): wird auf den aktuellen Stand von `zukunft.regensburg.de` zurückgesetzt (Daten löschen, dann neu seeden).
- Demo City (`demo-city`): wird auf einen leeren Zustand zurückgesetzt (Daten löschen, leere Sandbox für Tester).

Domain- und User-Zuordnungen der Tenants bleiben erhalten.

Ablauf:
1. Aktuelle `dashboard.json` von der konfigurierten URL herunterladen (`SEED_DASHBOARD_JSON_URL`).
2. Regensburg zurücksetzen: alle Inhalte des Tenants löschen (FK-sichere Reihenfolge), dann neu seeden mit `dashboard:seed` + `pages:seed` + `tenancy:backfill`.
3. Demo City zurücksetzen: alle Inhalte des Tenants löschen, es bleibt eine leere Sandbox.

Löschreihenfolge (respektiert Foreign-Key-Constraints): MetricValue, Metric, TimePeriod, MetricDefinition, BackgroundPage, category_tile (Pivot), Tile, Category, CategoryGroup, Navigation, FooterNavigation, Page.

Optionen:
- `--force`: überspringt die Bestätigungsabfrage, für den Cron-Einsatz

Beispiele:
```bash
# Interaktiv mit Bestätigung
php artisan dashboard:reset

# Ohne Bestätigung (für Cron/Automatisierung)
php artisan dashboard:reset --force
```

Der Command läuft als nächtlicher Cron-Job (siehe [Scheduler und Cron-Setup](#scheduler-und-cron-setup)).

## Empfohlene Ausführungsreihenfolge

### Bei der ersten Installation

```bash
cd ~/html/cividash-backend

# 1. Genereller Seeder (User usw.), nur wenn keine User existieren
php artisan db:seed --force

# 2. Pages und Navigation
php artisan pages:seed

# 3. Dashboard-Daten (mit dashboard.json im Root)
php artisan dashboard:seed --path=dashboard.json
```

### Bei Updates (wenn Daten bereits existieren)

```bash
cd ~/html/cividash-backend

# 1. Pages und Navigation (kann mehrfach ausgeführt werden)
php artisan pages:seed

# 2. Dashboard-Daten (überschreibt/aktualisiert bestehende Daten)
php artisan dashboard:seed --path=dashboard.json
```

`db:seed` gehört nur zur ersten Installation. `pages:seed` und `dashboard:seed` lassen sich mehrfach ausführen, sie aktualisieren bestehende Daten.

## Troubleshooting

### Problem: "File not found" bei dashboard:seed

- Prüfe, ob die `dashboard.json`-Datei am angegebenen Pfad existiert.
- Verwende einen absoluten Pfad oder stelle sicher, dass der relative Pfad vom Projekt-Root aus stimmt.
- Prüfe die Dateiberechtigungen.

### Problem: "No users found" beim Deployment

Das ist normal bei der ersten Installation. Der `DatabaseSeeder` läuft automatisch; wurden User bereits manuell angelegt, überspringt der Seeder diesen Schritt.

### Problem: Mediendateien werden nicht heruntergeladen

- Prüfe die `.env`-Variable `SEED_MEDIA_DOWNLOAD=true`.
- Prüfe die `.env`-Variable `SEED_MEDIA_BASE_URL` (Standard: `https://zukunft.regensburg.de/files`).
- Prüfe die Schreibrechte für `storage/app/seeds/`.
- Prüfe die Netzwerkverbindung zur Media-Base-URL.

### Problem: Seeding schlägt mit Datenbankfehlern fehl

- Stelle sicher, dass alle Migrationen ausgeführt wurden: `php artisan migrate --force`.
- Prüfe die Datenbankverbindung in `.env`.
- Prüfe die Logs: `storage/logs/laravel.log`.
- Führe das Seeding mit `--dry-run` aus, um Probleme einzugrenzen.

## Scheduler und Cron-Setup

### Überblick

Der Laravel-Scheduler führt `dashboard:reset --force` jede Nacht um 03:00 Uhr aus, aber nur wenn `APP_ENV=production` **und** `DASHBOARD_DEMO_RESET=true` in der `.env` gesetzt sind (Standard: `false`, kein Reset). Ohne gesetztes Flag ist der Schedule-Eintrag gar nicht erst registriert. Lokal, auf Staging und auf echten Produktions-Installationen ohne dieses Flag passiert nichts. Nur auf tatsächlichen Demo-/Showcase-Instanzen setzen, da der Befehl Tenant-Inhalte löscht/überschreibt (siehe `docs/deployment/installation-standalone.md`, Abschnitt Scheduler).

Definiert in `routes/console.php`:
```php
if (config('dashboard.demo_reset')) {
    Schedule::command('dashboard:reset --force')
        ->daily()
        ->at('03:00')
        ->environments(['production']);
}
```

### Cron einrichten (einmalig auf dem Produktionsserver)

Damit der Laravel-Scheduler überhaupt läuft, braucht der Server einen einzigen System-Cronjob:

```bash
# Per SSH auf dem Server einloggen, dann:
crontab -e
```

Folgende Zeile hinzufügen:
```
* * * * * cd /pfad/zum/projekt && php artisan schedule:run >> /dev/null 2>&1
```

Der Pfad muss dem tatsächlichen Projektpfad auf dem Server entsprechen (zum Beispiel dem Wert aus dem GitHub Secret `PATH_PROD`).

Der Cron läuft jede Minute; Laravel prüft intern, welche Commands fällig sind, und führt nur die geplanten aus.

### Verifizierung

Nach dem Einrichten des Cronjobs lässt sich prüfen, ob der Scheduler korrekt konfiguriert ist:

```bash
# Alle geplanten Commands anzeigen
php artisan schedule:list

# Scheduler einmalig manuell ausführen (zum Testen)
php artisan schedule:run

# Oder den Reset-Command direkt testen
php artisan dashboard:reset
```

### Umgebungsvariablen

| Variable | Standard | Beschreibung |
|----------|----------|-------------|
| `SEED_DASHBOARD_JSON_URL` | `https://zukunft.regensburg.de/dashboard.json` | URL für den nächtlichen Download der `dashboard.json` |
| `SEED_DASHBOARD_JSON` | `storage/app/seeds/regensburg/dashboard.json` | Lokaler Speicherpfad |

## Weitere Informationen

- Seeder-Dateien: `database/seeders/`
- Command-Dateien: `app/Console/Commands/`
- Konfiguration: `config/seeding.php`
- Deployment-Skript: `deploy/post_deploy.sh`

## Best Practices

1. Immer zuerst `--dry-run` verwenden:
   ```bash
   php artisan dashboard:seed --path=dashboard.json --dry-run
   ```
2. Vor dem Seeding ein Datenbank-Backup anlegen, besonders bei Updates.
3. Seeding-Fehler landen in `storage/logs/laravel.log`, bei Problemen dort nachsehen.
4. Seeding-Befehle zuerst in einer Testumgebung ausprobieren.
5. Die `dashboard.json`-Datei versionieren, wenn möglich, und Änderungen an der JSON-Struktur dokumentieren.
