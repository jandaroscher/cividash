import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import HomePage from '@/components/pages/HomePage.vue';
import { usePagesStore } from '@/stores/pages';

const mockRoute = {
    path: '/',
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

vi.mock('@/composables/useMeta', () => ({
    setMetaTags: vi.fn(),
    useMeta: () => ({ setMetaTags: vi.fn() }),
}));

vi.mock('@/utils/api', () => ({ getApiBaseUrl: () => 'http://localhost' }));

describe('HomePage', () => {
    let pagesStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        pagesStore = usePagesStore();
    });

    afterEach(() => {
        vi.restoreAllMocks();
        mockRoute.meta.locale = 'de';
    });

    function createWrapper(fetchResult = null) {
        // Mock the store's fetchRootPage
        pagesStore.fetchRootPage = vi.fn().mockResolvedValue(fetchResult);

        return shallowMount(HomePage, {
            global: {
                stubs: {
                    PageView: {
                        template: '<div class="page-view-stub" />',
                        props: ['pageData', 'locale'],
                    },
                },
            },
        });
    }

    it('calls fetchRootPage on mount', () => {
        createWrapper();
        expect(pagesStore.fetchRootPage).toHaveBeenCalledWith('de', true);
    });

    it('renders PageView when page data is loaded', async () => {
        const pageData = { title: 'Home', blocks: [{ type: 'hero', props: {} }] };
        const wrapper = createWrapper(pageData);

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.page-view-stub').exists()).toBe(true);
    });

    it('shows loading state while fetching', () => {
        // Create a promise that never resolves to keep loading state
        pagesStore.fetchRootPage = vi.fn().mockReturnValue(new Promise(() => {}));
        const wrapper = shallowMount(HomePage, {
            global: {
                stubs: {
                    PageView: { template: '<div class="page-view-stub" />', props: ['pageData', 'locale'] },
                },
            },
        });

        expect(wrapper.text()).toContain('Lade Startseite');
    });

    it('shows error state when fetch fails', async () => {
        pagesStore.fetchRootPage = vi.fn().mockRejectedValue(new Error('Network error'));

        const wrapper = shallowMount(HomePage, {
            global: {
                stubs: {
                    PageView: { template: '<div class="page-view-stub" />', props: ['pageData', 'locale'] },
                },
            },
        });

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Network error');
    });

    it('shows error when fetchRootPage returns null with store error', async () => {
        pagesStore.error = { message: 'Root page not found' };
        pagesStore.fetchRootPage = vi.fn().mockResolvedValue(null);

        const wrapper = shallowMount(HomePage, {
            global: {
                stubs: {
                    PageView: { template: '<div class="page-view-stub" />', props: ['pageData', 'locale'] },
                },
            },
        });

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Root page not found');
    });
});
