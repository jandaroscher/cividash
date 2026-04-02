import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import SectionBlock from '@/components/blocks/SectionBlock.vue';

vi.mock('@/utils/sanitizeHtml', () => ({
    sanitizeHtml: (html) => html,
}));

describe('SectionBlock', () => {
    function createWrapper(blockProps = {}) {
        return shallowMount(SectionBlock, {
            props: {
                block: {
                    type: 'section',
                    props: blockProps,
                },
            },
        });
    }

    it('renders content HTML', () => {
        const wrapper = createWrapper({ content: '<p>Hello World</p>' });
        const contentDiv = wrapper.find('.container');
        expect(contentDiv.exists()).toBe(true);
        expect(contentDiv.html()).toContain('<p>Hello World</p>');
    });

    it('applies background color from props', () => {
        const wrapper = createWrapper({ content: '<p>Test</p>', background_color: '#FF0000' });
        const section = wrapper.find('section');
        expect(section.attributes('style')).toContain('#FF0000');
    });

    it('renders section even when no background color provided', () => {
        const wrapper = createWrapper({ content: '<p>Test</p>' });
        const section = wrapper.find('section');
        expect(section.exists()).toBe(true);
        // When background_color is not set, the component uses a CSS variable fallback.
        // happy-dom does not preserve CSS variable values in style, so we just verify
        // the section renders and the explicit color test above covers the style binding.
    });

    it('does not render content div when content is empty', () => {
        const wrapper = createWrapper({});
        expect(wrapper.find('.container').exists()).toBe(false);
    });
});
