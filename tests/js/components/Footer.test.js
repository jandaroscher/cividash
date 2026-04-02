import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import Footer from '@/components/layout/Footer.vue';
import { useFooterStore } from '@/stores/footer';
import { useBrandingStore } from '@/stores/branding';

// Mock vue-router (required by useLocale composable)
vi.mock('vue-router', () => ({
    useRoute: () => ({
        path: '/',
        params: {},
        meta: { locale: 'de' },
    }),
    useRouter: () => ({
        push: vi.fn(),
    }),
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
        getTranslatedSlug: vi.fn(),
        supportedLocales: ['de', 'en'],
        defaultLocale: 'de',
        getLocale: () => 'de',
    }),
}));

// Mock api util
vi.mock('@/utils/api', () => ({
    getApiBaseUrl: () => 'http://localhost',
}));


describe('Footer', () => {
    let footerStore;
    let brandingStore;

    beforeEach(() => {
        setActivePinia(createPinia());
        footerStore = useFooterStore();
        brandingStore = useBrandingStore();

        // Mock fetch for footer config API call made via watcher
        globalThis.fetch = vi.fn().mockResolvedValue({
            ok: true,
            json: () =>
                Promise.resolve({
                    data: {
                        footer_navigation_items: [],
                        social_links: [],
                        layout_type: 'columns',
                        columns: 4,
                        social_links_enabled: false,
                        copyright_text: null,
                    },
                }),
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    function createWrapper() {
        return shallowMount(Footer, {
            global: {
                stubs: {
                    RouterLink: {
                        template: '<a :href="to" class="router-link-stub"><slot /></a>',
                        props: ['to'],
                    },
                    SocialIcon: { template: '<span class="social-icon-stub" />' },
                },
            },
        });
    }

    it('renders the footer element', () => {
        const wrapper = createWrapper();
        expect(wrapper.find('footer').exists()).toBe(true);
    });

    it('renders footer navigation items', async () => {
        footerStore.footerNavigationItems = [
            { label: 'Impressum', url: '/impressum' },
            { label: 'Datenschutz', url: '/datenschutz' },
            { label: 'Barrierefreiheit', url: '/barrierefreiheit' },
        ];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const navLinks = wrapper.findAll('.footer-link');
        const linkTexts = navLinks.map(link => link.text());
        expect(linkTexts).toContain('Impressum');
        expect(linkTexts).toContain('Datenschutz');
        expect(linkTexts).toContain('Barrierefreiheit');
    });

    it('does not render footer navigation items when no items exist', () => {
        footerStore.footerNavigationItems = [];
        const wrapper = createWrapper();

        const navLinks = wrapper.findAll('.footer-link');
        expect(navLinks.length).toBe(0);
    });

    it('renders social links when enabled and links exist', async () => {
        footerStore.socialLinksEnabled = true;
        footerStore.socialLinks = [
            { link: 'https://twitter.com/example', icon: 'https://example.com/twitter.svg', title: 'Twitter', is_active: true },
            { link: 'https://facebook.com/example', icon: 'https://example.com/facebook.svg', title: 'Facebook', is_active: true },
        ];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const socialLinks = wrapper.findAll('.social-icon-link');
        expect(socialLinks.length).toBe(2);
        expect(socialLinks[0].attributes('href')).toBe('https://twitter.com/example');
        expect(socialLinks[0].attributes('title')).toBe('Twitter');
    });

    it('does not render social links section when disabled', async () => {
        footerStore.socialLinksEnabled = false;
        footerStore.socialLinks = [
            { link: 'https://twitter.com/example', icon: 'https://example.com/twitter.svg', title: 'Twitter', is_active: true },
        ];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const socialLinks = wrapper.findAll('.social-icon-link');
        expect(socialLinks.length).toBe(0);
    });

    it('applies footer background color from branding store', () => {
        brandingStore.footerBackgroundColor = '#333333';
        const wrapper = createWrapper();

        // Background color is on the inner div, not the footer element
        const styledDiv = wrapper.find('[style]');
        expect(styledDiv.exists()).toBe(true);
        expect(styledDiv.attributes('style')).toContain('#333333');
    });

    it('renders footer links in a flat layout', async () => {
        footerStore.layoutType = 'simple';
        footerStore.footerNavigationItems = [
            { label: 'Link A', url: '/a' },
            { label: 'Link B', url: '/b' },
        ];

        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        const links = wrapper.findAll('.footer-link');
        expect(links.length).toBe(2);
        expect(links[0].text()).toBe('Link A');
        expect(links[1].text()).toBe('Link B');
    });
});
