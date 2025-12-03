<template>
    <div class="px-6 xl:px-20 py-8 flex flex-col gap-10">
        <div v-if="tile?.description" id="section-achievement" class="whitespace-break-spaces">
            <div
                class="prose max-w-none"
                v-html="description"
            />
        </div>

        <template v-if="tile?.background_blocks?.length > 0">
            <template v-for="(block, index) in blocksWithIds" :key="block.id || `${block.type}-${index}`">
                <div :id="block.sectionId" v-if="block.sectionId">
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
            <h3 class="text-3xl md:text-4xl font-bold mb-4">{{ currentLocale.value === 'en' ? 'Indicators' : 'Indikatoren' }}</h3>
            <div class="space-y-4">
                <div
                    v-for="(metric, index) in tile.metrics"
                    :key="index"
                    class="border-b pb-4"
                >
                    <h4 class="font-semibold">{{ metric.name?.[currentLocale.value] || metric.name }}</h4>
                    <p v-if="metric.description" class="text-sm text-gray-600 mt-1">
                        {{ metric.description?.[currentLocale.value] || metric.description }}
                    </p>
                </div>
            </div>
        </div>

        <div v-if="!tile && !data" class="text-center text-gray-500 py-8">
            No content available
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import DOMPurify from 'dompurify';
import { useLocale } from '../../../composables/useLocale';
import HeroBlock from '../../blocks/HeroBlock.vue';
import TextImageBlock from '../../blocks/TextImageBlock.vue';
import IntroTextBlock from '../../blocks/IntroTextBlock.vue';
import SectionBlock from '../../blocks/SectionBlock.vue';
import ListBlock from '../../blocks/ListBlock.vue';
import FAQBlock from '../../blocks/FAQBlock.vue';
import LinkBlock from '../../blocks/LinkBlock.vue';
import SliderBlock from '../../blocks/SliderBlock.vue';

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

const description = computed(() => {
    if (!props.tile) return '';
    const rawDescription = (
        props.tile.description?.[currentLocale.value] ||
        props.tile.description?.de ||
        props.tile.description ||
        ''
    );
    // Sanitize HTML to prevent XSS attacks
    return DOMPurify.sanitize(rawDescription);
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
};

function getBlockComponent(blockType) {
    const component = blockComponentMap[blockType];
    if (!component) {
        logWarn(`Content: Unknown block type "${blockType}"`);
    }
    return component || null;
}

// Add section IDs to blocks for navigation
const blocksWithIds = computed(() => {
    if (!props.tile?.background_blocks) return [];
    
    let backgroundFound = false;
    let engagementFound = false;
    let contributionFound = false;
    
    return props.tile.background_blocks.map((block) => {
        const blockWithId = { ...block };
        
        // Add section IDs based on block type
        // First intro-text or hero block gets section-achievement (Hintergrund)
        if ((block.type === 'intro-text' || block.type === 'hero') && !backgroundFound) {
            blockWithId.sectionId = 'section-achievement';
            backgroundFound = true;
        }
        // First slider block gets section-engagement (Unser Engagement)
        // But if it comes before any intro-text/hero, it can also be in section-achievement
        else if (block.type === 'slider') {
            if (!engagementFound && backgroundFound) {
                // Slider after background content -> section-engagement
                blockWithId.sectionId = 'section-engagement';
                engagementFound = true;
            } else if (!backgroundFound) {
                // Slider before background content -> section-achievement (Hintergrund)
                blockWithId.sectionId = 'section-achievement';
                backgroundFound = true;
            } else if (!engagementFound) {
                // First slider after background -> section-engagement
                blockWithId.sectionId = 'section-engagement';
                engagementFound = true;
            }
        }
        // Contribution section
        else if (block.type === 'section' && block.props?.id === 'contribution' && !contributionFound) {
            blockWithId.sectionId = 'section-contribution';
            contributionFound = true;
        }
        
        return blockWithId;
    });
});
</script>

