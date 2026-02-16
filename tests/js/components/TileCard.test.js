import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import TileCard from '@/components/TileCard.vue';

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

vi.mock('@/composables/useHelpContext', () => ({
    useHelpContext: () => ({
        getTooltip: vi.fn(() => null),
        currentContext: { value: null },
        helpOverlayOpen: { value: false },
    }),
}));

// Mock the intersection observer directive
vi.mock('@vueuse/components', () => ({
    vIntersectionObserver: {
        mounted: () => {},
        unmounted: () => {},
    },
}));

describe('TileCard', () => {
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
            ...overrides,
        };
    }

    function createWrapper(tile = createTile(), props = {}) {
        return shallowMount(TileCard, {
            props: { tile, ...props },
            global: {
                stubs: {
                    IndicatorBig: true,
                    IndicatorSmall: true,
                    Tooltip: { template: '<div><slot /></div>', props: ['text'] },
                    VueSlider: true,
                    'dotlottie-player': true,
                },
                directives: {
                    'intersection-observer': {
                        mounted: () => {},
                    },
                },
            },
        });
    }

    it('renders tile title from translated title object', () => {
        const tile = createTile({ title: { de: 'Mein Titel', en: 'My Title' } });
        const wrapper = createWrapper(tile);

        expect(wrapper.text()).toContain('Mein Titel');
    });

    it('renders tile subheader from description', () => {
        const tile = createTile({ description: { de: 'Eine kurze Beschreibung.', en: 'A short description.' } });
        const wrapper = createWrapper(tile);

        // Subheader extracts the first sentence
        expect(wrapper.text()).toContain('Eine kurze Beschreibung');
    });

    it('applies background color from tile_color property', () => {
        const tile = createTile({ tile_color: '#FF5733' });
        const wrapper = createWrapper(tile);

        // The top section div should have the tile color as backgroundColor
        const colorDiv = wrapper.find('[class*="py-6"]');
        expect(colorDiv.exists()).toBe(true);
        // Happy-DOM keeps hex values as-is
        expect(colorDiv.attributes('style')).toContain('background-color: #FF5733');
    });

    it('applies background color from category color source when tile_color is not set', () => {
        const tile = createTile({
            tile_color: null,
            categories: [
                {
                    id: 1,
                    key: 'cat-1',
                    color: '#00AA00',
                    group: { key: 'dimensions', is_color_source: true },
                },
            ],
        });
        const wrapper = createWrapper(tile);

        const colorDiv = wrapper.find('[class*="py-6"]');
        expect(colorDiv.exists()).toBe(true);
        // Happy-DOM keeps hex values as-is
        expect(colorDiv.attributes('style')).toContain('background-color: #00AA00');
    });

    it('falls back to gray background when no tile color or category color', () => {
        const tile = createTile({ tile_color: null, categories: [] });
        const wrapper = createWrapper(tile);

        const colorDiv = wrapper.find('[class*="py-6"]');
        expect(colorDiv.exists()).toBe(true);
        expect(colorDiv.classes()).toContain('bg-gray-100');
    });

    it('renders an image when icon is a non-lottie URL', async () => {
        const tile = createTile({ icon: 'https://example.com/image.png' });
        const wrapper = createWrapper(tile);
        await wrapper.vm.$nextTick();

        const img = wrapper.find('img[loading="lazy"]');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.com/image.png');
    });

    it('shows info button when background_blocks are present', async () => {
        const tile = createTile({
            background_blocks: [{ type: 'text', content: 'More info' }],
        });
        const wrapper = createWrapper(tile);
        await wrapper.vm.$nextTick();

        const infoButton = wrapper.find('button[aria-label="Info"]');
        expect(infoButton.exists()).toBe(true);
    });

    it('hides info button when no background_blocks', async () => {
        const tile = createTile({ background_blocks: [] });
        const wrapper = createWrapper(tile);
        await wrapper.vm.$nextTick();

        const infoButton = wrapper.find('button[aria-label="Info"]');
        expect(infoButton.exists()).toBe(false);
    });

    it('is visible by default when no filter is active', () => {
        const tile = createTile();
        const wrapper = createWrapper(tile);

        // v-show should not hide the element
        expect(wrapper.find('.max-w-\\[363px\\]').element.style.display).not.toBe('none');
    });
});
