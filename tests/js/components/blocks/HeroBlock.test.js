import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import HeroBlock from '@/components/blocks/HeroBlock.vue';

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

vi.mock('@/utils/sanitizeHtml', () => ({
    sanitizeHtml: (html) => html,
    isExternalUrl: (url) => typeof url === 'string' && /^https?:/.test(url),
}));

describe('HeroBlock', () => {
    function createWrapper(blockProps = {}) {
        return shallowMount(HeroBlock, {
            props: {
                block: {
                    type: 'hero',
                    props: blockProps,
                },
            },
        });
    }

    it('renders title', () => {
        const wrapper = createWrapper({ title: 'Hero Title' });
        expect(wrapper.find('h1').text()).toBe('Hero Title');
    });

    it('renders subtitle', () => {
        const wrapper = createWrapper({ title: 'T', subtitle: 'Subtitle text' });
        expect(wrapper.find('p').text()).toBe('Subtitle text');
    });

    it('renders CTA button when text and url provided', () => {
        const wrapper = createWrapper({ cta_text: 'Learn more', cta_url: '/about' });
        const cta = wrapper.find('a');
        expect(cta.text()).toBe('Learn more');
        expect(cta.attributes('href')).toBe('/about');
    });

    it('does not render CTA when cta_text is missing', () => {
        const wrapper = createWrapper({ cta_url: '/about' });
        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('does not render CTA when cta_url is missing', () => {
        const wrapper = createWrapper({ cta_text: 'Click' });
        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('renders background image when provided', () => {
        const wrapper = createWrapper({ title: 'T', image: 'hero.jpg' });
        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('/storage/hero.jpg');
    });

    it('does not render background image when not provided', () => {
        const wrapper = createWrapper({ title: 'T' });
        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('does not render title when not provided', () => {
        const wrapper = createWrapper({});
        expect(wrapper.find('h1').exists()).toBe(false);
    });

    it('sets target _blank for external CTA URLs', () => {
        const wrapper = createWrapper({ cta_text: 'Go', cta_url: 'https://example.com' });
        const cta = wrapper.find('a');
        expect(cta.attributes('target')).toBe('_blank');
        expect(cta.attributes('rel')).toBe('noopener noreferrer');
    });

    it('does not set target for internal CTA URLs', () => {
        const wrapper = createWrapper({ cta_text: 'Go', cta_url: '/page' });
        const cta = wrapper.find('a');
        expect(cta.attributes('target')).toBeUndefined();
    });
});
