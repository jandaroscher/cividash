import { defineStore } from 'pinia';

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
            // Lock body scroll when overlay is open
            document.body.style.overflow = 'hidden';
        },
        closeOverlay() {
            this.open = false;
            this.tile = null;
            this.data = null;
            // Restore body scroll
            document.body.style.overflow = '';
        },
        toggleOverlay(tile, data = null) {
            if (this.open) {
                this.closeOverlay();
            } else {
                this.openOverlay(tile, data);
            }
        },
    },
});

