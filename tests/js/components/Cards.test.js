import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import Cards from '@/components/Cards.vue';
import { useTilesStore } from '@/stores/tiles';
import { useFilterStore } from '@/stores/filter';

// Mock composables that use vue-router internally
vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({
        currentLocale: { value: 'de' },
        setLocale: vi.fn(),
        getTranslatedSlug: vi.fn(),
        supportedLocales: ['de', 'en'],
        defaultLocale: 'de',
        getLocale: () => 'de',
    }),
}));

describe('Cards', () => {
    let tilesStore;
    let filterStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        tilesStore = useTilesStore();
        filterStore = useFilterStore();
    });

    function createWrapper() {
        return shallowMount(Cards, {
            global: {
                stubs: {
                    VueFlexWaterfall: {
                        template: '<div class="waterfall-stub"><slot /></div>',
                        props: ['col', 'colSpacing', 'breakAt', 'alignContent'],
                        methods: {
                            updateOrder: vi.fn(),
                        },
                    },
                    TileCard: {
                        template: '<div class="tile-card-stub" :data-tile-id="tile.id">{{ tile.title?.de || tile.title }}</div>',
                        props: ['tile'],
                    },
                },
            },
        });
    }

    function createTile(id, title, categories = []) {
        return {
            id,
            title: { de: title, en: `${title} EN` },
            description: { de: `Desc ${title}` },
            categories,
        };
    }

    it('renders TileCard components for each tile in the store', async () => {
        tilesStore.tiles = [
            createTile(1, 'Tile A'),
            createTile(2, 'Tile B'),
            createTile(3, 'Tile C'),
        ];

        const wrapper = createWrapper();
        // Wait for nextTick due to isReady flag set in onMounted
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const tileCards = wrapper.findAll('.tile-card-stub');
        expect(tileCards.length).toBe(3);
    });

    // TileCard sizes itself to
    // (100% - (cols - 1) * 40px) / cols using these custom properties (see
    // TileCard.vue's <style> block), so the grid always spans the full
    // container width instead of shrink-wrapping to less than that.
    it('exposes the active column counts as CSS custom properties for tile width sizing', async () => {
        tilesStore.tiles = [
            createTile(1, 'Tile A'),
            createTile(2, 'Tile B'),
            createTile(3, 'Tile C'),
        ];

        const wrapper = createWrapper();
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const style = wrapper.find('.waterfall-stub').attributes('style');
        expect(style).toContain('--waterfall-col-desktop: 3');
        expect(style).toContain('--waterfall-col-tablet: 2');
    });

    it('displays empty state message when no tiles match filter', async () => {
        tilesStore.tiles = [
            createTile(1, 'Tile A', [
                { id: 1, key: 'cat-a', group: { key: 'dimensions' } },
            ]),
        ];

        // Set a filter that no tile matches
        filterStore.level1Filter = 'dimensions';
        filterStore.level2Filter = { key: '999', title: 'Nonexistent' };

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Keine Tiles gefunden für Filter:');
        expect(wrapper.text()).toContain('Nonexistent');
    });

    it('displays search empty state message when search query yields no results', async () => {
        tilesStore.tiles = [
            createTile(1, 'Alpha'),
        ];

        filterStore.searchQuery = 'zzzznotfound';

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Keine Tiles gefunden für Suche:');
        expect(wrapper.text()).toContain('zzzznotfound');
    });

    it('filters tiles based on category filter from filter store', async () => {
        const catA = { id: 1, key: 'cat-a', group: { key: 'dimensions' } };
        const catB = { id: 2, key: 'cat-b', group: { key: 'dimensions' } };

        tilesStore.tiles = [
            createTile(1, 'Tile A', [catA]),
            createTile(2, 'Tile B', [catB]),
            createTile(3, 'Tile C', [catA]),
        ];

        filterStore.level1Filter = 'dimensions';
        filterStore.level2Filter = { key: '1', title: 'Category A' };

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Only tiles with catA (id: 1) should be visible
        const tileCards = wrapper.findAll('.tile-card-stub');
        expect(tileCards.length).toBe(2);
        expect(tileCards[0].text()).toContain('Tile A');
        expect(tileCards[1].text()).toContain('Tile C');
    });

    it('filters tiles based on search query', async () => {
        tilesStore.tiles = [
            createTile(1, 'Klimaschutz'),
            createTile(2, 'Bildung'),
            createTile(3, 'Klimawandel'),
        ];

        filterStore.searchQuery = 'Klima';

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const tileCards = wrapper.findAll('.tile-card-stub');
        expect(tileCards.length).toBe(2);
        expect(tileCards[0].text()).toContain('Klimaschutz');
        expect(tileCards[1].text()).toContain('Klimawandel');
    });

    it('renders all tiles when no filter is active', async () => {
        tilesStore.tiles = [
            createTile(1, 'Tile A'),
            createTile(2, 'Tile B'),
        ];

        // No filter active
        filterStore.level1Filter = null;
        filterStore.level2Filter = { key: null, title: null };
        filterStore.searchQuery = '';

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const tileCards = wrapper.findAll('.tile-card-stub');
        expect(tileCards.length).toBe(2);
    });

    // Search/filter must still apply when a page pins a fixed set
    // of tiles via selectedTileIds (e.g. TileAppBlock on a non-home page).
    describe('selectedTileIds (fixed tile set, e.g. TileAppBlock)', () => {
        function createWrapperWithSelectedIds(selectedTileIds) {
            return shallowMount(Cards, {
                props: { selectedTileIds },
                global: {
                    stubs: {
                        VueFlexWaterfall: {
                            template: '<div class="waterfall-stub"><slot /></div>',
                            props: ['col', 'colSpacing', 'breakAt', 'alignContent'],
                            methods: {
                                updateOrder: vi.fn(),
                            },
                        },
                        TileCard: {
                            template: '<div class="tile-card-stub" :data-tile-id="tile.id">{{ tile.title?.de || tile.title }}</div>',
                            props: ['tile'],
                        },
                    },
                },
            });
        }

        it('renders all selected tiles when no search query is set', async () => {
            tilesStore.tiles = [
                createTile(1, 'Klimaschutz'),
                createTile(2, 'Bildung'),
                createTile(3, 'Klimawandel'),
            ];

            const wrapper = createWrapperWithSelectedIds(['1', '2']);
            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();

            const tileCards = wrapper.findAll('.tile-card-stub');
            expect(tileCards.length).toBe(2);
        });

        it('still applies the search query on top of the selected tile IDs', async () => {
            tilesStore.tiles = [
                createTile(1, 'Klimaschutz'),
                createTile(2, 'Bildung'),
                createTile(3, 'Klimawandel'),
            ];

            filterStore.searchQuery = 'Klima';

            // All three tiles are pinned onto the page, but only the ones
            // matching the search term should remain visible.
            const wrapper = createWrapperWithSelectedIds(['1', '2', '3']);
            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();

            const tileCards = wrapper.findAll('.tile-card-stub');
            expect(tileCards.length).toBe(2);
            expect(tileCards[0].text()).toContain('Klimaschutz');
            expect(tileCards[1].text()).toContain('Klimawandel');
        });

        it('shows the empty state when the search query matches none of the selected tiles', async () => {
            tilesStore.tiles = [
                createTile(1, 'Klimaschutz'),
                createTile(2, 'Bildung'),
            ];

            filterStore.searchQuery = 'zzzznotfound';

            const wrapper = createWrapperWithSelectedIds(['1', '2']);
            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();

            expect(wrapper.findAll('.tile-card-stub').length).toBe(0);
            expect(wrapper.text()).toContain('Keine Tiles gefunden für Suche:');
        });

        it('does not show tiles outside the selected set even if they match the search', async () => {
            tilesStore.tiles = [
                createTile(1, 'Klimaschutz'),
                createTile(2, 'Klimawandel'),
            ];

            filterStore.searchQuery = 'Klima';

            // Only tile 1 is pinned to this page - tile 2 matches the search
            // but must stay excluded since it is not part of the fixed set.
            const wrapper = createWrapperWithSelectedIds(['1']);
            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();

            const tileCards = wrapper.findAll('.tile-card-stub');
            expect(tileCards.length).toBe(1);
            expect(tileCards[0].text()).toContain('Klimaschutz');
        });
    });
});
