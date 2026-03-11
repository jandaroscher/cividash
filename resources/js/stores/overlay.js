import { defineStore } from 'pinia';

/**
 * Create a URL slug for a tile by using its `id`.
 * @param {{id?: number|string}|null|undefined} tile - Tile object; its `id` will be used as the slug. May be null/undefined.
 * @returns {string|null} The tile's `id` converted to a string if present, `null` otherwise.
 */
function getTileSlug(tile) {
    if (!tile) return null;
    // Use tile ID as slug (most reliable identifier)
    return tile.id?.toString() || null;
}

/**
 * Set or remove the "tile" query parameter in the current page URL without creating a new history entry.
 *
 * Does nothing when executed outside a browser environment (no global window).
 * @param {string|null|undefined} tileSlug - The slug to set for the `tile` query parameter; pass a falsy value to remove the parameter.
 */
function updateUrlWithTile(tileSlug) {
    if (typeof window === 'undefined') return;
    
    const url = new URL(window.location.href);
    if (tileSlug) {
        url.searchParams.set('tile', tileSlug);
    } else {
        url.searchParams.delete('tile');
    }
    
    // Use replaceState to avoid adding new history entry
    window.history.replaceState({}, '', url.toString());
}

/**
 * Retrieve the tile slug from the current page URL's "tile" query parameter.
 * @returns {string|null} The value of the "tile" query parameter, or `null` if the parameter is absent or if not running in a browser environment.
 */
function getTileSlugFromUrl() {
    if (typeof window === 'undefined') return null;
    const params = new URLSearchParams(window.location.search);
    return params.get('tile');
}

export const useOverlayStore = defineStore('overlay', {
    state: () => ({
        open: false,
        tile: null,
        data: null,
    }),
    actions: {
        openOverlay(tile, data = null) {
            this.tile = tile;
            this.data = data;
            this.open = true;
            // Update URL with tile parameter
            const tileSlug = getTileSlug(tile);
            if (tileSlug) {
                updateUrlWithTile(tileSlug);
            }
        },
        closeOverlay() {
            this.open = false;
            this.tile = null;
            this.data = null;
            // Remove tile parameter from URL
            updateUrlWithTile(null);
        },
        toggleOverlay(tile, data = null) {
            if (this.open) {
                this.closeOverlay();
            } else {
                this.openOverlay(tile, data);
            }
        },
        /**
         * Open overlay from URL parameter (used for deep-linking)
         */
        openFromUrl(tiles) {
            const tileSlug = getTileSlugFromUrl();
            if (!tileSlug || !tiles || tiles.length === 0) return false;
            
            // Find tile by ID (slug is tile ID)
            const tileId = parseInt(tileSlug, 10);
            const tile = tiles.find((t) => t.id === tileId);
            
            if (tile) {
                this.openOverlay(tile);
                return true;
            }
            
            return false;
        },
    },
});
