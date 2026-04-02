import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import SliderBlock from '@/components/blocks/SliderBlock.vue';

vi.mock('@/utils/sanitizeHtml', () => ({
    sanitizeHtml: (html) => html,
    isExternalUrl: (url) => typeof url === 'string' && /^https?:/.test(url),
}));

vi.mock('@/utils/api', () => ({
    getApiBaseUrl: () => 'http://localhost',
}));

vi.mock('@/composables/useLocale', () => ({
    useLocale: () => ({
        currentLocale: { value: 'de' },
    }),
}));

vi.mock('@egjs/vue3-flicking', () => ({
    default: {
        template: '<div class="flicking-stub"><slot /><slot name="viewport" /></div>',
        props: ['plugins', 'options'],
    },
}));

vi.mock('@egjs/flicking-plugins', () => ({
    Arrow: vi.fn(),
    Pagination: vi.fn(),
}));

vi.mock('@egjs/vue3-flicking/dist/flicking.css', () => ({}));
vi.mock('@egjs/flicking-plugins/dist/arrow.css', () => ({}));
vi.mock('@egjs/flicking-plugins/dist/pagination.css', () => ({}));

describe('SliderBlock', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    function createWrapper(blockProps = {}) {
        return mount(SliderBlock, {
            props: {
                block: {
                    type: 'slider',
                    props: blockProps,
                },
            },
        });
    }

    it('renders heading when provided', () => {
        const wrapper = createWrapper({
            heading: 'Our Slides',
            items: [{ title: 'Slide 1' }],
        });
        expect(wrapper.find('h2').text()).toBe('Our Slides');
    });

    it('does not render heading when empty', () => {
        const wrapper = createWrapper({
            items: [{ title: 'Slide 1' }],
        });
        expect(wrapper.find('h2').exists()).toBe(false);
    });

    it('renders slides from items', () => {
        const wrapper = createWrapper({
            items: [
                { title: 'Slide A', description: 'Desc A' },
                { title: 'Slide B', description: 'Desc B' },
            ],
        });
        expect(wrapper.text()).toContain('Slide A');
        expect(wrapper.text()).toContain('Slide B');
    });

    it('renders nothing when items is empty', () => {
        const wrapper = createWrapper({ items: [] });
        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('renders nothing when items is not provided', () => {
        const wrapper = createWrapper({});
        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('filters out inactive items', () => {
        const wrapper = createWrapper({
            items: [
                { title: 'Active', is_active: true },
                { title: 'Inactive', is_active: false },
            ],
        });
        expect(wrapper.text()).toContain('Active');
        expect(wrapper.text()).not.toContain('Inactive');
    });

    it('handles single slide', () => {
        const wrapper = createWrapper({
            items: [{ title: 'Only One' }],
        });
        expect(wrapper.text()).toContain('Only One');
    });

    it('renders slide link when link_url is provided', () => {
        const wrapper = createWrapper({
            items: [{ title: 'Slide', link_url: '/more', link_text: 'Read more' }],
        });
        const link = wrapper.find('a');
        expect(link.exists()).toBe(true);
        expect(link.text()).toBe('Read more');
        expect(link.attributes('href')).toBe('/more');
    });

    it('renders slide image from storage path', () => {
        const wrapper = createWrapper({
            items: [{ title: 'With image', image: 'slides/photo.jpg' }],
        });
        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('/storage/slides/photo.jpg');
    });
});
