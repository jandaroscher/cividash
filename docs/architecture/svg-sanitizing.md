# SVG sanitizing

## Why

Filament `FileUpload` fields that accept `image/*` or `image/svg+xml` (category icons,
tile icons, branding logo/favicon) store uploaded files on the `public` disk. SVG is
executable markup: `<script>`, `on*` event handlers, and `javascript:`/`data:` hrefs
run when the file is opened directly (e.g. `/storage/tiles/icon.svg`), not only when
embedded via `<img>`. Unsanitized, any authenticated editor could plant a stored-XSS
payload that fires against admins opening the raw asset URL.

## How it works

Every SVG written through a Filament `FileUpload` is sanitized on disk immediately
after storage, via a single global hook in `App\Providers\AppServiceProvider::boot()`:

```php
FileUpload::configureUsing(function (FileUpload $upload) {
    $upload->saveUploadedFileUsing(/* store, then sanitize if *.svg */);
}, isImportant: true);
```

`isImportant: true` makes this run after Filament's own `setUp()` (which installs the
default `saveUploadedFileUsing`), so it applies to every `FileUpload` instance
app-wide without touching each Resource/Page individually. The theme bundle importer
(`App\Services\ThemeBundleImporter`, ZIP-based, not a Filament `FileUpload`) sanitizes
SVG assets separately at extraction time.

The sanitizer itself is `App\Support\SvgSanitizer`. It strips `<script>`,
`<foreignObject>`, `<iframe>`, `<embed>`, `<object>`, all `on*` attributes, and
`javascript:`/`data:`/external `href`/`xlink:href` values, and drops `DOCTYPE`/`ENTITY`
declarations before parsing to prevent XXE and entity-expansion attacks.

**Not used:** `enshrined/svg-sanitize`, the common Composer package for this, is
licensed `GPL-2.0-or-later` — incompatible with this project's `EUPL-1.2` license.
`App\Support\SvgSanitizer` is a small deny-list sanitizer instead.

**Known limitation:** deny-list, not a full spec-compliant allow-list sanitizer.
Sufficient for the current threat model (trusted CMS editors, not public self-service
uploads). Revisit with a maintained allow-list library if that changes.
