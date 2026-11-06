import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BlockImage from '@/components/BlockImage.vue';

describe('BlockImage', () => {
    function createWrapper(props = {}, attrs = {}) {
        return mount(BlockImage, { props: { src: '/storage/pic.jpg', ...props }, attrs });
    }

    it('renders the image with alt text and title', () => {
        const img = createWrapper({ alt: 'Market square', title: 'Spring market' }).find('img');
        expect(img.attributes('src')).toBe('/storage/pic.jpg');
        expect(img.attributes('alt')).toBe('Market square');
        expect(img.attributes('title')).toBe('Spring market');
    });

    it('renders an empty alt and no title when none are set', () => {
        const img = createWrapper().find('img');
        expect(img.attributes('alt')).toBe('');
        expect(img.attributes('title')).toBeUndefined();
    });

    it('overlays the copyright on the image', () => {
        const caption = createWrapper({ copyright: 'Jane Doe' }).find('figure > figcaption');
        expect(caption.text()).toBe('© Jane Doe');
    });

    it('does not repeat a copyright sign the editor already typed', () => {
        expect(createWrapper({ copyright: '© Jane Doe' }).find('figcaption').text()).toBe('© Jane Doe');
    });

    it('renders no overlay without copyright', () => {
        expect(createWrapper({ copyright: '  ' }).find('figcaption').exists()).toBe(false);
    });

    it('puts layout classes on the figure and image classes on the image', () => {
        const wrapper = createWrapper({ imgClass: 'w-full' }, { class: 'lg:hidden' });
        expect(wrapper.find('figure').classes()).toContain('lg:hidden');
        expect(wrapper.find('img').classes()).toContain('w-full');
    });
});
