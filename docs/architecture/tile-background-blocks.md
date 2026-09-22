# Tile Background Blocks Architecture

## Overview

Tile background content is stored as **embedded blocks directly in the Tile model**. This means the background content is stored as a JSON column (locale-keyed, each locale holding an array of blocks) in the `tiles` table, instead of using a separate `pages` or `background_pages` table.

## Architecture decision

**Chosen approach:** embedded blocks in the Tile model

### Advantages

- **Simplicity**: no separate table or relation needed
- **Performance**: one query, no joins, simpler caching
- **Admin UX**: everything in one form (TileResource)
- **Domain coupling**: the background is part of the tile, shared lifecycle
- **Multi-tenancy**: simple scoping via `tile.tenant_id` (if present)

### Drawbacks (accepted)

- **No routing/slug**: backgrounds have no own URL (not needed)
- **No preview/draft states**: no separate draft/live states (not needed for tile backgrounds)

## Data model

### Database schema

```sql
ALTER TABLE tiles ADD COLUMN background_blocks JSON NULL;
```

### Model structure

```php
class Tile extends Model
{
    use HasTranslations;

    protected $fillable = [..., 'background_blocks'];

    public array $translatable = [..., 'background_blocks'];

    protected $casts = [
        'background_blocks' => 'array',
    ];
}
```

### Block structure

`background_blocks` is a translatable attribute (Spatie `HasTranslations`), so the column itself stores a locale-keyed object, e.g. `{"de": [...], "en": [...]}`, where each locale's value is the array of block objects described below. Access a single locale's blocks with `$tile->getTranslation('background_blocks', 'de')`. There are two representations for that per-locale array:

#### Database format (input)

How blocks are stored per locale in the database (format produced by the Filament Builder):

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

#### API format (output)

How blocks are returned via the REST API (after the `BlockTransformer`):

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

**Note for frontend development:** Vue components consume the API format. Use `block.props` (not `block.data`) when building UI components. The `BlockTransformer` automatically converts the database format into the API format.

## Multi-tenancy

- `tenant_id` is handled via the Tile model (if present)
- Background blocks are automatically tenant-scoped, since they are part of the tile
- No additional tenant checks needed

## Legacy data

The legacy `BackgroundPage` model remains for existing data; new tiles use `background_blocks`.

## Related documentation

