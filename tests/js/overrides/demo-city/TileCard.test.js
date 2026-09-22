import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import DemoCityTileCard from '@/overrides/demo-city/TileCard.vue';
import { useTenantStore } from '@/stores/tenant';

// Mock composables that use vue-router internally (same as TileCard.test.js)
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

vi.mock('@/composables/useHelpContext', () => ({
    useHelpContext: () => ({
        getTooltip: vi.fn(() => null),
        currentContext: { value: null },
        helpOverlayOpen: { value: false },
    }),
}));

vi.mock('@vueuse/components', () => ({
    vIntersectionObserver: {
        mounted: () => {},
        unmounted: () => {},
    },
}));

describe('Demo City TileCard override', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    function createTile(overrides = {}) {
        return {
            id: 1,
            title: { de: 'Testitel', en: 'Test Title' },
            description: { de: 'Testbeschreibung', en: 'Test Description' },
            categories: [],
            metric_definitions: [],
            icon: null,
            tile_color: null,
            background_blocks: [],
            time_granularity: 'year',
            ...overrides,
        };
    }

    function createWrapper(tile = createTile(), props = {}) {
        return mount(DemoCityTileCard, {
            props: { tile, ...props },
            global: {
                stubs: {
                    IndicatorBig: true,
                    IndicatorSmall: true,
                    Tooltip: { template: '<div><slot /></div>', props: ['text'] },
                    VueSlider: true,
                    'dotlottie-wc': true,
                },
                directives: {
                    'intersection-observer': {
                        mounted: () => {},
                    },
                },
            },
        });
    }

    it('renders the blue title bar with the tile title instead of the default heading row', () => {
        const tile = createTile({ title: { de: 'Mein Titel', en: 'My Title' } });
        const wrapper = createWrapper(tile);

        const titleBar = wrapper.find('.demo-city-title-bar');
        expect(titleBar.exists()).toBe(true);
        expect(titleBar.text()).toContain('Mein Titel');
    });

    it('renders the tenant name as an inverted badge in the title bar', () => {
        const tenantStore = useTenantStore();
        tenantStore.name = 'Demo City';
        const wrapper = createWrapper();

        const badge = wrapper.find('.demo-city-badge');
        expect(badge.exists()).toBe(true);
        expect(badge.text()).toBe('Demo City');
    });

    it('does not render a badge when the tenant has no name', () => {
        const tenantStore = useTenantStore();
        tenantStore.name = null;
        const wrapper = createWrapper();

        expect(wrapper.find('.demo-city-badge').exists()).toBe(false);
    });

    it('places the trend label next to the value instead of near the footer', async () => {
        const tile = createTile({
            time_granularity: 'year',
            metric_definitions: [{
                metric_key: 'm1',
                label: { de: 'Metrik' },
                indicator_type: 'small',
                values: [
                    { period_key: '2020', value: 1 },
                    { period_key: '2021', value: 2 },
                ],
            }],
        });
        const wrapper = createWrapper(tile);
        await wrapper.vm.$nextTick();

        // The trend paragraph should sit before the year slider block, i.e.
        // in the value area, not after it near the footer.
        const html = wrapper.html();
        const trendIndex = html.indexOf('Veränderung zum Vorjahr');
        const sliderIndex = html.indexOf('vue-slider');
        expect(trendIndex).toBeGreaterThan(-1);
        expect(trendIndex).toBeLessThan(sliderIndex);

        // Regression (visual QA on PR #205): the base TileCard's own footer
        // trend must be suppressed, not just duplicated - the label may
        // appear only once per tile.
        const occurrences = html.split('Veränderung zum Vorjahr').length - 1;
        expect(occurrences).toBe(1);
    });

    // Regression (visual QA on PR #205): the blue title bar must sit flush
    // at the top of the card, not inside the base's own top-padded section.
    it('renders the title bar as the first element inside the card, with no padding above it', () => {
        const wrapper = createWrapper();

        const card = wrapper.find('.shadow-card');
        expect(card.element.firstElementChild).toBe(wrapper.find('.demo-city-title-bar').element);
    });

    it('still renders the subheader and indicators through the base TileCard', async () => {
        const tile = createTile({
            description: { de: 'Eine kurze Beschreibung.' },
            metric_definitions: [{
                metric_key: 'm1',
                label: { de: 'Metrik' },
                indicator_type: 'small',
                values: [{ period_key: '2021', value: 42 }],
            }],
        });
        const wrapper = createWrapper(tile);
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Eine kurze Beschreibung');
        expect(wrapper.findComponent({ name: 'IndicatorSmall' }).exists() || wrapper.find('indicator-small-stub').exists()).toBe(true);
    });
});
