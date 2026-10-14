function escapeAttribute(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

/**
 * Build the copy-paste embed codes for a tile: the loader variant
 * (public/embed.js, auto height) and a plain iframe fallback.
 */
export function buildEmbedSnippets({ origin, slug, locale, title }) {
    const lang = locale === 'en' ? 'en' : 'de';
    const prefix = lang === 'en' ? '/en' : '';
    const src = `${origin}${prefix}/embed/tiles/${encodeURIComponent(slug)}`;
    const label = escapeAttribute(title || slug);

    return {
        loader:
            `<cividash-tile slug="${escapeAttribute(slug)}" lang="${lang}" label="${label}"></cividash-tile>\n` +
            `<script src="${origin}/embed.js" defer></script>`,
        iframe:
            `<iframe src="${src}" title="${label}" loading="lazy" ` +
            'style="width:100%;height:500px;border:0"></iframe>',
    };
}
