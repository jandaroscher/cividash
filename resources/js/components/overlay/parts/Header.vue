<template>
  <div
    class="px-6 xl:px-20 py-8 shadow-header flex flex-col gap-6"
    :style="{ backgroundColor: 'var(--card-background-color, #FFFFFF)' }"
  >
    <div class="flex flex-row justify-between space-x-4">
      <div
        v-if="tile"
        class="text-theme-primary font-bold text-theme-h1 hyphens-auto min-w-0"
      >
        {{ title }}
      </div>
      <button
        class="h-9 rounded-full flex justify-center shrink-0 hover:shadow-info-close transition-shadow duration-200"
        aria-label="Close overlay"
        @click="$emit('close')"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="36"
          height="36"
          viewBox="0 0 36 36"
        >
          <g>
            <g
              fill="none"
              stroke="#191919"
              stroke-width="2"
            >
              <circle
                cx="18"
                cy="18"
                r="18"
                stroke="none"
              />
              <circle
                cx="18"
                cy="18"
                r="17"
                fill="none"
              />
            </g>
            <g transform="translate(10 10)">
              <path
                d="M6396.07-5129.94l16-16"
                transform="translate(-6396.07 5145.938)"
                fill="none"
                stroke="#191919"
                stroke-width="2"
              />
              <path
                d="M0,16,16,0"
                transform="translate(16) rotate(90)"
                fill="none"
                stroke="#191919"
                stroke-width="2"
              />
            </g>
          </g>
        </svg>
      </button>
    </div>
    <div class="flex flex-col xl:flex-row xl:justify-between xl:items-center gap-6">
      <div class="flex flex-col gap-[10px]">
        <a
          v-for="mark in jumpMarks"
          :key="mark.sectionId"
          :href="`#${mark.sectionId}`"
          class="h-[26px] text-white flex flex-row w-fit"
          :style="{ backgroundColor: brandingStore.primaryColor }"
          @click.prevent="scrollToSection(mark.sectionId)"
        >
          <span
            class="w-[26px] block justify-center items-center flex"
            :style="{ backgroundColor: brandingStore.secondaryColor || brandingStore.primaryColor }"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="15.874"
              height="10.144"
              viewBox="0 0 15.874 10.144"
            >
              <g transform="translate(15.419 0.376) rotate(90)">
                <g transform="translate(0.678 0.612)">
                  <path
                    d="M1.061,1.061l6.955,6.87L1.061,14.8"
                    transform="translate(-1.061 -1.061)"
                    fill="none"
                    stroke="#fff"
                    stroke-width="3"
                  />
                </g>
              </g>
            </svg>
          </span>
          <span class="px-2 py-0.5">{{ mark.label }}</span>
        </a>
      </div>
      <div
        v-if="backgroundCategories && backgroundCategories.length > 0"
        class="flex flex-row gap-4"
      >
        <img
          v-for="cat in backgroundCategories"
          :key="cat.id"
          :alt="categoryTitle(cat)"
          :src="categoryIcon(cat)"
          width="100"
          loading="lazy"
        >
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useLocale } from '../../../composables/useLocale';
import { useBrandingStore } from '../../../stores/branding';

const brandingStore = useBrandingStore();

const props = defineProps({
    tile: {
        type: Object,
        default: null,
    },
});

defineEmits(['close']);

const { currentLocale } = useLocale();

const title = computed(() => {
    if (!props.tile) return 'Tile Details';
    return (
        props.tile.title?.[currentLocale.value] ||
        props.tile.title?.de ||
        props.tile.title ||
        'Tile Details'
    );
});

const backgroundCategories = computed(() => {
    const groupKey = brandingStore.tileBackgroundCategoryGroupKey;
    if (!groupKey) return [];
    const categories = props.tile?.categories || [];
    return categories.filter(category => category.group?.key === groupKey);
});

const jumpMarks = computed(() => {
    if (!props.tile?.background_blocks) return [];
    return props.tile.background_blocks
        .map((block, index) => ({
            label: block.props?.jump_mark_label || null,
            sectionId: `section-${index}`,
        }))
        .filter(item => item.label);
});

function categoryIcon(cat) {
    if (!cat.icon) return '';
    if (typeof cat.icon === 'string') {
        return cat.icon;
    }
    return cat.icon[currentLocale.value] || cat.icon.de || cat.icon.en || '';
}

function categoryTitle(cat) {
    if (!cat.title) return '';
    if (typeof cat.title === 'string') {
        return cat.title;
    }
    return cat.title[currentLocale.value] || cat.title.de || cat.title.en || '';
}

function scrollToSection(sectionId) {
    const element = document.getElementById(sectionId);
    if (element) {
        // Find the sidebar container (parent of the overlay content)
        const sidebarContainer = element.closest('.overflow-y-auto');
        if (sidebarContainer) {
            const containerRect = sidebarContainer.getBoundingClientRect();
            const targetRect = element.getBoundingClientRect();
            const scrollTop = sidebarContainer.scrollTop + (targetRect.top - containerRect.top) - 20; // 20px offset
            sidebarContainer.scrollTo({
                top: scrollTop,
                behavior: 'smooth',
            });
        } else {
            // Fallback to standard scrollIntoView
            element.scrollIntoView({
                behavior: 'smooth',
            });
        }
    }
}
</script>
