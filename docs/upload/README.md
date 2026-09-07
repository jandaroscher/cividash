# Upload-Bundles

Dieses Verzeichnis enthält die Schema-Definition und Beispiele für den Upload-Workflow.

- Schemas: [`schemas/v1/`](./schemas/v1/), JSON Schema draft-07
- Beispiele: [`examples/`](./examples/)

## Schemas

| Datei | Zweck |
|---|---|
| `bundle.schema.json` | Root-Envelope (`schema_version`, `mode`, `data`, optional `category_groups`, `categories`). |
| `row.schema.json` | Eine Zeile in `data`, ein (Tile, Metrik, Wert)-Tripel. |
| `category-group.schema.json` | Strukturdaten: Kategorie-Gruppe (z. B. „Handlungsfelder"). |
| `category.schema.json` | Strukturdaten: Einzelne Kategorie (z. B. „Energie"). |

## Beispiele

### Gültig

| Datei | Szenario |
|---|---|
| `valid-minimal.json` | Kleinstmögliches Bundle, ein Tile ohne Metriken. |
| `valid-full.json` | Vollständig mit Kategorie-Struktur, mehreren Tiles, mehreren Jahren. |
| `valid-structure-only.json` | Nur Struktur (Kategorien/Gruppen), keine Daten-Rows. Nützlich zum initialen Tenant-Setup. |

### Ungültig (zum Testen der Validierung)

| Datei | Erwarteter Fehler |
|---|---|
| `invalid-missing-required.json` | `tile.slug` fehlt in zweiter Row (schema). |
| `invalid-bad-types.json` | Falsche Typen + Slug verletzt Regex + Jahr < 1900. |
| `invalid-unresolved-references.json` | `category.keys` referenziert unbekannte Kategorie. |
| `invalid-bad-envelope.json` | `schema_version=2.0`, `mode=merge`, `locale=fr`, unbekanntes Zeilen-Feld. |

## Lokal validieren

Mit `ajv-cli` (via npx):

```bash
# Alle gültigen Beispiele validieren, sollten alle durchgehen
npx --yes ajv-cli validate \
  -s docs/upload/schemas/v1/bundle.schema.json \
  -r "docs/upload/schemas/v1/*.schema.json" \
  -d "docs/upload/examples/valid-*.json"

# Ungültige Beispiele prüfen, sollten alle Fehler werfen
for f in docs/upload/examples/invalid-*.json; do
  echo "--- $f"
  npx --yes ajv-cli validate \
    -s docs/upload/schemas/v1/bundle.schema.json \
    -r "docs/upload/schemas/v1/*.schema.json" \
    -d "$f" || true
done
```

(Hinweis: `invalid-unresolved-references.json` ist _schema-gültig_, der Fehler entsteht erst bei fachlicher Validierung, weil die Kategorie-Referenz erst zur Laufzeit aufgelöst wird.)

## Round-Trip mit-Export

Der JSON-Export der Tenant-Daten erfüllt dieses Schema. Ein exportiertes Bundle kann ohne Änderung als Upload in einen anderen Tenant eingespielt werden. Siehe [`docs/architecture/upload-workflow.md`](../architecture/upload-workflow.md#round-trip-mit-export).
