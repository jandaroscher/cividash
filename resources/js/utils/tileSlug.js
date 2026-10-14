/**
 * Slug for tile URLs (export, embed) in the given locale. Falls back to the
 * tile ID, which those endpoints accept as well.
 */
export function resolveTileSlug(tile, locale) {
    const slug = tile?.slug;
    if (slug) {
        if (typeof slug === 'string') return slug;
        const resolved = slug[locale] || slug.de || slug.en;
        if (resolved) return resolved;
    }
    return tile?.id ? String(tile.id) : null;
}
