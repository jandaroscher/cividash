import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createHead } from '@unhead/vue/client';
import PageView from '@/components/pages/PageView.vue';
import { usePagesStore } from '@/stores/pages';

vi.mock('vue-router', () => ({
    useRoute: () => ({ path: '/', params: {}, meta: { locale: 'de' }, query: {} }),
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

describe('PageView', () => {
    let pagesStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        pagesStore = usePagesStore();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    function createWrapper(props = {}) {
        return shallowMount(PageView, {
            props,
            global: {
                plugins: [createHead()],
                stubs: {
                    BlockRenderer: {
                        template: '<div class="block-renderer-stub" />',
                        props: ['blocks'],
                    },
                },
            },
        });
    }

    it('renders BlockRenderer when pageData has blocks', () => {
        const wrapper = createWrapper({
            pageData: {
                title: 'Test Page',
                blocks: [
                    { type: 'hero', props: { title: 'Hello' } },
                    { type: 'text-image', props: { text: 'World' } },
                ],
            },
        });

        const blockRenderer = wrapper.find('.block-renderer-stub');
        expect(blockRenderer.exists()).toBe(true);
    });

    it('shows empty state when pageData has no blocks', () => {
        const wrapper = createWrapper({
            pageData: { title: 'Empty Page', blocks: [] },
        });

        expect(wrapper.text()).toContain('Kein Inhalt verfügbar');
        expect(wrapper.find('.block-renderer-stub').exists()).toBe(false);
    });

    it('shows loading state when store is loading', () => {
        pagesStore.loading = true;
        const wrapper = createWrapper({ pageData: null });

        expect(wrapper.text()).toContain('Lade Seite');
    });

    it('shows error state when store has error', () => {
        pagesStore.error = { message: 'Something went wrong' };
        const wrapper = createWrapper({ pageData: null });

        expect(wrapper.text()).toContain('Something went wrong');
    });

    it('renders nothing meaningful when pageData is null and not loading or error', () => {
        const wrapper = createWrapper({ pageData: null });
        // No loading, no error, no pageData => only the wrapper div
        expect(wrapper.find('.block-renderer-stub').exists()).toBe(false);
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('shows English empty state text when locale is en', () => {
        const wrapper = createWrapper({
            pageData: { title: 'Empty', blocks: [] },
            locale: 'en',
        });

        expect(wrapper.text()).toContain('No content available');
    });
});
