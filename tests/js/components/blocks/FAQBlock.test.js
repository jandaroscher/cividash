import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import FAQBlock from '@/components/blocks/FAQBlock.vue';

vi.mock('@/utils/sanitizeHtml', () => ({
    sanitizeHtml: (html) => html,
}));

vi.mock('@/utils/api', () => ({
    getApiBaseUrl: () => 'http://localhost',
}));

describe('FAQBlock', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    function createWrapper(blockProps = {}) {
        return shallowMount(FAQBlock, {
            props: {
                block: {
                    type: 'faq',
                    props: blockProps,
                },
            },
        });
    }

    it('renders FAQ items', () => {
        const wrapper = createWrapper({
            items: [
                { question: 'What is this?', answer: '<p>An FAQ</p>' },
                { question: 'How does it work?', answer: '<p>Like this</p>' },
            ],
        });
        const details = wrapper.findAll('details');
        expect(details).toHaveLength(2);
    });

    it('displays question text in summary', () => {
        const wrapper = createWrapper({
            items: [{ question: 'What is this?', answer: '<p>Answer</p>' }],
        });
        expect(wrapper.find('summary').text()).toContain('What is this?');
    });

    it('renders answer HTML content', () => {
        const wrapper = createWrapper({
            items: [{ question: 'Q?', answer: '<p>The answer</p>' }],
        });
        expect(wrapper.html()).toContain('<p>The answer</p>');
    });

    it('does not render answer div when answer is empty', () => {
        const wrapper = createWrapper({
            items: [{ question: 'Q?' }],
        });
        const details = wrapper.find('details');
        // The v-if="item.answer" should prevent the answer div from rendering
        expect(details.find('.prose').exists()).toBe(false);
    });

    it('renders multiple independent items', () => {
        const wrapper = createWrapper({
            items: [
                { question: 'Q1', answer: '<p>A1</p>' },
                { question: 'Q2', answer: '<p>A2</p>' },
                { question: 'Q3', answer: '<p>A3</p>' },
            ],
        });
        const details = wrapper.findAll('details');
        expect(details).toHaveLength(3);
        expect(details[0].text()).toContain('Q1');
        expect(details[1].text()).toContain('Q2');
        expect(details[2].text()).toContain('Q3');
    });

    it('handles empty items array', () => {
        const wrapper = createWrapper({ items: [] });
        expect(wrapper.findAll('details')).toHaveLength(0);
    });
});
