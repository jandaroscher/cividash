# Tile Background Blocks Architecture

## Übersicht

Tile-Background-Inhalte werden als **Embedded Blocks direkt im Tile Model** gespeichert. Dies bedeutet, dass die Background-Inhalte als JSON-Array in der `tiles` Tabelle gespeichert werden, anstatt eine separate `pages` oder `background_pages` Tabelle zu verwenden.

## Architektur-Entscheidung

**Gewählter Ansatz:** Embedded Blocks im Tile Model

### Vorteile

- **Einfachheit**: Keine separate Tabelle oder Beziehung nötig
- **Performance**: Ein Query, keine Joins, einfacheres Caching
- **Admin-UX**: Alles in einem Formular (TileResource)
- **Domain-Kopplung**: Background ist Teil des Tiles, gemeinsamer Lebenszyklus
- **Multi-Tenancy**: Einfaches Scoping über `tile.tenant_id` (falls vorhanden)

### Nachteile (akzeptiert)

- **Kein Routing/Slug**: Backgrounds haben keine eigene URL (nicht benötigt)
- **Keine Preview/Draft-States**: Keine separaten Draft/Live-States (nicht benötigt für Tile Backgrounds)

## Datenmodell

### Datenbank-Schema

```sql
ALTER TABLE tiles ADD COLUMN background_blocks JSON NULL;
```

### Model-Struktur

```php
class Tile extends Model
{
    protected $fillable = [..., 'background_blocks'];
    
    protected $casts = [
        'background_blocks' => 'array',
    ];
}
```

### Block-Struktur

Die `background_blocks` Spalte speichert ein Array von Block-Objekten. Es gibt zwei Darstellungen:

#### Datenbank-Format (Input)

Wie Blocks in der Datenbank gespeichert werden (Format von Filament Builder):

```json
[
  {
    "type": "hero",
    "data": {
      "title": "Hero Title",
      "subtitle": "Hero Subtitle"
    }
  },
  {
    "type": "text-image",
    "data": {
      "text": "Some text",
      "image": "image.jpg"
    }
  }
]
```

#### API-Format (Output)

Wie Blocks über die REST API zurückgegeben werden (nach `BlockTransformer`):

```json
[
  {
    "type": "hero",
    "props": {
      "title": "Hero Title",
      "subtitle": "Hero Subtitle"
    }
  },
  {
    "type": "text-image",
    "props": {
      "text": "Some text",
      "image": "image.jpg"
    }
  }
]
```

**Hinweis für Frontend-Entwicklung:** Vue-Komponenten konsumieren das API-Format. Verwende daher `block.props` (nicht `block.data`) beim Erstellen von UI-Komponenten. Der `BlockTransformer` konvertiert automatisch das Datenbank-Format in das API-Format.

## Multi-Tenancy

- `tenant_id` wird über das Tile Model gehandhabt (falls vorhanden)
- Background-Blocks sind automatisch tenant-scoped, da sie Teil des Tiles sind
- Keine zusätzlichen Tenant-Checks nötig

## Migration Path

- Bestehende `BackgroundPage` Daten können später migriert werden (separate Task)
- Neue Tiles verwenden direkt `background_blocks`
- `BackgroundPage` Model kann später deprecated werden

## Verwandte Dokumentation




