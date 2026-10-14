import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createHead } from '@unhead/vue/client';
import TileEmbedPage from '@/components/pages/TileEmbedPage.vue';
import { useTilesStore } from '@/stores/tiles';

const mockRoute = { params: { slug: 'test-tile' }, meta: { locale: 'en', embed: true } };

vi.mock('vue-router', () => ({ useRoute: () => mockRoute }));

describe('TileEmbedPage', () => {
    let tilesStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        tilesStore = useTilesStore();
    });

    function mountWith(result) {
        tilesStore.fetchBySlug = vi.fn().mockResolvedValue(result);
        return shallowMount(TileEmbedPage, { global: { plugins: [createHead()] } });
    }

    it('loads the tile in the route locale and renders it in iframe mode', async () => {
        const wrapper = mountWith({ id: 1, slug: 'test-tile', title: 'Test' });
        await flushPromises();

        expect(tilesStore.fetchBySlug).toHaveBeenCalledWith('test-tile', 'en', true);
        const card = wrapper.findComponent({ name: 'TileCard' });
        expect(card.exists()).toBe(true);
        expect(card.props('isIframe')).toBe(true);
        expect(wrapper.find('a').attributes('href')).toBe('/en/tiles/test-tile');
    });

    it('shows a not-found message when the tile does not exist', async () => {
        const wrapper = mountWith(null);
        await flushPromises();

        expect(wrapper.text()).toContain('Tile not found.');
    });
});
