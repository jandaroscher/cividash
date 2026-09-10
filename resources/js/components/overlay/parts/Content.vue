<template>
  <div class="px-6 xl:px-20 py-8 flex flex-col gap-10">
    <template v-if="tile?.background_blocks?.length > 0">
      <template
        v-for="(block, index) in blocksWithIds"
        :key="block.id || `${block.type}-${index}`"
      >
        <div
          v-if="block.sectionId"
          :id="block.sectionId"
        >
          <component
            :is="getBlockComponent(block.type)"
            :block="block"
          />
        </div>
        <component
          :is="getBlockComponent(block.type)"
          v-else
          :block="block"
        />
      </template>
    </template>

    <div v-if="tile?.metrics?.length > 0">
      <h3 class="content-heading text-theme-h2 mb-4">
        {{ currentLocale.value === 'en' ? 'Indicators' : 'Indikatoren' }}
      </h3>
      <div class="space-y-4">
        <div
          v-for="(metric, index) in tile.metrics"
          :key="index"
          class="border-b pb-4"
        >
          <h4 class="font-semibold">
            {{ metric.name?.[currentLocale.value] || metric.name }}
          </h4>
          <p
            v-if="metric.description"
            class="text-sm text-gray-400 mt-2.5"
          >
            {{ metric.description?.[currentLocale.value] || metric.description }}
          </p>
        </div>
      </div>
    </div>

    <div
      v-if="!tile && !data"
      class="text-center text-gray-500 py-8"
    >
      No content available
    </div>
  </div>
</template>

<script setup>
import { logWarn } from '../../../lib/log.js';
import { computed } from 'vue';
import { useLocale } from '../../../composables/useLocale';
import HeroBlock from '../../blocks/HeroBlock.vue';
import TextImageBlock from '../../blocks/TextImageBlock.vue';
import IntroTextBlock from '../../blocks/IntroTextBlock.vue';
import SectionBlock from '../../blocks/SectionBlock.vue';
import ListBlock from '../../blocks/ListBlock.vue';
import FAQBlock from '../../blocks/FAQBlock.vue';
import LinkBlock from '../../blocks/LinkBlock.vue';
import SliderBlock from '../../blocks/SliderBlock.vue';
import DownloadBlock from '../../blocks/DownloadBlock.vue';
import { buildJumpMarkSections } from '../../../utils/jumpMarks';

const props = defineProps({
    tile: {
        type: Object,
        default: null,
    },
    data: {
        type: Object,
        default: null,
    },
});

const { currentLocale } = useLocale();

const blockComponentMap = {
    hero: HeroBlock,
    'text-image': TextImageBlock,
    'intro-text': IntroTextBlock,
    section: SectionBlock,
    list: ListBlock,
    faq: FAQBlock,
    link: LinkBlock,
    slider: SliderBlock,
    download: DownloadBlock,
};

function getBlockComponent(blockType) {
    const component = blockComponentMap[blockType];
    if (!component) {
        logWarn(`Content: Unknown block type "${blockType}"`);
    }
    return component || null;
}

// Add section IDs to blocks that will be exposed as a jump mark in Header.vue.
// Uses the same shared logic (buildJumpMarkSections) as Header.vue so a section
// id is only assigned here when Header will actually show a link to it, and
// Header only shows a link when a section id is assigned here.
const blocksWithIds = computed(() => {
    if (!props.tile?.background_blocks) return [];
    const sections = buildJumpMarkSections(props.tile.background_blocks);
    return props.tile.background_blocks.map((block, index) => {
        const blockWithId = { ...block };
        const section = sections[index];
        if (section?.label && section?.hasContent) {
            blockWithId.sectionId = section.sectionId;
        }
        return blockWithId;
    });
});
</script>
