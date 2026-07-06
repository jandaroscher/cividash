import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import DownloadBlock from '@/components/blocks/DownloadBlock.vue';

vi.mock('@/utils/api', () => ({
    getApiBaseUrl: () => 'http://localhost',
}));

describe('DownloadBlock', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        // Mock fetch to prevent actual HEAD requests
        global.fetch = vi.fn().mockResolvedValue({
            headers: new Headers({ 'content-length': '1024' }),
        });
    });

    function createWrapper(blockProps = {}) {
        return shallowMount(DownloadBlock, {
            props: {
                block: {
                    type: 'download',
                    props: blockProps,
                },
            },
        });
    }

    it('renders heading when provided', () => {
        const wrapper = createWrapper({ heading: 'Downloads', items: [] });
        expect(wrapper.find('h2').text()).toBe('Downloads');
    });

    it('does not render heading when not provided', () => {
        const wrapper = createWrapper({ items: [] });
        expect(wrapper.find('h2').exists()).toBe(false);
    });

    it('renders download links for active items', () => {
        const wrapper = createWrapper({
            items: [
                { file: 'docs/report.pdf', title: 'Annual Report' },
                { file: 'docs/guide.pdf', title: 'User Guide' },
            ],
        });
        const links = wrapper.findAll('a[download]');
        expect(links).toHaveLength(2);
        expect(links[0].text()).toContain('Annual Report');
        expect(links[1].text()).toContain('User Guide');
    });

    it('sets correct href from file path', () => {
        const wrapper = createWrapper({
            items: [{ file: 'docs/report.pdf', title: 'Report' }],
        });
        const link = wrapper.find('a[download]');
        expect(link.attributes('href')).toBe('/storage/docs/report.pdf');
    });

    it('keeps full URLs as-is for href', () => {
        const wrapper = createWrapper({
            items: [{ file: 'https://cdn.example.com/file.pdf', title: 'Remote File' }],
        });
        expect(wrapper.find('a[download]').attributes('href')).toBe('https://cdn.example.com/file.pdf');
    });

    it('shows file extension', () => {
        const wrapper = createWrapper({
            items: [{ file: 'docs/report.pdf', title: 'Report' }],
        });
        expect(wrapper.text()).toContain('PDF');
    });

    it('filters out inactive items', () => {
        const wrapper = createWrapper({
            items: [
                { file: 'a.pdf', title: 'Active', is_active: true },
                { file: 'b.pdf', title: 'Inactive', is_active: false },
            ],
        });
        const links = wrapper.findAll('a[download]');
        expect(links).toHaveLength(1);
        expect(links[0].text()).toContain('Active');
    });

    it('handles empty items array', () => {
        const wrapper = createWrapper({ items: [] });
        expect(wrapper.findAll('a[download]')).toHaveLength(0);
    });

    it('handles missing items prop', () => {
        const wrapper = createWrapper({});
        expect(wrapper.findAll('a[download]')).toHaveLength(0);
    });

    it('renders description text when provided', () => {
        const wrapper = createWrapper({
            text: '<p>Download these files</p>',
            items: [],
        });
        expect(wrapper.html()).toContain('Download these files');
    });

    it('limits the clickable area of the link to its content width', () => {
        const wrapper = createWrapper({
            items: [{ file: 'docs/report.pdf', title: 'Report' }],
        });
        const link = wrapper.find('a[download]');
        // The anchor must shrink to its content (inline-flex + w-fit) instead of
        // spanning the full row width (flex), so empty space next to the entry
        // is no longer clickable/focusable.
        expect(link.classes()).toContain('inline-flex');
        expect(link.classes()).toContain('w-fit');
        expect(link.classes()).not.toContain('flex');
        // Keep a large enough touch target for accessibility (WCAG target size).
        expect(link.classes()).toContain('min-h-11');
    });
});
