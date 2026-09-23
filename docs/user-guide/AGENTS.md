# User guide: working rules for contributors and agents

This directory holds the CMS user guide as Markdown. These files are the single source
of truth for the guide.

## Languages

- Maintain the guide in German (`de/`) and English (`en/`).
- Update both language versions with every content change.

## Product name

- Call the product **CiviDash**. Do not use internal project codes in user-facing text.
- Where it reads better, use neutral terms such as "der Adminbereich" / "the admin area"
  or "das Dashboard" / "the dashboard".

## Tone (German version)

- No direct address: write "Auf … klicken", not "Klicken Sie auf …".
- No gendered forms. Keep role names neutral: "Redaktion", "Admin", "Anwender".
- Use imperative or infinitive constructions for instructions: "Neuen Eintrag anlegen",
  "Speichern bestätigen".
- Keep sentences short (one to two lines). Describe the action, skip explanations that
  add nothing.
- Explain technical terms on first use, e.g. "Kachel (Tile), ein Inhaltselement auf der
  Dashboard-Übersicht".
- Stay factual and professional. No marketing language, no filler.

Apply the same rules to the English version: imperative instructions, neutral roles,
short sentences.

## Structure

Organize chapters by task (Diátaxis), not by menu. Each chapter has one file per language:

| # | German | English | Content |
|---|--------|---------|---------|
| 0 | `de/00-einstieg.md` | `en/00-introduction.md` | Audience, contents, conventions, last-updated date |
| 1 | `de/01-erste-schritte.md` | `en/01-getting-started.md` | Login, admin overview, navigation, profile, language |
| 2 | `de/02-seiten-verwalten.md` | `en/02-managing-pages.md` | Pages, content blocks, metadata, translations |
| 3 | `de/03-kacheln-verwalten.md` | `en/03-managing-tiles.md` | Tiles, background pages, metrics, order and status |
| 4 | `de/04-kategorien.md` | `en/04-categories.md` | Category groups and categories |
| 5 | `de/05-bilder-und-dateien.md` | `en/05-images-and-files.md` | Uploads, formats, alt texts, Lottie and SVG |
| 6 | `de/06-einstellungen.md` | `en/06-settings.md` | Admin only: site settings, themes, dashboard configuration, API keys, data import, users |
| 7 | `de/07-rollen-und-berechtigungen.md` | `en/07-roles-and-permissions.md` | Roles and permission matrix |
| 8 | `de/08-glossar.md` | `en/08-glossary.md` | Glossary |

- Go from simple (login) to complex (settings).
- Mark admin-only chapters as such ("(nur Admin)" / "(admin only)").
- Keep reference material (glossary, permission matrix) at the end.
- Use at most three heading levels below the chapter title.

## Keep the last-updated date current

`de/00-einstieg.md` and `en/00-introduction.md` carry a date under **Stand:** /
**Last updated:**. Set it to today's date in both files whenever any file in
`docs/user-guide/` changes.

## Keep the guide in sync with the CMS

When you change the CMS backend, check whether the guide needs an update. This applies
in particular to:

- `app/Filament/`: Resources, Pages, Fabricator blocks
- `app/Filament/Pages/`: settings pages (branding, navigation, footer, API keys)
- `app/Filament/Fabricator/PageBlocks/`: content block types
- `app/Models/`: fields, validation and relations visible in the admin
- `app/Policies/`: permission changes
- `resources/lang/`: changed labels or menu names
- `config/`: configuration that changes admin behavior

Then:

1. Update the affected Markdown files in both languages.
2. Retake affected screenshots in `assets/screenshots/de/` and `assets/screenshots/en/`,
   using the demo tenants and example.org domains.
