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

    it('offers downscaled copies of uploaded photos', () => {
        const img = createWrapper({ src: '/storage/page-blocks/slide.JPG', sizes: '80vw' }).find('img');
        expect(img.attributes('srcset')).toBe(
            '/storage/_resized/480/page-blocks/slide.JPG 480w, '
            + '/storage/_resized/960/page-blocks/slide.JPG 960w, '
            + '/storage/_resized/1600/page-blocks/slide.JPG 1600w',
        );
        expect(img.attributes('sizes')).toBe('80vw');
    });

    it('escapes spaces and commas in file names inside the srcset', () => {
        const img = createWrapper({ src: '/storage/slider/team photo, final.png', sizes: '80vw' }).find('img');
        expect(img.attributes('srcset').split(', ')[0]).toBe('/storage/_resized/480/slider/team%20photo%2C%20final.png 480w');
    });

    it('offers no srcset unless the block states the rendered width', () => {
        expect(createWrapper({ src: '/storage/page-blocks/slide.jpg' }).find('img').attributes('srcset')).toBeUndefined();
    });

    it('treats an absolute URL on the own origin like a relative one', () => {
        const img = createWrapper({ src: `${window.location.origin}/storage/hero/photo.jpg`, sizes: '100vw' }).find('img');
        expect(img.attributes('srcset')).toContain('/storage/_resized/960/hero/photo.jpg 960w');
    });

    it('leaves vector graphics and external images as they are', () => {
        for (const src of ['/storage/icons/logo.svg', 'https://example.org/pic.jpg']) {
            const img = createWrapper({ src, sizes: '80vw' }).find('img');
            expect(img.attributes('srcset')).toBeUndefined();
            expect(img.attributes('sizes')).toBeUndefined();
        }
    });
});
