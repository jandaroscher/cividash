import DefaultTileCard from '../components/TileCard.vue';

// import.meta.glob is a Vite build-time glob: every overrides/*/TileCard.vue
// file gets bundled and its module is known at build time, no runtime
// compiler involved. `eager: true` resolves the modules immediately instead
// of returning dynamic import() functions, which keeps resolveTileComponent
// synchronous for use in a template's :is binding.
const tileOverrides = import.meta.glob('../overrides/*/TileCard.vue', { eager: true });

/**
 * Resolve the TileCard component for a tenant slug.
 * Falls back to the default TileCard when no override folder exists.
 *
 * @param {string} slug
 * @returns {object}
 */
export function resolveTileComponent(slug) {
    const match = Object.entries(tileOverrides).find(([path]) =>
        path.includes(`/overrides/${slug}/TileCard.vue`),
    );

    return match ? match[1].default : DefaultTileCard;
}
