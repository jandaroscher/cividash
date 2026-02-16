import { describe, it, expect, vi, beforeEach } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import BlockRenderer from '@/components/BlockRenderer.vue';

// Stub all block components used by BlockRenderer
const HeroBlockStub = { template: '<div class="hero-block">{{ block.type }}</div>', props: ['block'] };
const TextImageBlockStub = { template: '<div class="text-image-block">{{ block.type }}</div>', props: ['block'] };
const IntroTextBlockStub = { template: '<div class="intro-text-block">{{ block.type }}</div>', props: ['block'] };
const SectionBlockStub = { template: '<div class="section-block">{{ block.type }}</div>', props: ['block'] };
const ListBlockStub = { template: '<div class="list-block">{{ block.type }}</div>', props: ['block'] };
const FAQBlockStub = { template: '<div class="faq-block">{{ block.type }}</div>', props: ['block'] };
const LinkBlockStub = { template: '<div class="link-block">{{ block.type }}</div>', props: ['block'] };
const SliderBlockStub = { template: '<div class="slider-block">{{ block.type }}</div>', props: ['block'] };
const TileAppBlockStub = { template: '<div class="tile-app-block">{{ block.type }}</div>', props: ['block'] };

describe('BlockRenderer', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    function createWrapper(blocks = []) {
        return shallowMount(BlockRenderer, {
            props: { blocks },
            global: {
                stubs: {
                    HeroBlock: HeroBlockStub,
                    TextImageBlock: TextImageBlockStub,
                    IntroTextBlock: IntroTextBlockStub,
                    SectionBlock: SectionBlockStub,
                    ListBlock: ListBlockStub,
                    FAQBlock: FAQBlockStub,
                    LinkBlock: LinkBlockStub,
                    SliderBlock: SliderBlockStub,
                    TileAppBlock: TileAppBlockStub,
                },
            },
        });
    }

    it('renders nothing when blocks array is empty', () => {
        const wrapper = createWrapper([]);
        // The root div should exist but have no block component children
        expect(wrapper.find('div').exists()).toBe(true);
        // No block components should be rendered
        expect(wrapper.findComponent(HeroBlockStub).exists()).toBe(false);
        expect(wrapper.findComponent(FAQBlockStub).exists()).toBe(false);
        expect(wrapper.findComponent(SectionBlockStub).exists()).toBe(false);
    });

    it('renders correct block component for known block types', () => {
        const blocks = [
            { type: 'hero', id: 1, props: {} },
            { type: 'faq', id: 2, props: {} },
            { type: 'section', id: 3, props: {} },
        ];
        const wrapper = createWrapper(blocks);

        expect(wrapper.findComponent(HeroBlockStub).exists()).toBe(true);
        expect(wrapper.findComponent(FAQBlockStub).exists()).toBe(true);
        expect(wrapper.findComponent(SectionBlockStub).exists()).toBe(true);
    });

    it('passes block data as props to child components', () => {
        const heroBlock = { type: 'hero', id: 1, props: { title: 'Hello' } };
        const wrapper = createWrapper([heroBlock]);

        const heroComponent = wrapper.findComponent(HeroBlockStub);
        expect(heroComponent.exists()).toBe(true);
        expect(heroComponent.props('block')).toEqual(heroBlock);
    });

    it('handles unknown block type gracefully by not rendering it', () => {
        const consoleWarnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});

        const blocks = [
            { type: 'nonexistent-block', id: 1, props: {} },
        ];
        const wrapper = createWrapper(blocks);

        // Unknown block type should trigger a console warning
        expect(consoleWarnSpy).toHaveBeenCalledWith(
            expect.stringContaining('Unknown block type "nonexistent-block"')
        );

        consoleWarnSpy.mockRestore();
    });

    it('filters out inactive blocks (is_active === false)', () => {
        const blocks = [
            { type: 'hero', id: 1, props: { is_active: true } },
            { type: 'faq', id: 2, props: { is_active: false } },
            { type: 'section', id: 3, props: {} },
        ];
        const wrapper = createWrapper(blocks);

        // Hero and section should render; FAQ with is_active: false should not
        expect(wrapper.findComponent(HeroBlockStub).exists()).toBe(true);
        expect(wrapper.findComponent(FAQBlockStub).exists()).toBe(false);
        expect(wrapper.findComponent(SectionBlockStub).exists()).toBe(true);
    });

    it('renders all known block types correctly', () => {
        const blockTypes = [
            { type: 'hero', stub: HeroBlockStub },
            { type: 'text-image', stub: TextImageBlockStub },
            { type: 'intro-text', stub: IntroTextBlockStub },
            { type: 'section', stub: SectionBlockStub },
            { type: 'list', stub: ListBlockStub },
            { type: 'faq', stub: FAQBlockStub },
            { type: 'link', stub: LinkBlockStub },
            { type: 'slider', stub: SliderBlockStub },
            { type: 'tile-app', stub: TileAppBlockStub },
        ];

        const blocks = blockTypes.map((bt, index) => ({
            type: bt.type,
            id: index + 1,
            props: {},
        }));

        const wrapper = createWrapper(blocks);

        blockTypes.forEach(({ stub }) => {
            expect(wrapper.findComponent(stub).exists()).toBe(true);
        });
    });
});
