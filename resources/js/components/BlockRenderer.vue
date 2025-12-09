<template>
    <div>
        <component
            v-for="(block, index) in blocks"
            :key="block.id || `${block.type}-${index}`"
            :is="getBlockComponent(block.type)"
            :block="block"
        />
    </div>
</template>

<script setup>
import HeroBlock from './blocks/HeroBlock.vue';
import TextImageBlock from './blocks/TextImageBlock.vue';
import IntroTextBlock from './blocks/IntroTextBlock.vue';
import SectionBlock from './blocks/SectionBlock.vue';
import ListBlock from './blocks/ListBlock.vue';
import FAQBlock from './blocks/FAQBlock.vue';
import LinkBlock from './blocks/LinkBlock.vue';
import SliderBlock from './blocks/SliderBlock.vue';
import TileAppBlock from './blocks/TileAppBlock.vue';

const props = defineProps({
    blocks: {
        type: Array,
        required: true,
    },
});

const blockComponentMap = {
    hero: HeroBlock,
    'text-image': TextImageBlock,
    'intro-text': IntroTextBlock,
    section: SectionBlock,
    list: ListBlock,
    faq: FAQBlock,
    link: LinkBlock,
    slider: SliderBlock,
    'tile-app': TileAppBlock,
};

function getBlockComponent(blockType) {
    const component = blockComponentMap[blockType];
    if (!component) {
        logWarn(`BlockRenderer: Unknown block type "${blockType}"`);
    }
    return component || null;
}
</script>

