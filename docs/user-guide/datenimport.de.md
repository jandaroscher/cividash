# Datenimport

Kacheln, Kategorien, Metriken und Jahreswerte lassen sich als JSON-Bundle in den Tenant importieren — statt jeden Datensatz einzeln im Backend anzulegen. Der Import läuft in zwei Schritten: erst Dry-Run mit Vorschau, dann Commit.

Das Bundle-Format ist identisch zum JSON-Export aus der Export-Funktion. Ein Export aus einem Tenant lässt sich direkt in einen anderen Tenant importieren (Round-Trip).

## Voraussetzungen

- Admin-Rolle im Zieltenant
- JSON-Datei gemäß Schema (siehe _Bundle-Format_ unten)
- Maximale Dateigröße: 20 MB

## Bundle-Format (JSON)

Ein Bundle hat einen Envelope und eine `data`-Liste. Jede Zeile beschreibt eine Kachel mit einer Metrik und einem Wert für ein Jahr.

```json
{
  "schema_version": "1.0",
  "mode": "upsert",
  "data": [
    {
      "tile.slug": "energieverbrauch",
      "tile.title": { "de": "Energieverbrauch", "en": "Energy Consumption" },
      "category.keys": ["handlungsfelder/energie"],
      "metric.key": "kwh_total",
      "metric.label": { "de": "Gesamtverbrauch" },
      "metric.unit": { "de": "GWh" },
      "value.year": 2024,
      "value.value": 3750
    }
  ]
}
```

**Pflichtfelder pro Zeile:**

- `tile.slug` — URL-sicherer Schlüssel der Kachel (Kleinbuchstaben, Bindestriche).
- `tile.title.de` — Pflicht beim Anlegen einer neuen Kachel.
- `value.year` + `metric.key` + `metric.label.de` — Pflicht, wenn ein Wert gesetzt wird.

**Optionale Strukturdaten** auf Bundle-Ebene:

- `category_groups` — Kategorie-Gruppen (z. B. „Handlungsfelder").
- `categories` — Einzelne Kategorien mit Referenz auf `group_key`.

**Schema und Beispiel-Bundle herunterladen:** Auf der Import-Seite oben rechts sind zwei Buttons: _Schema herunterladen_ (JSON Schema draft-07 zur Validierung im eigenen Editor oder CI-Pipeline) und _Beispiel-Bundle_ (vollständiges, valides Beispiel als Ausgangspunkt für eigene Importe). Die Schemas sind auch als API-Endpoints unter `/api/import/schemas/{bundle|row|category|category-group}` öffentlich erreichbar.

## Modi

| Modus | Verhalten |
|---|---|
| `upsert` (Standard) | Neue Datensätze werden angelegt, bestehende aktualisiert. Keine Löschungen. |
| `replace` | _(v1.1 — noch nicht implementiert; der Server lehnt mit HTTP 422 `unsupported_mode` ab)_ Zusätzlich werden Datensätze gelöscht, die nicht im Bundle stehen. |

## Import durchführen

1. Im Backend zu _System → Datenimport_ navigieren.
2. JSON-Datei per Drag-and-Drop oder Klick in das Upload-Feld laden.
3. **Dry-Run starten** klicken. Ergebnisse prüfen:
    - **Änderungen-Tabelle**: Anzahl neuer, aktualisierter, gelöschter und unveränderter Einträge pro Entität.
    - **Fehler**: Blockieren den Import. Mit Zeilennummer, Feldpfad und Erklärung.
    - **Warnungen**: Blockieren den Import nicht (z. B. wenn der Bundle-Tenant nicht zum eingeloggten Tenant passt).
4. Bei Fehlern: Datei korrigieren und erneut hochladen.
5. Bei grünem Dry-Run: **Import bestätigen** klicken. Alle Änderungen werden in einer einzigen Transaktion geschrieben.

## Dry-Run und Commit

Der Dry-Run führt intern denselben Code aus wie der Commit, verwirft die Änderungen am Ende aber in der Datenbank-Transaktion. Ein erfolgreicher Dry-Run garantiert, dass der Commit ebenfalls erfolgreich ist — vorausgesetzt, die Datenbank ändert sich zwischen Dry-Run und Commit nicht durch andere Nutzer.

## Atomare Transaktion

Der Import ist atomar: Scheitert eine einzige Zeile (falscher Typ, unbekannte Kategorie, fehlender Pflicht-Titel), wird der komplette Import zurückgerollt — nichts landet in der Datenbank.

## Fehlermeldungen interpretieren

Jeder Fehler hat folgende Struktur:

- **Row-Nummer** (wenn relevant) — Zeile im `data`-Array, 0-indiziert.
- **Code** — Maschinenlesbar. Beispiele: `required`, `invalid_type`, `invalid_format`, `invalid_enum_value`, `unresolved_reference`.
- **Pfad** — Punkt-Notation des betroffenen Feldes, z. B. `data[3].tile.title.de`.
- **Nachricht** — Erklärung für den Anwender.

## Historie

Unter dem Ergebnis-Bereich listet die Tabelle _Letzte Import-Läufe_ die letzten Durchläufe mit Zeit, Datei, Modus, Status, Fehleranzahl, Dauer und Benutzer. Sowohl Dry-Runs als auch Commits werden protokolliert.

## Tenant-Kontext

Der Tenant wird immer aus der eingeloggten Session (oder dem API-Token) abgeleitet. Ein `tenant.slug`-Feld im Bundle wird ignoriert — es gibt nur eine Warnung, falls Bundle-Tenant und Token-Tenant abweichen. Daten landen immer im eingeloggten Tenant.

## Admin-API (programmatisch)

Für automatisierte Pipelines gibt es den Endpoint `POST /api/admin/import` mit Bearer-Token-Authentifizierung. Rate-Limit: 5 Läufe pro Minute und Tenant. Dokumentation unter `/docs` (Scribe).

## Begrenzungen v1.0

- **Nur JSON.** CSV-Support folgt in v1.1.
- **Keine Assets** (Bilder, Favicon). Die werden weiterhin im Branding-Bereich hochgeladen.
- **Keine Content-Pages.** `docs/upload/README.md` beschreibt den geplanten Scope für Pages in einer späteren Version.
