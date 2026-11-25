# Data export

Visitors download tile data in the export dialog (tile detail page, filtered view, "Export
all tiles"). The same files come from the public API:

| Endpoint | Scope |
|---|---|
| `GET /api/v1/tiles/{slug}/export` | one tile |
| `GET /api/v1/exports/tiles` | tiles matching `tiles[]`, `categories[]`, `year_from`, `year_to` |
| `GET /api/v1/exports/catalog` | every public tile of the tenant |

All endpoints take `format` (`json`, `csv`, `xlsx`, `xml`; default `json`), `locale` (`de`,
`en`) and an optional `fields[]` list. Filters, field selection and the rate limit are the
same for every format. Each row is one value of one metric of one tile; the fields and their
canonical order are defined in `App\Services\Export\FieldWhitelist`. The schema version
(currently `1.0`) is sent in the `X-Export-Schema-Version` header and inside every file
except CSV.

## Formats

### JSON

An envelope with `schema_version`, `generated_at`, `tenant`, `locale`, `fields`, `filter`
and a `data` array of row objects keyed by field name. Rich text (tile description and hint)
keeps its HTML.

### CSV

UTF-8 with byte order mark, semicolon as delimiter, a header row with translated column
names. Lists are joined with `, `, rich text is converted to plain text. Opens directly in
Excel with German regional settings.

### Excel (`xlsx`)

A workbook with two sheets:

- **Daten / Data**: translated header row (bold, frozen, with an autofilter) and one row per
  value. Numbers (tile ID, year, value) are numeric cells, everything else is text. Lists
  are joined with `, `, rich text is plain text. Text is never stored as a formula, even if
  it starts with `=`. Cells longer than Excel's limit (32,767 UTF-16 code units) are cut, and characters that XML
  does not allow are dropped.
- **Info**: schema version, creation time (a date cell), dashboard, language and the
  applied filter.

The workbook is built in a temporary file and sent once it is complete. Memory use stays
constant, but the download only starts when the whole workbook is written, so it has to
finish within the PHP and proxy timeouts (60 seconds in the production image). A local
run wrote 100,000 rows in about 9 seconds. JSON, CSV and XML stream row by row.

### XML

A document in the namespace `urn:cividash:export:1.0`, described by
[`schemas/v1/export.xsd`](./schemas/v1/export.xsd):

```xml
<?xml version="1.0" encoding="UTF-8"?>
<export xmlns="urn:cividash:export:1.0" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" schema_version="1.0">
  <generated_at>2026-11-25T10:30:00+01:00</generated_at>
  <tenant id="1" slug="default">Default</tenant>
  <locale>de</locale>
  <filter>
    <year_from>2020</year_from>
  </filter>
  <fields>
    <field>tile.title</field>
    <field>category.labels</field>
    <field>value.year</field>
    <field>value.value</field>
    <field>metric.source</field>
  </fields>
  <rows>
    <row>
      <tile.title>Mobilität</tile.title>
      <category.labels>
        <item>Verkehr</item>
      </category.labels>
      <value.year>2021</value.year>
      <value.value>95.25</value.value>
      <metric.source xsi:nil="true"/>
    </row>
  </rows>
</export>
```

- Field elements have the same names as the JSON keys and appear in canonical order.
- A field that was not requested is absent; a requested field without a value is
  `xsi:nil="true"`.
- Lists use `<item>` children. Rich text keeps its HTML as escaped text.
- `tenant` and `filter` are only present when they apply.

Validate a file locally with `xmllint --noout --schema docs/export/schemas/v1/export.xsd export.xml`.

## Changing the format

New fields are added to `FieldWhitelist`, the translations in `resources/lang/*/export.php`
and the `Row` type in the XSD. A change that removes or renames a field, or changes a type,
is a breaking change: it needs a new schema version and follows the [API versioning](../api/versioning.md) rules.
