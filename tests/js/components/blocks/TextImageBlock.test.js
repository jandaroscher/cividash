import { describe, it, expect, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import TextImageBlock from '@/components/blocks/TextImageBlock.vue';

vi.mock('@/utils/sanitizeHtml', () => ({
    sanitizeHtml: (html) => html,
}));

describe('TextImageBlock', () => {
    function createWrapper(blockProps = {}) {
        return shallowMount(TextImageBlock, {
            props: {
                block: {
                    type: 'text-image',
                    props: blockProps,
                },
            },
        });
    }

    it('renders heading when provided', () => {
        const wrapper = createWrapper({ heading: 'My Heading' });
        expect(wrapper.find('h2').text()).toBe('My Heading');
    });

    it('does not render heading when empty', () => {
        const wrapper = createWrapper({ text: '<p>text</p>' });
        expect(wrapper.find('h2').exists()).toBe(false);
    });

    it('renders text as HTML', () => {
        const wrapper = createWrapper({ text: '<p>Hello</p>' });
        const prose = wrapper.find('.prose');
        expect(prose.exists()).toBe(true);
        expect(prose.html()).toContain('<p>Hello</p>');
    });

    it('renders image from storage path', () => {
        const wrapper = createWrapper({ image: 'photos/pic.jpg' });
        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('/storage/photos/pic.jpg');
    });

    it('renders image from full URL', () => {
        const wrapper = createWrapper({ image: 'https://example.com/pic.jpg' });
        const img = wrapper.find('img');
        expect(img.attributes('src')).toBe('https://example.com/pic.jpg');
    });

    it('handles image as array', () => {
        const wrapper = createWrapper({ image: ['photos/pic.jpg'] });
        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('/storage/photos/pic.jpg');
    });

    it('uses image_alt for alt text', () => {
        const wrapper = createWrapper({ image: 'pic.jpg', image_alt: 'My Alt' });
        expect(wrapper.find('img').attributes('alt')).toBe('My Alt');
    });

    it('defaults alt text to Image', () => {
        const wrapper = createWrapper({ image: 'pic.jpg' });
        expect(wrapper.find('img').attributes('alt')).toBe('Image');
    });

    it('renders image on left by default', () => {
        const wrapper = createWrapper({ image: 'pic.jpg', text: '<p>Hi</p>' });
        // First div with order-1 should contain the image
        const orderDivs = wrapper.findAll('.order-1');
        expect(orderDivs.length).toBeGreaterThan(0);
        const firstOrderDiv = orderDivs[0];
        expect(firstOrderDiv.find('img').exists()).toBe(true);
    });

    it('renders image on right when image_position is right', () => {
        const wrapper = createWrapper({ image: 'pic.jpg', text: '<p>Hi</p>', image_position: 'right' });
        // With right position, the image div should have md:order-2 class
        const imgs = wrapper.findAll('img');
        expect(imgs.length).toBeGreaterThan(0);
        const imageParent = imgs[0].element.parentElement;
        expect(imageParent.classList.contains('md:order-2')).toBe(true);
    });
});
