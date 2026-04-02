import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import NotFound from '@/components/pages/NotFound.vue';

// Mock vue-router
const mockRoute = {
    path: '/',
    params: {},
    meta: { locale: 'de' },
    query: {},
};

vi.mock('vue-router', () => ({
    useRoute: () => mockRoute,
    useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
    RouterLink: {
        template: '<a :href="to"><slot /></a>',
        props: ['to'],
    },
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

describe('NotFound', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    function createWrapper() {
        return shallowMount(NotFound, {
            global: {
                stubs: {
                    RouterLink: {
                        template: '<a :href="to" class="router-link-stub"><slot /></a>',
                        props: ['to'],
                    },
                },
            },
        });
    }

    it('renders a 404 heading in German by default', () => {
        const wrapper = createWrapper();
        const heading = wrapper.find('h1');
        expect(heading.exists()).toBe(true);
        expect(heading.text()).toContain('404');
        expect(heading.text()).toContain('Seite nicht gefunden');
    });

    it('renders German description text', () => {
        const wrapper = createWrapper();
        expect(wrapper.text()).toContain('Die gesuchte Seite existiert nicht.');
    });

    it('has a link to the German home page', () => {
        const wrapper = createWrapper();
        const link = wrapper.find('a');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toBe('/');
        expect(link.text()).toBe('Zur Startseite');
    });

    it('renders English content when locale is en', () => {
        mockRoute.meta.locale = 'en';
        mockRoute.path = '/en';

        const wrapper = createWrapper();
        expect(wrapper.text()).toContain('Page Not Found');
        expect(wrapper.text()).toContain('The page you are looking for does not exist.');

        const link = wrapper.find('a');
        expect(link.attributes('href')).toBe('/en');
        expect(link.text()).toBe('Go to Home');

        // Reset
        mockRoute.meta.locale = 'de';
        mockRoute.path = '/';
    });

    it('has the main landmark with correct role', () => {
        const wrapper = createWrapper();
        const main = wrapper.find('main');
        expect(main.exists()).toBe(true);
        expect(main.attributes('role')).toBe('main');
    });
});
