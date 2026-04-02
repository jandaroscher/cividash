import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import TileDetailPage from '@/components/pages/TileDetailPage.vue';
import { useTilesStore } from '@/stores/tiles';

const mockRoute = {
    path: '/tiles/test-tile',
    params: { slug: 'test-tile' },
    meta: { locale: 'de' },
    query: {},
};

const mockRouter = { push: vi.fn() };

vi.mock('vue-router', () => ({
    useRoute: () => mockRoute,
    useRouter: () => mockRouter,
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

vi.mock('@/composables/useMeta', () => ({
    setMetaTags: vi.fn(),
    useMeta: () => ({ setMetaTags: vi.fn() }),
}));

vi.mock('@/utils/api', () => ({ getApiBaseUrl: () => 'http://localhost' }));

describe('TileDetailPage', () => {
    let tilesStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        tilesStore = useTilesStore();
        mockRoute.params = { slug: 'test-tile' };
        mockRoute.meta = { locale: 'de' };
        mockRoute.path = '/tiles/test-tile';
        mockRouter.push.mockClear();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    function createWrapper(fetchResult = null, storeOverrides = {}) {
        tilesStore.fetchBySlug = vi.fn().mockResolvedValue(fetchResult);
        Object.assign(tilesStore, storeOverrides);

        return shallowMount(TileDetailPage, {
            global: {
                stubs: {
                    OverlayHeader: {
                        template: '<div class="overlay-header-stub" />',
                        props: ['tile'],
                    },
                    OverlayContent: {
                        template: '<div class="overlay-content-stub" />',
                        props: ['tile'],
                    },
                    NotFound: {
                        template: '<div class="not-found-stub">404</div>',
                    },
                },
            },
        });
    }

    it('calls fetchBySlug with the slug from route params', () => {
        createWrapper();
        expect(tilesStore.fetchBySlug).toHaveBeenCalledWith('test-tile', 'de', true);
    });

    it('renders tile content when tile is loaded', async () => {
        const tileData = { id: 1, title: 'Test Tile', slug: 'test-tile' };
        const wrapper = createWrapper(tileData);

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.overlay-header-stub').exists()).toBe(true);
        expect(wrapper.find('.overlay-content-stub').exists()).toBe(true);
    });

    it('renders NotFound when tile is not found (404)', async () => {
        const wrapper = createWrapper(null, { error: { status: 404 } });

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.not-found-stub').exists()).toBe(true);
    });

    it('shows loading state while fetching', async () => {
        tilesStore.loading = true;
        tilesStore.fetchBySlug = vi.fn().mockReturnValue(new Promise(() => {}));

        const wrapper = shallowMount(TileDetailPage, {
            global: {
                stubs: {
                    OverlayHeader: { template: '<div />', props: ['tile'] },
                    OverlayContent: { template: '<div />', props: ['tile'] },
                    NotFound: { template: '<div />' },
                },
            },
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Lade Kachel');
    });

    it('shows NotFound when slug is missing', async () => {
        mockRoute.params = {};
        const wrapper = createWrapper(null);

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.not-found-stub').exists()).toBe(true);
    });

    it('shows error state when store has error', async () => {
        const wrapper = createWrapper(null, { error: { message: 'Server error', status: 500 } });

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Server error');
    });
});
