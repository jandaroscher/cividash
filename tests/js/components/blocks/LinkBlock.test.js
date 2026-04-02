import { describe, it, expect, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import LinkBlock from '@/components/blocks/LinkBlock.vue';

describe('LinkBlock', () => {
    function createWrapper(blockProps = {}) {
        return shallowMount(LinkBlock, {
            props: {
                block: {
                    type: 'link',
                    props: blockProps,
                },
            },
        });
    }

    it('renders link text', () => {
        const wrapper = createWrapper({ text: 'Click me', url: '/about' });
        expect(wrapper.find('a').text()).toBe('Click me');
    });

    it('applies URL to href', () => {
        const wrapper = createWrapper({ text: 'Link', url: '/about' });
        expect(wrapper.find('a').attributes('href')).toBe('/about');
    });

    it('prepends slash to relative URLs', () => {
        const wrapper = createWrapper({ text: 'Link', url: 'about' });
        expect(wrapper.find('a').attributes('href')).toBe('/about');
    });

    it('keeps absolute URLs as-is', () => {
        const wrapper = createWrapper({ text: 'Link', url: 'https://example.com' });
        expect(wrapper.find('a').attributes('href')).toBe('https://example.com');
    });

    it('sets target _blank and rel for external links', () => {
        const wrapper = createWrapper({ text: 'External', url: 'https://example.com' });
        const link = wrapper.find('a');
        expect(link.attributes('target')).toBe('_blank');
        expect(link.attributes('rel')).toBe('noopener noreferrer');
    });

    it('does not set target or rel for internal links', () => {
        const wrapper = createWrapper({ text: 'Internal', url: '/about' });
        const link = wrapper.find('a');
        expect(link.attributes('target')).toBeUndefined();
        expect(link.attributes('rel')).toBeUndefined();
    });

    it('defaults to # when url is empty', () => {
        const wrapper = createWrapper({ text: 'No URL' });
        expect(wrapper.find('a').attributes('href')).toBe('#');
    });

    it('applies button style class when style is button', () => {
        const wrapper = createWrapper({ text: 'Button', url: '/', style: 'button' });
        expect(wrapper.find('a').classes()).toContain('bg-accent');
    });

    it('applies link style class when style is not button', () => {
        const wrapper = createWrapper({ text: 'Link', url: '/', style: 'link' });
        expect(wrapper.find('a').classes()).toContain('underline');
    });
});
