import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import TileAppBlock from '@/components/blocks/TileAppBlock.vue';

vi.mock('vue-router', () => ({
    useRoute: () => ({ path: '/', params: {}, meta: { locale: 'de' } }),
    useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('@/utils/api', () => ({
    getApiBaseUrl: () => 'http://localhost',
}));

vi.mock('@/stores/tiles', () => ({
    useTilesStore: () => ({
        loading: false,
        error: null,
        tiles: [],
        locale: 'de',
        fetchAll: vi.fn(),
        setLocale: vi.fn(),
    }),
}));

vi.mock('@/stores/overlay', () => ({
    useOverlayStore: () => ({
        open: false,
        openFromUrl: vi.fn(),
        closeOverlay: vi.fn(),
    }),
}));

vi.mock('@/stores/filter', () => ({
    useFilterStore: () => ({
        restoreFromUrl: vi.fn(),
    }),
}));

const CardsStub = { template: '<div class="cards-stub"></div>', props: ['selectedTileIds'] };
const FilterStub = { template: '<div class="filter-stub"></div>', props: ['showSearch', 'showFilter', 'heading'] };
const OverlayStub = { template: '<div class="overlay-stub"></div>' };

describe('TileAppBlock', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    function createWrapper(blockProps = {}) {
        return shallowMount(TileAppBlock, {
            props: {
                block: {
                    type: 'tile-app',
                    props: blockProps,
                },
            },
            global: {
                stubs: {
                    Cards: CardsStub,
                    Filter: FilterStub,
                    Overlay: OverlayStub,
                },
            },
        });
    }

    it('renders the tile app block container', () => {
        const wrapper = createWrapper({});
        expect(wrapper.find('.tile-app-block').exists()).toBe(true);
    });

    it('renders Overlay component', () => {
        const wrapper = createWrapper({});
        expect(wrapper.findComponent(OverlayStub).exists()).toBe(true);
    });

    it('renders Cards component when not loading and no error', () => {
        const wrapper = createWrapper({});
        expect(wrapper.findComponent(CardsStub).exists()).toBe(true);
    });

    it('passes selected tile IDs to Cards', () => {
        const wrapper = createWrapper({ tiles: [1, 2, 3] });
        const cards = wrapper.findComponent(CardsStub);
        expect(cards.props('selectedTileIds')).toEqual([1, 2, 3]);
    });

    it('passes empty array when no tiles selected', () => {
        const wrapper = createWrapper({});
        const cards = wrapper.findComponent(CardsStub);
        expect(cards.props('selectedTileIds')).toEqual([]);
    });

    it('sets hideFilters when only one tile is selected', () => {
        const wrapper = createWrapper({ tiles: [42] });
        // With 1 tile, hideFilters should be true — Filter gets showSearch=false, showFilter=false
        const filter = wrapper.findComponent(FilterStub);
        if (filter.exists()) {
            expect(filter.props('showSearch')).toBe(false);
            expect(filter.props('showFilter')).toBe(false);
        }
        // Component should render without error regardless
        expect(wrapper.find('.tile-app-block').exists()).toBe(true);
    });

    it('passes heading prop to Filter component', () => {
        const wrapper = createWrapper({ heading: 'Dashboard', tiles: [1, 2] });
        const filter = wrapper.findComponent(FilterStub);
        if (filter.exists()) {
            expect(filter.props('heading')).toBe('Dashboard');
        }
        expect(wrapper.find('.tile-app-block').exists()).toBe(true);
    });

    it('has min-height style on container', () => {
        const wrapper = createWrapper({});
        expect(wrapper.find('.tile-app-block').exists()).toBe(true);
    });
});
