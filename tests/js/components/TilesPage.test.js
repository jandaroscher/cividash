import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import TilesPage from '@/components/pages/TilesPage.vue';
import { useTilesStore } from '@/stores/tiles';
import { useOverlayStore } from '@/stores/overlay';
import { useFilterStore } from '@/stores/filter';

const mockRoute = {
    path: '/dashboard',
    params: {},
    meta: { locale: 'de' },
    query: {},
};

vi.mock('vue-router', () => ({
    useRoute: () => mockRoute,
    useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
    RouterLink: { template: '<a><slot /></a>', props: ['to'] },
}));

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

vi.mock('@/utils/api', () => ({ getApiBaseUrl: () => 'http://localhost' }));

describe('TilesPage', () => {
    let tilesStore;
    let overlayStore;
    let filterStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        tilesStore = useTilesStore();
        overlayStore = useOverlayStore();
        filterStore = useFilterStore();

        // Stub fetch so fetchAll doesn't make real requests
        globalThis.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [] }),
        });

        mockRoute.meta = { locale: 'de' };
        mockRoute.path = '/dashboard';
    });

    afterEach(() => {
        vi.restoreAllMocks();
        // Restore original fetch to prevent leaking mocked fetch across suites
        globalThis.fetch = undefined;
    });

    function createWrapper(props = {}) {
        return shallowMount(TilesPage, {
            props,
            global: {
                stubs: {
                    Cards: { template: '<div class="cards-stub" />' },
                    Filter: {
                        template: '<div class="filter-stub" />',
                        props: ['showSearch', 'showFilter'],
                    },
                    Overlay: { template: '<div class="overlay-stub" />' },
                },
            },
        });
    }

    it('renders Cards component when not loading and no error', async () => {
        // Mock fetch to return tiles so fetchAll completes with data
        globalThis.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [{ id: 1, title: 'Tile 1' }] }),
        });

        const wrapper = createWrapper();
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.cards-stub').exists()).toBe(true);
    });

    it('shows loading state when tiles are loading', async () => {
        // Make fetch never resolve so loading stays true
        globalThis.fetch = vi.fn().mockReturnValue(new Promise(() => {}));

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Lade Tiles');
    });

    it('shows error state when tiles fail to load', async () => {
        globalThis.fetch = vi.fn().mockRejectedValue(new Error('Failed to fetch'));

        const wrapper = createWrapper();
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Failed to fetch');
    });

    it('renders Filter when showSearch and showFilter are true', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [] }),
        });

        const wrapper = createWrapper({ showSearch: true, showFilter: true });
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.filter-stub').exists()).toBe(true);
    });

    it('does not render Filter when both showSearch and showFilter are false', async () => {
        globalThis.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: [] }),
        });

        const wrapper = createWrapper({ showSearch: false, showFilter: false });
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.filter-stub').exists()).toBe(false);
    });

    it('renders Overlay component', () => {
        const wrapper = createWrapper();
        expect(wrapper.find('.overlay-stub').exists()).toBe(true);
    });

    it('shows English loading text when locale is en', async () => {
        mockRoute.meta = { locale: 'en' };
        // Set loading before mount and prevent fetch from resolving
        globalThis.fetch = vi.fn().mockReturnValue(new Promise(() => {}));
        tilesStore.loading = true;

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Loading tiles');
    });

    it('calls fetchAll on mount', () => {
        const fetchAllSpy = vi.spyOn(tilesStore, 'fetchAll');
        createWrapper();
        expect(fetchAllSpy).toHaveBeenCalledWith('de');
    });

    it('sets locale in tiles store', () => {
        const setLocaleSpy = vi.spyOn(tilesStore, 'setLocale');
        createWrapper();
        expect(setLocaleSpy).toHaveBeenCalledWith('de');
    });
});
