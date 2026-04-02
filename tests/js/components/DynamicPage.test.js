import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import DynamicPage from '@/components/pages/DynamicPage.vue';
import { usePagesStore } from '@/stores/pages';

const mockRoute = {
    path: '/kontakt',
    params: { slug: 'kontakt' },
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

describe('DynamicPage', () => {
    let pagesStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        pagesStore = usePagesStore();
        mockRoute.params = { slug: 'kontakt' };
        mockRoute.meta = { locale: 'de' };
        mockRoute.path = '/kontakt';
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    function createWrapper(fetchResult = null, storeOverrides = {}) {
        pagesStore.fetchPageBySlug = vi.fn().mockResolvedValue(fetchResult);
        Object.assign(pagesStore, storeOverrides);

        return shallowMount(DynamicPage, {
            global: {
                stubs: {
                    PageView: {
                        template: '<div class="page-view-stub" />',
                        props: ['pageData', 'locale'],
                    },
                    NotFound: {
                        template: '<div class="not-found-stub">404</div>',
                    },
                },
            },
        });
    }

    it('calls fetchPageBySlug with the slug from route params', () => {
        createWrapper();
        expect(pagesStore.fetchPageBySlug).toHaveBeenCalledWith('kontakt', 'de', true);
    });

    it('renders PageView when page data is loaded', async () => {
        const pageData = { title: 'Kontakt', blocks: [{ type: 'text-image', props: {} }] };
        const wrapper = createWrapper(pageData);

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.page-view-stub').exists()).toBe(true);
    });

    it('renders NotFound when page is not found (404)', async () => {
        const wrapper = createWrapper(null, { error: { status: 404 }, isNotFound: true });

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.not-found-stub').exists()).toBe(true);
    });

    it('shows loading state while fetching', async () => {
        pagesStore.fetchPageBySlug = vi.fn().mockReturnValue(new Promise(() => {}));
        const wrapper = shallowMount(DynamicPage, {
            global: {
                stubs: {
                    PageView: { template: '<div />', props: ['pageData', 'locale'] },
                    NotFound: { template: '<div />' },
                },
            },
        });

        // onMounted triggers loadPage() which sets loading = true
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain('Lade Seite');
    });

    it('handles array slug params (nested pages)', () => {
        mockRoute.params = { slug: ['kontakt', 'testseite'] };
        createWrapper();
        expect(pagesStore.fetchPageBySlug).toHaveBeenCalledWith('kontakt/testseite', 'de', true);
    });

    it('shows error when slug is missing', async () => {
        mockRoute.params = {};
        const wrapper = createWrapper(null);

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // With no slug, the component sets an error
        expect(wrapper.text()).toContain('No slug provided');
    });
});
