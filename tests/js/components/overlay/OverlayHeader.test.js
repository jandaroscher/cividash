import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import OverlayHeader from '@/components/overlay/parts/Header.vue';
import { useOverlayStore } from '@/stores/overlay';

vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({ currentLocale: { value: 'de' } }),
}));

const tile = { id: 7, slug: { de: 'co2' }, title: { de: 'CO2' }, background_blocks: [{ type: 'slider', props: { items: [{ title: 'A' }] } }] };
const embedButton = 'button[aria-label="Kachel einbetten"]';
const exportButton = 'button[aria-label="Daten herunterladen"]';
const detailsButton = 'button[aria-label="Details anzeigen"]';

describe('OverlayHeader', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('swaps the embed button for a details button in the embed view', async () => {
        const wrapper = shallowMount(OverlayHeader, { props: { tile, embeddable: true } });

        await wrapper.find(embedButton).trigger('click');

        expect(useOverlayStore().view).toBe('embed');
        expect(wrapper.find(embedButton).exists()).toBe(false);
        expect(wrapper.find(exportButton).exists()).toBe(true);

        await wrapper.find(detailsButton).trigger('click');

        expect(useOverlayStore().view).toBe('info');
        expect(wrapper.find(embedButton).exists()).toBe(true);
    });

    it('offers no details button when the tile has no detail content', async () => {
        useOverlayStore().view = 'embed';
        const wrapper = shallowMount(OverlayHeader, { props: { tile: { ...tile, background_blocks: [] }, embeddable: true } });

        expect(wrapper.find(detailsButton).exists()).toBe(false);
    });

    it('offers a details button when the tile only has indicators', () => {
        useOverlayStore().view = 'embed';
        const wrapper = shallowMount(OverlayHeader, { props: { tile: { ...tile, background_blocks: [], metrics: [{ id: 1 }] }, embeddable: true } });

        expect(wrapper.find(detailsButton).exists()).toBe(true);
    });

    // The standalone tile page reuses this header but has no embed view.
    it('offers no embed button on the standalone tile page', () => {
        useOverlayStore().view = 'embed';
        const wrapper = shallowMount(OverlayHeader, { props: { tile } });

        expect(wrapper.find(embedButton).exists()).toBe(false);
        expect(wrapper.find(exportButton).exists()).toBe(true);
    });
});
