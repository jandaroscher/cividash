import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import Header from '@/components/layout/Header.vue';
import { useHeaderStore } from '@/stores/header';
import { useBrandingStore } from '@/stores/branding';

// Mock vue-router
const mockRoute = {
    path: '/',
    params: {},
    meta: { locale: 'de' },
};

const mockRouter = {
    push: vi.fn(),
};

vi.mock('vue-router', () => ({
    useRoute: () => mockRoute,
    useRouter: () => mockRouter,
    RouterLink: {
        template: '<a :href="to"><slot /></a>',
        props: ['to'],
    },
}));

// Mock composables
vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({
        currentLocale: { value: 'de' },
        setLocale: vi.fn(),
        getTranslatedSlug: vi.fn().mockResolvedValue(null),
        supportedLocales: ['de', 'en'],
        defaultLocale: 'de',
        getLocale: () => 'de',
    }),
}));

describe('Header', () => {
    let headerStore;
    let brandingStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        headerStore = useHeaderStore();
        brandingStore = useBrandingStore();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    /**
     * Helper to mock fetch and create wrapper.
     * The Header component calls headerStore.fetchConfig() via an immediate watcher on locale,
     * so the fetch mock determines what ends up in the store.
     */
    function createWrapper(fetchData = {}) {
        const defaultData = {
            navigation_items: [],
            show_language_switcher: false,
            dropdown_enabled: false,
            ...fetchData,
        };

        globalThis.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ data: defaultData }),
        });

        return shallowMount(Header, {
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

    it('renders the header element', () => {
        const wrapper = createWrapper();
        expect(wrapper.find('header').exists()).toBe(true);
    });

    it('renders site name when no logo URL is set', () => {
        brandingStore.logoUrl = null;
        const wrapper = createWrapper();

        // Should display the site name from the header config
        expect(wrapper.text()).toContain('Test Dashboard');
    });

    it('renders logo image when logoUrl is set', () => {
        brandingStore.logoUrl = 'https://example.com/logo.png';
        const wrapper = createWrapper();

        const img = wrapper.find('img.logo');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.com/logo.png');
    });

    it('renders navigation items when they exist', async () => {
        const wrapper = createWrapper({
            navigation_items: [
                { label: 'Startseite', url: '/' },
                { label: 'Dashboard', url: '/dashboard' },
                { label: 'Kontakt', url: '/kontakt' },
            ],
        });

        // Wait for fetch to resolve and store to update
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const navLinks = wrapper.findAll('.nav-link');
        expect(navLinks.length).toBe(3);
        expect(navLinks[0].text()).toBe('Startseite');
        expect(navLinks[1].text()).toBe('Dashboard');
        expect(navLinks[2].text()).toBe('Kontakt');
    });

    it('does not render navigation when there are no navigation items', async () => {
        const wrapper = createWrapper({ navigation_items: [] });
        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();

        // Desktop nav should not exist
        const desktopNav = wrapper.find('.desktop-nav');
        expect(desktopNav.exists()).toBe(false);
    });

    it('renders language switcher when showLanguageSwitcher is enabled', async () => {
        const wrapper = createWrapper({
            navigation_items: [{ label: 'Home', url: '/' }],
            show_language_switcher: true,
        });

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Should find DE and EN switcher buttons
        const deSwitcher = wrapper.find('.language-switcher-desktop');
        expect(deSwitcher.exists()).toBe(true);
        expect(deSwitcher.text()).toContain('DE');
        expect(deSwitcher.text()).toContain('EN');
    });

    it('does not render language switcher when showLanguageSwitcher is false', async () => {
        const wrapper = createWrapper({
            navigation_items: [{ label: 'Home', url: '/' }],
            show_language_switcher: false,
        });

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const langSwitcher = wrapper.find('.language-switcher-desktop');
        expect(langSwitcher.exists()).toBe(false);
    });

    it('applies header background color from branding store', () => {
        brandingStore.headerBackgroundColor = '#FF0000';
        const wrapper = createWrapper();

        const header = wrapper.find('header');
        // Happy-DOM keeps hex values as-is rather than converting to rgb()
        expect(header.attributes('style')).toContain('background-color: #FF0000');
    });

    it('shows mobile menu toggle button when navigation items exist', async () => {
        const wrapper = createWrapper({
            navigation_items: [{ label: 'Home', url: '/' }],
        });

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const mobileToggle = wrapper.find('.mobile-menu-toggle');
        expect(mobileToggle.exists()).toBe(true);
    });

    it('opens mobile menu when toggle button is clicked', async () => {
        const wrapper = createWrapper({
            navigation_items: [{ label: 'Home', url: '/' }],
        });

        await vi.dynamicImportSettled();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const mobileToggle = wrapper.find('.mobile-menu-toggle');
        expect(mobileToggle.exists()).toBe(true);
        await mobileToggle.trigger('click');
        await wrapper.vm.$nextTick();

        const mobileMenu = wrapper.find('#mobile-menu');
        expect(mobileMenu.exists()).toBe(true);
        expect(mobileMenu.classes()).toContain('open');
    });
});
