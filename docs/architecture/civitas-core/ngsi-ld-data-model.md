# CIVITAS/CORE Integration — NGSI-LD Data Model

This document is the **formal data model** for the entities the dashboard ingests
from a CIVITAS/CORE context broker (Stellio). It defines the
`NachhaltigkeitsIndikator` entity type, its attributes, the time-series encoding
and the mapping to the dashboard's internal models. It complements the
[architecture overview](architecture.md) and the [data flow](data-flow.md).

## Entity type

| | |
|---|---|
| **Entity type** | `NachhaltigkeitsIndikator` |
| **URN scheme** | `urn:ngsi-ld:NachhaltigkeitsIndikator:<slug>` |
| **Encoding** | ETSI NGSI-LD, typed attribute nodes (`Property`, `LanguageProperty`, `Relationship`) |
| **`@context`** | NGSI-LD core context **v1.8** (`https://uri.etsi.org/ngsi-ld/v1/ngsi-ld-core-context-v1.8.jsonld`) |

The `<slug>` segment of the URN becomes the metric key on import (the last
colon-separated URN segment, slugified).

## Attributes

NGSI-LD encodes each attribute as a typed node: a `Property` carries `{type,value}`,
a `LanguageProperty` carries `{type,languageMap}`, and a `Relationship` carries
`{type,object}`. The mapper unwraps these nodes but also tolerates already-flattened
(raw scalar) values for resilience across broker variants.

| Attribute | NGSI-LD type | Data type | Required | Default | Description |
|-----------|--------------|-----------|----------|---------|-------------|
| `id` | — | URN string | **required** | — | Entity identity, `urn:ngsi-ld:NachhaltigkeitsIndikator:<slug>`. |
| `type` | — | string | **required** | — | Fixed value `NachhaltigkeitsIndikator`. |
| `name` | `LanguageProperty` | `languageMap` `{de,en}` | **required** | — | Display title of the indicator. |
| `unit` | `Property` | string | **required** | — | Unit of measure, e.g. `MW`, `%`, `t CO2/a`. |
| `description` | `LanguageProperty` | `languageMap` `{de,en}` | optional | — | Longer description of the indicator. |
| `timeGranularity` | `Property` | string enum | optional | `year` | Time granularity of the series. One of `year`, `quarter`, `month`, `week`, `day`; unrecognised values fall back to `year`. |
| `dataPoints` | `Property` | array (see below) | optional | `[]` | Time series of measured values. |
| `category` | `Relationship` | URN string | optional | — | Link to a category, `urn:ngsi-ld:Category:<key>`. |

## `dataPoints` time series

`dataPoints` is a `Property` whose `value` is an **array** of data points. Each
entry is an object of the form `{ year | period, value }`:

- `value` — the numeric measurement (stored as float; `null` is allowed).
- `period` — a canonical **period_key** string whose format depends on
  `timeGranularity` (see below).
- `year` — a legacy numeric shorthand for yearly series; mapped to a 4-digit
  yearly period_key.

Entries without a usable period/year are skipped on import.

### period_key granularities

The `period` string is an ISO-style period_key matching the entity's
`timeGranularity`:

| Granularity | period_key format | Example |
|-------------|-------------------|---------|
| `year` | `YYYY` | `2023` |
| `quarter` | `YYYY-Qn` | `2023-Q2` |
| `month` | `YYYY-MM` | `2023-07` |
| `week` | `YYYY-Wnn` | `2023-W31` |
| `day` | `YYYY-MM-DD` | `2023-07-15` |

### Caveat: the attribute is `dataPoints`, NOT `values`

> The time-series attribute **must** be named `dataPoints`. Under the NGSI-LD core
> context, the term `values` expands to the reserved term `hasValues`, which
> Stellio — and therefore production CORE — **rejects with HTTP 400**. Using
> `dataPoints` avoids that collision. (See `docker/civitas/v1.6.2/README.md` and
>.)

## `category` relationship

`category` is a `Relationship` whose `object` references a category entity:
`urn:ngsi-ld:Category:<key>`. On import the last URN segment is slugified into the
category `key` (e.g. `urn:ngsi-ld:Category:energie` → key `energie`), while the full
URN is preserved as the category's `external_id`.

## Complete example (verbatim)

The following is the complete seed payload from
`docker/civitas/v1.6.2/seed-ngsi-ld.json` — three `NachhaltigkeitsIndikator`
entities exactly as the `NgsiLdDataMapper` expects them:

```json
[
  {
    "id": "urn:ngsi-ld:NachhaltigkeitsIndikator:erneuerbare-energien",
    "type": "NachhaltigkeitsIndikator",
    "name": {
      "type": "LanguageProperty",
      "languageMap": {
        "de": "Erneuerbare Energien",
        "en": "Renewable Energy"
      }
    },
    "description": {
      "type": "LanguageProperty",
      "languageMap": {
        "de": "Ausbau der Erneuerbaren Energien im Stadtgebiet",
        "en": "Expansion of renewable energy in the urban area"
      }
    },
    "unit": {
      "type": "Property",
      "value": "MW"
    },
    "category": {
      "type": "Relationship",
      "object": "urn:ngsi-ld:Category:energie"
    },
    "dataPoints": {
      "type": "Property",
      "value": [
        { "year": 2015, "value": 26.1 },
        { "year": 2018, "value": 35.4 },
        { "year": 2020, "value": 44.2 },
        { "year": 2022, "value": 55.8 },
        { "year": 2023, "value": 64.0 }
      ]
    },
    "@context": [
      "https://uri.etsi.org/ngsi-ld/v1/ngsi-ld-core-context-v1.8.jsonld"
    ]
  },
  {
    "id": "urn:ngsi-ld:NachhaltigkeitsIndikator:radverkehr",
    "type": "NachhaltigkeitsIndikator",
    "name": {
      "type": "LanguageProperty",
      "languageMap": {
        "de": "Radverkehr",
        "en": "Cycling"
      }
    },
    "description": {
      "type": "LanguageProperty",
      "languageMap": {
        "de": "Steigerung des Radverkehrsanteils am Gesamtverkehr",
        "en": "Increase of cycling share in total traffic"
      }
    },
    "unit": {
      "type": "Property",
      "value": "%"
    },
    "category": {
      "type": "Relationship",
      "object": "urn:ngsi-ld:Category:mobilitaet"
    },
    "dataPoints": {
      "type": "Property",
      "value": [
        { "year": 2015, "value": 18.5 },
        { "year": 2018, "value": 20.1 },
        { "year": 2020, "value": 21.8 },
        { "year": 2022, "value": 23.4 }
      ]
    },
    "@context": [
      "https://uri.etsi.org/ngsi-ld/v1/ngsi-ld-core-context-v1.8.jsonld"
    ]
  },
  {
    "id": "urn:ngsi-ld:NachhaltigkeitsIndikator:co2-emissionen",
    "type": "NachhaltigkeitsIndikator",
    "name": {
      "type": "LanguageProperty",
      "languageMap": {
        "de": "CO2-Emissionen",
        "en": "CO2 Emissions"
      }
    },
    "description": {
      "type": "LanguageProperty",
      "languageMap": {
        "de": "Reduktion der städtischen CO2-Emissionen",
        "en": "Reduction of urban CO2 emissions"
      }
    },
    "unit": {
      "type": "Property",
      "value": "t CO2/a"
    },
    "category": {
      "type": "Relationship",
      "object": "urn:ngsi-ld:Category:klima"
    },
    "dataPoints": {
      "type": "Property",
      "value": [
        { "year": 2015, "value": 8.2 },
        { "year": 2018, "value": 7.5 },
        { "year": 2020, "value": 6.8 },
        { "year": 2022, "value": 6.1 }
      ]
    },
    "@context": [
      "https://uri.etsi.org/ngsi-ld/v1/ngsi-ld-core-context-v1.8.jsonld"
    ]
  }
]
```

## Mapping NGSI-LD → dashboard models

One `NachhaltigkeitsIndikator` entity maps to a tile with its metric definition,
time series and category assignment (`NgsiLdDataMapper`):

| Dashboard model | Field | Source (NGSI-LD) |
|-----------------|-------|------------------|
| **Tile** | `title` | `name` languageMap |
| | `description` | `description` languageMap |
| | `time_granularity` | `timeGranularity` (default `year`) |
| | `is_public` | constant `true` |
| | `external_source` | constant `civitas-core` |
| | `external_id` | `id` (full URN) |
| **MetricDefinition** | `metric_key` | slugified last segment of `id` |
| | `label` | `name` languageMap |
| | `unit` | `unit` value → `{de,en}` (same string) |
| | `indicator_type` | constant `number` |
| | `external_id` | `id` (full URN) |
| **TimePeriod** | `period_key` | each `dataPoints[].period` (or `year`) |
| | `granularity` | derived from the tile's `timeGranularity` |
| | `label` | generated from `period_key` + granularity |
| **MetricValue** | `value` | each `dataPoints[].value` (float, nullable) |
| **Category** | `key` / `slug` | slugified last segment of `category` URN |
| | `external_id` | `category` URN (full) |
| | `is_active` | constant `true` |

The tile, definition and category each store `external_source = civitas-core` and
`external_id` for provenance and idempotent re-sync. See [data flow](data-flow.md)
for the `source_hash` mechanism.
