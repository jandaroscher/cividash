import DefaultTileCard from '../components/TileCard.vue';

// import.meta.glob is a Vite build-time glob: every overrides/*/TileCard.vue
// file gets bundled and its module is known at build time, no runtime
// compiler involved. `eager: true` resolves the modules immediately instead
// of returning dynamic import() functions, which keeps resolveTileComponent
// synchronous for use in a template's :is binding.
const tileOverrides = import.meta.glob('../overrides/*/TileCard.vue', { eager: true });

/**
 * Resolve the TileCard component for a theme slug.
 * Overrides belong to a theme (shareable across tenants), not a single
 * tenant - falls back to the default TileCard when no theme is given or
 * no matching override folder exists.
 *
 * @param {string|null|undefined} themeSlug
 * @returns {object}
 */
export function resolveTileComponent(themeSlug) {
    if (!themeSlug) {
        return DefaultTileCard;
    }

    const match = Object.entries(tileOverrides).find(([path]) =>
        path.includes(`/overrides/${themeSlug}/TileCard.vue`),
    );

    return match ? match[1].default : DefaultTileCard;
}
