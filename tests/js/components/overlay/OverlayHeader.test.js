import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import OverlayHeader from '@/components/overlay/parts/Header.vue';
import { useOverlayStore } from '@/stores/overlay';

vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({ currentLocale: { value: 'de' } }),
}));

const tile = { id: 7, slug: { de: 'co2' }, title: { de: 'CO2' }, background_blocks: [] };
const embedButton = 'button[aria-label="Kachel einbetten"]';
const exportButton = 'button[aria-label="Daten herunterladen"]';

describe('OverlayHeader', () => {
    beforeEach(() => setActivePinia(createPinia()));

    it('switches the side overlay to the embed view and hides both buttons there', async () => {
        const wrapper = shallowMount(OverlayHeader, { props: { tile, embeddable: true } });

        await wrapper.find(embedButton).trigger('click');

        expect(useOverlayStore().view).toBe('embed');
        expect(wrapper.find(embedButton).exists()).toBe(false);
        expect(wrapper.find(exportButton).exists()).toBe(false);
    });

    // The standalone tile page reuses this header but has no embed view.
    it('offers no embed button on the standalone tile page', () => {
        useOverlayStore().view = 'embed';
        const wrapper = shallowMount(OverlayHeader, { props: { tile } });

        expect(wrapper.find(embedButton).exists()).toBe(false);
        expect(wrapper.find(exportButton).exists()).toBe(true);
    });
});
