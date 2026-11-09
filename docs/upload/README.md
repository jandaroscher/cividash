# Upload Bundles

This directory contains the schema definition and examples for the upload workflow.

- Schemas: [`schemas/v1/`](./schemas/v1/), JSON Schema draft-07
- Examples: [`examples/`](./examples/)

## Schemas

| File | Purpose |
|---|---|
| `bundle.schema.json` | Root envelope (`schema_version`, `mode`, `data`, optional `category_groups`, `categories`). |
| `row.schema.json` | One row in `data`, a (tile, metric, value) triple. |
| `category-group.schema.json` | Structural data: category group (e.g. "Handlungsfelder"). |
| `category.schema.json` | Structural data: a single category (e.g. "Energie"). |

## Examples

### Valid

| File | Scenario |
|---|---|
| `valid-minimal.json` | Smallest possible bundle, one tile without metrics. |
| `valid-full.json` | Complete with category structure, multiple tiles, multiple years. |
| `valid-structure-only.json` | Structure only (categories/groups), no data rows. Useful for initial tenant setup. |

### Invalid (for testing validation)

| File | Expected error |
|---|---|
| `invalid-missing-required.json` | `tile.slug` missing in the second row (schema). |
| `invalid-bad-types.json` | Wrong types + slug violates the regex + year < 1900. |
| `invalid-unresolved-references.json` | `category.keys` references an unknown category. |
| `invalid-bad-envelope.json` | `schema_version=2.0`, `mode=merge`, `locale=fr`, unknown row field. |

## Validating locally

With `ajv-cli` (via npx):

```bash
# Validate all valid examples, all should pass
npx --yes ajv-cli validate \
  -s docs/upload/schemas/v1/bundle.schema.json \
  -r "docs/upload/schemas/v1/*.schema.json" \
  -d "docs/upload/examples/valid-*.json"

# Check invalid examples, all should throw errors
for f in docs/upload/examples/invalid-*.json; do
  echo "--- $f"
  npx --yes ajv-cli validate \
    -s docs/upload/schemas/v1/bundle.schema.json \
    -r "docs/upload/schemas/v1/*.schema.json" \
    -d "$f" || true
done
```

(Note: `invalid-unresolved-references.json` is _schema-valid_; the error only occurs during business validation, because the category reference is resolved at runtime.)

## Periods

A row's value belongs to a period:

- `value.period_key` with `value.granularity`: the period in ISO form, for example `2024` (`year`), `2024-Q1` (`quarter`), `2024-05` (`month`), `2024-W05` (`week`) or `2024-05-15` (`day`).
- `value.year`: the v1 field. It is used only when `value.period_key` is absent and maps to the year period `2024`. Year-only bundles import unchanged. If both are set, `value.year` must be the leading year of `value.period_key`.

A tile has one granularity, `tile.time_granularity` (default `year`). The import sets it when it creates the tile and rejects a different value for an existing tile. Every value of a tile must have the tile's granularity; the import rejects other rows with `granularity_mismatch`.

## Round-trip with the JSON export

The JSON export of tenant data conforms to this schema. An exported bundle can be re-imported into another tenant unchanged as an upload.

- The export writes `metric.key`, which groups values into metric definitions on import.
- The export writes `value.period_key` and `value.granularity` verbatim, plus `value.year` as the leading year of the period. Sub-year periods therefore survive the round-trip.
- The export writes `tile.time_granularity`, so a quarterly tile is created as a quarterly tile.
- The JSON export writes `tile.description` and `tile.hint` as the stored HTML. The CSV export writes them as plain text.
- The export writes translatable fields (`tile.title`, `metric.label`, ...) as plain strings in the export `locale`, and `null` where that locale has no translation. The import stores a plain string as the translation for the bundle `locale` (default `de`). Export with `locale=de` to create new tiles, because a new tile needs `tile.title.de`.
