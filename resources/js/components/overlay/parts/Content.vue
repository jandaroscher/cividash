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
          v-else
          :is="getBlockComponent(block.type)"
          :block="block"
        />
      </template>
    </template>

    <div v-if="tile?.metrics?.length > 0">
      <h3 class="text-theme-h2 font-bold mb-4">
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
            class="text-sm text-gray-600 mt-1"
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

// Add section IDs to blocks based on jump_mark_label
const blocksWithIds = computed(() => {
    if (!props.tile?.background_blocks) return [];
    return props.tile.background_blocks.map((block, index) => {
        const blockWithId = { ...block };
        if (block.props?.jump_mark_label) {
            blockWithId.sectionId = `section-${index}`;
        }
        return blockWithId;
    });
});
</script>
