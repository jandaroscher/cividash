# Datenbank-Seeding Dokumentation

## Übersicht

Das Projekt verwendet mehrere Artisan-Befehle zum Seeding der Datenbank mit initialen Daten. Diese Dokumentation beschreibt alle verfügbaren Seeding-Befehle, deren Verwendung und die empfohlene Ausführungsreihenfolge.

## Verfügbare Seeding-Befehle

### 1. DatabaseSeeder (`db:seed`)

**Befehl:**
```bash
php artisan db:seed
```

**Beschreibung:**
Genereller Seeder für grundlegende Anwendungsdaten. Erstellt initiale User-Accounts und andere Basis-Daten.

**Aktueller Inhalt:**
- Erstellt einen Test-User:
  - Name: "Test User"
  - Email: `test@example.com`
  - Passwort: `password` (Standard aus UserFactory)

**Wann ausführen:**
- Bei der ersten Installation der Anwendung
- Wenn keine User in der Datenbank existieren
- Wird automatisch beim Deployment ausgeführt (siehe `deploy/post_deploy.sh`)

**Automatische Ausführung:**
Das Deployment-Skript (`deploy/post_deploy.sh`) führt den `DatabaseSeeder` automatisch aus, wenn keine User in der Datenbank existieren:

```bash
# Nur wenn USER_COUNT = 0
php artisan db:seed --force
```

**Optionen:**
- `--force` : Erzwingt Seeding ohne Bestätigung (wichtig für automatisierte Deployments)
- `--class=ClassName` : Führt nur einen spezifischen Seeder aus

**Beispiel:**
```bash
cd ~/html/cividash-backend
php artisan db:seed --force
```

---

### 2. Pages & Navigation Seeding (`pages:seed`)

**Befehl:**
```bash
php artisan pages:seed
```

**Beschreibung:**
Seeded Fabricator Pages und Header/Footer Navigation von der Referenz-Website (Regensburg).

**Was wird geseedet:**

**Fabricator Pages:**
- Home (`/`)
- Kontakt/Contact (`/kontakt`, `/en/contact`)
- Download (`/download`, `/en/download`)
- Datenschutz/Privacy (`/datenschutz`, `/en/privacy`)
- Impressum/Imprint (`/impressum`, `/en/imprint`)

**Navigation:**
- **Header Navigation:**
  - Download
  - Kontakt
- **Footer Navigation:**
  - Impressum
  - Datenschutz
  - regensburg.de
  - mein.regensburg.de

**Optionen:**
- `--dry-run` : Führt einen Testlauf durch, ohne Daten zu speichern (zeigt nur eine Zusammenfassung)

**Beispiel:**
```bash
cd ~/html/cividash-backend

# Normal
php artisan pages:seed

# Testlauf
php artisan pages:seed --dry-run
```

**Hinweis:**
Dieser Befehl ruft die folgenden Seeder auf:
- `PageSeeder` - Erstellt/aktualisiert Fabricator Pages
- `NavigationSeeder` - Erstellt Header und Footer Navigation

---

### 3. Dashboard Seeding (`dashboard:seed`)

**Befehl:**
```bash
php artisan dashboard:seed --path=/pfad/zur/dashboard.json
```

**Beschreibung:**
Seeded Tiles, Categories, Metrics, SDG-Ziele und deren Beziehungen aus einer `dashboard.json` Datei (Regensburg-Format).

**Was wird geseedet:**

1. **Handlungsfelder (Categories)**
   - Lädt Kategorien aus `dashboard.json`
   - Lädt zugehörige Medien-Dateien herunter (wenn aktiviert)

2. **Handlungsdimensionen (Dimensions)**
   - Erstellt 3 statische Dimensionen:
     - Gerechtigkeit (Justice)
     - Produktivität (Productivity)
     - Grün (Green)
   - Verknüpft Dimensionen mit Handlungsfeldern

3. **SDG-Ziele (SDG Goals)**
   - Lädt SDG-Ziele aus `dashboard.json`
   - Lädt zugehörige Medien-Dateien herunter

4. **Tiles (Kacheln)**
   - Lädt Tiles aus `dashboard.json`
   - Verknüpft Tiles mit:
     - Handlungsfeldern (Categories)
     - Handlungsdimensionen (Dimensions)
     - SDG-Zielen

5. **Metrics (Kennzahlen)**
   - Lädt Metrics aus `dashboard.json`
   - Verknüpft Metrics mit Tiles

**Optionen:**

- `--path=` : Pfad zur `dashboard.json` Datei
  - Optional: Wenn nicht angegeben, wird der Standard-Pfad verwendet
  - Standard: `storage/app/seeds/regensburg/dashboard.json`
  - Kann auch als relativer Pfad angegeben werden (z. B. `dashboard.json` im Root)

- `--url=` : URL, von der `dashboard.json` heruntergeladen wird
  - Lädt die Datei herunter und speichert sie lokal unter `storage/app/seeds/regensburg/dashboard.json`
  - Danach wird wie gewohnt mit der lokalen Datei geseedet
  - Beispiel: `--url=https://zukunft.regensburg.de/dashboard.json`

- `--tenant=` : Tenant-Identifier (für zukünftige Multi-Tenant-Unterstützung)
  - Aktuell noch nicht implementiert

- `--dry-run` : Führt einen Testlauf durch, ohne Daten zu speichern
  - Zeigt eine Zusammenfassung der zu seedenden Daten
  - Nützlich zum Testen vor dem eigentlichen Seeding

**Beispiele:**

```bash
cd ~/html/cividash-backend

# Mit dashboard.json im Root-Verzeichnis
php artisan dashboard:seed --path=dashboard.json

# Mit absolutem Pfad
php artisan dashboard:seed --path=/var/www/dashboard.json

# Von URL herunterladen und seeden
php artisan dashboard:seed --url=https://zukunft.regensburg.de/dashboard.json

# Mit Standard-Pfad (storage/app/seeds/regensburg/dashboard.json)
php artisan dashboard:seed

# Testlauf
php artisan dashboard:seed --path=dashboard.json --dry-run
```

**Konfiguration:**

Die Seeding-Konfiguration befindet sich in `config/seeding.php`:

- `default_json_path` : Standard-Pfad zur dashboard.json
  - Kann über `.env` Variable `SEED_DASHBOARD_JSON` überschrieben werden
- `dashboard_json_url` : URL zum Herunterladen der dashboard.json (für `dashboard:reset`)
  - `.env` Variable: `SEED_DASHBOARD_JSON_URL` (Standard: `https://zukunft.regensburg.de/dashboard.json`)
- `media_download_enabled` : Aktiviert/Deaktiviert das Herunterladen von Medien-Dateien
  - `.env` Variable: `SEED_MEDIA_DOWNLOAD` (Standard: `true`)
- `media_base_url` : Basis-URL für Medien-Downloads
  - `.env` Variable: `SEED_MEDIA_BASE_URL` (Standard: `https://zukunft.regensburg.de/files`)

**Hinweis:**
Dieser Befehl ruft die folgenden Seeder auf (in dieser Reihenfolge):
1. `CategorySeeder` - Handlungsfelder
2. `HandlungsdimensionSeeder` - Handlungsdimensionen
3. `SDGZielSeeder` - SDG-Ziele
4. `TileSeeder` - Tiles
5. `MetricSeeder` - Metrics

---

### 4. Demo-Daten Reset (`dashboard:reset`)

**Befehl:**
```bash
php artisan dashboard:reset
php artisan dashboard:reset --force
```

**Beschreibung:**
Setzt die Demo-Tenants für die öffentliche Testphase zurück:
- **Regensburg** (`stadt-regensburg`): Wird auf den aktuellen Stand von `zukunft.regensburg.de` zurückgesetzt (Daten löschen + neu seeden)
- **Demo City** (`demo-city`): Wird auf einen leeren Zustand zurückgesetzt (Daten löschen, leere Sandbox für Tester)

Domain- und User-Zuordnungen der Tenants bleiben erhalten.

**Ablauf:**
1. Aktuelle `dashboard.json` von der konfigurierten URL herunterladen (`SEED_DASHBOARD_JSON_URL`)
2. **Regensburg zurücksetzen:**
   - Alle Inhalte des Tenants löschen (FK-sichere Reihenfolge)
   - Neu seeden: `dashboard:seed` + `pages:seed` + `tenancy:backfill`
3. **Demo City zurücksetzen:**
   - Alle Inhalte des Tenants löschen → leere Sandbox

**Löschreihenfolge** (respektiert Foreign-Key-Constraints):
MetricValue → Metric → TileYear → MetricDefinition → BackgroundPage → category_tile (Pivot) → Tile → Category → CategoryGroup → Navigation → FooterNavigation → Page

**Optionen:**
- `--force` : Überspringt die Bestätigungsabfrage (für Cron-Einsatz)

**Beispiele:**
```bash
# Interaktiv mit Bestätigung
php artisan dashboard:reset

# Ohne Bestätigung (für Cron/Automatisierung)
php artisan dashboard:reset --force
```

**Automatische Ausführung (Nightly Cron):**

Der Command ist als nächtlicher Cron-Job konfiguriert (siehe [Scheduler & Cron-Setup](#scheduler--cron-setup)).

---

## Empfohlene Ausführungsreihenfolge

### Bei der ersten Installation:

```bash
cd ~/html/cividash-backend

# 1. Genereller Seeder (User, etc.) - nur wenn keine User existieren
php artisan db:seed --force

# 2. Pages und Navigation
php artisan pages:seed

# 3. Dashboard-Daten (mit dashboard.json im Root)
php artisan dashboard:seed --path=dashboard.json
```

### Bei Updates (wenn Daten bereits existieren):

```bash
cd ~/html/cividash-backend

# 1. Pages und Navigation (kann mehrfach ausgeführt werden)
php artisan pages:seed

# 2. Dashboard-Daten (überschreibt/aktualisiert bestehende Daten)
php artisan dashboard:seed --path=dashboard.json
```

**Wichtig:**
- `db:seed` sollte nur bei der ersten Installation ausgeführt werden
- `pages:seed` und `dashboard:seed` können mehrfach ausgeführt werden (aktualisieren bestehende Daten)

---

## Troubleshooting

### Problem: "File not found" bei dashboard:seed

**Lösung:**
- Prüfe, ob die `dashboard.json` Datei am angegebenen Pfad existiert
- Verwende absoluten Pfad oder stelle sicher, dass der relative Pfad vom Projekt-Root aus korrekt ist
- Prüfe Dateiberechtigungen

### Problem: "No users found" beim Deployment

**Lösung:**
- Das ist normal bei der ersten Installation
- Der `DatabaseSeeder` wird automatisch ausgeführt
- Wenn User manuell erstellt wurden, wird der Seeder übersprungen

### Problem: Medien-Dateien werden nicht heruntergeladen

**Lösung:**
- Prüfe `.env` Variable `SEED_MEDIA_DOWNLOAD=true`
- Prüfe `.env` Variable `SEED_MEDIA_BASE_URL` (Standard: `https://zukunft.regensburg.de/files`)
- Prüfe Schreibrechte für `storage/app/seeds/`
- Prüfe Netzwerk-Verbindung zur Media-Base-URL

### Problem: Seeding schlägt mit Datenbank-Fehlern fehl

**Lösung:**
- Stelle sicher, dass alle Migrationen ausgeführt wurden: `php artisan migrate --force`
- Prüfe Datenbank-Verbindung in `.env`
- Prüfe Logs: `storage/logs/laravel.log`
- Führe Seeding mit `--dry-run` aus, um Probleme zu identifizieren

---

## Scheduler & Cron-Setup

### Überblick

Der Laravel Scheduler führt `dashboard:reset --force` jede Nacht um 03:00 Uhr aus – aber **nur wenn `APP_ENV=production`** in der `.env` gesetzt ist. Lokal und auf Staging passiert nichts.

Definiert in `routes/console.php`:
```php
Schedule::command('dashboard:reset --force')
    ->daily()
    ->at('03:00')
    ->environments(['production']);
```

### Cron einrichten (einmalig auf dem Produktionsserver)

Damit der Laravel Scheduler überhaupt läuft, muss **ein einziger System-Cronjob** auf dem Server angelegt werden:

```bash
# Per SSH auf dem Server einloggen, dann:
crontab -e
```

Folgende Zeile hinzufügen:
```
* * * * * cd /pfad/zum/projekt && php artisan schedule:run >> /dev/null 2>&1
```

> **Hinweis:** Der Pfad muss dem tatsächlichen Projektpfad auf dem Server entsprechen (z. B. der Wert aus dem GitHub Secret `PATH_PROD`).

Der Cron läuft jede Minute. Laravel prüft intern, welche Commands fällig sind, und führt nur die geplanten aus.

### Verifizierung

Nach dem Einrichten des Cronjobs kann man prüfen, ob der Scheduler korrekt konfiguriert ist:

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

---

## Weitere Informationen

- **Seeder-Dateien:** `database/seeders/`
- **Command-Dateien:** `app/Console/Commands/`
- **Konfiguration:** `config/seeding.php`
- **Deployment-Skript:** `deploy/post_deploy.sh`

---

## Best Practices

1. **Immer `--dry-run` zuerst verwenden:**
   ```bash
   php artisan dashboard:seed --path=dashboard.json --dry-run
   ```

2. **Backup vor Seeding:**
   - Erstelle ein Datenbank-Backup vor dem Seeding, besonders bei Updates

3. **Logs prüfen:**
   - Seeding-Fehler werden in `storage/logs/laravel.log` geloggt
   - Prüfe Logs bei Problemen

4. **Testumgebung:**
   - Teste Seeding-Befehle zuerst in einer Testumgebung

5. **Versionierung:**
   - Versioniere die `dashboard.json` Datei, wenn möglich
   - Dokumentiere Änderungen an der JSON-Struktur
