import { describe, it, expect, beforeEach } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useOverlayStore } from '@/stores/overlay';

const tiles = [{ id: 7, title: 'Radwege' }, { id: 12, title: 'Bäume' }];

describe('overlay store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        window.history.replaceState({}, '', '/?category=3');
    });

    it('puts the open tile into the URL so the overlay can be shared', () => {
        useOverlayStore().openOverlay(tiles[0]);

        expect(new URL(window.location.href).searchParams.get('tile')).toBe('7');
        expect(window.location.search).toContain('category=3');
    });

    it('removes the tile from the URL and resets the view when closed', () => {
        const overlay = useOverlayStore();
        overlay.openOverlay(tiles[0], null, 'embed');

        overlay.closeOverlay();

        expect(overlay.open).toBe(false);
        expect(overlay.view).toBe('info');
        expect(window.location.search).toBe('?category=3');
    });

    it('opens the tile named in a shared link', () => {
        window.history.replaceState({}, '', '/?tile=12');
        const overlay = useOverlayStore();

        expect(overlay.openFromUrl(tiles)).toBe(true);
        expect(overlay.open).toBe(true);
        expect(overlay.tile.title).toBe('Bäume');
    });

    it('stays closed when a shared link names an unknown tile', () => {
        window.history.replaceState({}, '', '/?tile=99');
        const overlay = useOverlayStore();

        expect(overlay.openFromUrl(tiles)).toBe(false);
        expect(overlay.open).toBe(false);
    });
});
