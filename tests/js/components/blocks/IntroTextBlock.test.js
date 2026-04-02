import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import IntroTextBlock from '@/components/blocks/IntroTextBlock.vue';

vi.mock('@/utils/sanitizeHtml', () => ({
    sanitizeHtml: (html) => html,
}));

vi.mock('@/composables/useImageUrl', () => ({
    useImageUrl: (ref) => {
        const { computed } = require('vue');
        return computed(() => {
            const val = ref.value;
            if (!val) return null;
            if (typeof val === 'string' && val.startsWith('http')) return val;
            return val ? `/storage/${val}` : null;
        });
    },
}));

vi.mock('@/utils/api', () => ({
    getApiBaseUrl: () => 'http://localhost',
}));

describe('IntroTextBlock', () => {
    function createWrapper(blockProps = {}) {
        return shallowMount(IntroTextBlock, {
            props: {
                block: {
                    type: 'intro-text',
                    props: blockProps,
                },
            },
        });
    }

    it('renders heading when provided', () => {
        const wrapper = createWrapper({ heading: 'Welcome' });
        expect(wrapper.find('h1').text()).toBe('Welcome');
    });

    it('does not render heading when not provided', () => {
        const wrapper = createWrapper({});
        expect(wrapper.find('h1').exists()).toBe(false);
    });

    it('renders subheading when provided', () => {
        const wrapper = createWrapper({ subheading: 'Subtitle here' });
        expect(wrapper.find('h2').text()).toBe('Subtitle here');
    });

    it('renders text content as HTML', () => {
        const wrapper = createWrapper({ text: '<p>Some text</p>' });
        const prose = wrapper.find('.prose');
        expect(prose.exists()).toBe(true);
        expect(prose.html()).toContain('<p>Some text</p>');
    });

    it('renders image when provided', () => {
        const wrapper = createWrapper({ image: 'photos/test.jpg' });
        const images = wrapper.findAll('img');
        expect(images.length).toBeGreaterThan(0);
        expect(images[0].attributes('src')).toBe('/storage/photos/test.jpg');
    });

    it('does not render images when not provided', () => {
        const wrapper = createWrapper({ heading: 'No image' });
        expect(wrapper.findAll('img')).toHaveLength(0);
    });
});
