import { describe, it, expect } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import ListBlock from '@/components/blocks/ListBlock.vue';

describe('ListBlock', () => {
    function createWrapper(blockProps = {}) {
        return shallowMount(ListBlock, {
            props: {
                block: {
                    type: 'list',
                    props: blockProps,
                },
            },
        });
    }

    it('renders bullet list items', () => {
        const wrapper = createWrapper({
            list_type: 'bullet',
            items: [{ text: 'Item A' }, { text: 'Item B' }],
        });
        expect(wrapper.find('ul').exists()).toBe(true);
        const items = wrapper.findAll('li');
        expect(items).toHaveLength(2);
        expect(items[0].text()).toBe('Item A');
        expect(items[1].text()).toBe('Item B');
    });

    it('renders ordered list when list_type is not bullet', () => {
        const wrapper = createWrapper({
            list_type: 'numbered',
            items: [{ text: 'First' }, { text: 'Second' }],
        });
        expect(wrapper.find('ol').exists()).toBe(true);
        expect(wrapper.find('ul').exists()).toBe(false);
        expect(wrapper.findAll('li')).toHaveLength(2);
    });

    it('handles empty items array', () => {
        const wrapper = createWrapper({
            list_type: 'bullet',
            items: [],
        });
        expect(wrapper.find('ul').exists()).toBe(true);
        expect(wrapper.findAll('li')).toHaveLength(0);
    });

    it('defaults to ordered list when list_type is undefined', () => {
        const wrapper = createWrapper({
            items: [{ text: 'Solo' }],
        });
        expect(wrapper.find('ol').exists()).toBe(true);
    });
});
