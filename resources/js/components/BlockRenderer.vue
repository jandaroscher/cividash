<template>
  <div>
    <component
      :is="getBlockComponent(block.type)"
      v-for="(block, index) in visibleBlocks"
      :key="block.id || `${block.type}-${index}`"
      :block="block"
    />
  </div>
</template>

<script setup>
import { logWarn } from '../lib/log.js';
import { computed } from 'vue';
import HeroBlock from './blocks/HeroBlock.vue';
import TextImageBlock from './blocks/TextImageBlock.vue';
import IntroTextBlock from './blocks/IntroTextBlock.vue';
import SectionBlock from './blocks/SectionBlock.vue';
import ListBlock from './blocks/ListBlock.vue';
import FAQBlock from './blocks/FAQBlock.vue';
import LinkBlock from './blocks/LinkBlock.vue';
import SliderBlock from './blocks/SliderBlock.vue';
import TileAppBlock from './blocks/TileAppBlock.vue';
import DownloadBlock from './blocks/DownloadBlock.vue';

const props = defineProps({
    blocks: {
        type: Array,
        required: true,
    },
});

const visibleBlocks = computed(() =>
    props.blocks.filter((block) => block?.props?.is_active !== false)
);

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
    'card-grid': TileAppBlock,
    download: DownloadBlock,
};

function getBlockComponent(blockType) {
    const component = blockComponentMap[blockType];
    if (!component) {
        logWarn(`BlockRenderer: Unknown block type "${blockType}"`);
    }
    return component || null;
}
</script>

