<template>
  <div
    v-show="shouldShow"
    class="tile-waterfall-item block hyphens-auto"
    :class="{ 'pb-5 md:pb-10': !isIframe }"
  >
    <div
      class="shadow-card demo-city-card"
      :style="cardContainerStyle"
    >
      <!-- Demo City look: bold blue title bar with white heading + inverted tag,
           mirroring the full-colour cards on demo-city.de. A structural change
           not achievable via color/font tokens alone (Approach A). -->
      <div class="demo-city-title-bar">
        <span class="demo-city-title-text hyphens-auto">{{ header }}</span>
        <span
          v-if="tenantStore.name"
          class="demo-city-badge shrink-0"
        >{{ tenantStore.name }}</span>
      </div>
      <div
        :class="backgroundClass ? [backgroundClass] : []"
        :style="backgroundColorStyle"
        class="py-6 text-black relative"
      >
        <div
          v-if="subheader"
          class="text-lg font-bold px-4"
        >
          {{ subheader }}
        </div>

        <!-- Trend moved next to the value/subheader (was rendered near the
             footer in the default TileCard) - "Wert/Trend umsortiert". -->
        <p
          v-if="years.length > 1 && currentYear !== years[0]"
          class="text-theme-base text-gray-400 font-semibold flex flex-row items-center gap-2 px-4 mb-2"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            width="16"
            height="17"
            viewBox="0 0 24 25"
            aria-hidden="true"
            class="text-gray-600 flex-shrink-0 relative -top-px"
          >
            <g transform="translate(0 1)">
              <path d="M12,0A12,12,0,1,1,0,12,12,12,0,0,1,12,0Z" fill="none" />
              <g transform="translate(0 15.48) rotate(-45)">
                <path d="M0,0H18.789" transform="translate(0 4.311)" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="3" />
                <path d="M0,0,4.359,4.359,0,8.719" transform="translate(14.705)" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="3" />
              </g>
            </g>
          </svg>
          <span>{{ trendLabel }}</span>
        </p>

        <template
          v-for="indicator in indicators"
          :key="indicator.id"
        >
          <IndicatorBig
            v-if="(indicator.type === 'big' || indicator.indicator_type === 'big' || indicator.indikatortyp === 'groß') && !lottieUrl && !imageUrl"
            :indicator="indicator"
            :current-year="currentYear"
          />
        </template>

        <img
          v-if="imageUrl"
          class="mx-auto h-[216px] object-contain"
          height="216"
          width="363"
          :src="imageUrl"
          :alt="header"
          loading="lazy"
          @error="onImageError"
        >

        <div
          v-if="lottieUrl"
          ref="target"
          v-intersection-observer="onIntersectionObserver"
          class="text-center"
        >
          <dotlottie-player
            ref="lottiePlayer"
            autoplay="true"
            loop="true"
            class="mx-auto h-[216px]"
          />
        </div>
      </div>

      <div
        class="py-6 px-4"
        :style="{ backgroundColor: 'var(--card-background-color, #FFFFFF)' }"
      >
        <template
          v-for="indicator in indicators"
          :key="indicator.id"
        >
          <IndicatorSmall
            v-if="indicator.type === 'small' || indicator.indikatortyp === 'normal' || lottieUrl || imageUrl"
            :indicator="indicator"
            :current-year="currentYear"
          />
        </template>

        <div
          v-if="years.length > 0"
          class="max-w-[214px] mx-auto my-4"
        >
          <Tooltip
            :text="getYearSliderTooltip()"
            position="top"
            wrapper-class="w-full"
            trigger-class="w-full"
            :disabled="isSliderInteracting"
          >
            <div
              class="w-full"
              @mousedown="handleSliderInteractionStart"
              @mouseup="handleSliderInteractionEnd"
              @mouseleave="handleSliderInteractionEnd"
              @touchstart="handleSliderInteractionStart"
              @touchend="handleSliderInteractionEnd"
            >
              <VueSlider
                v-model="currentYear"
                :data="years"
                :tooltip="'none'"
                :dot-attrs="{ 'aria-label': 'Select year' }"
              />
            </div>
          </Tooltip>
        </div>
        <div
          v-if="years.length > 0"
          class="text-black font-bold text-center text-lg mb-4"
        >
          {{ periodLabelMap[currentYear] || currentYear }}
        </div>

        <div
          v-if="hint"
          class="text-sm italic text-gray-400 mt-2.5"
        >
          {{ hint }}
        </div>

        <div
          v-if="footnote"
          class="text-sm text-gray-300"
        >
          {{ footnote }}
        </div>

        <div
          v-if="!isIframe"
          class="flex justify-end mt-2.5 space-x-2.5 items-center"
        >
          <Tooltip
            v-if="infoButtonVisible"
            :text="getInfoButtonTooltip()"
            position="left"
          >
            <button
              class="rounded-full shrink-0 w-9 h-9 text-xl font-bold hover:shadow-info transition-shadow duration-200"
              :style="{ color: brandingStore.primaryColor }"
              aria-label="Info"
              @click="toggleOverlay"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="36"
                height="36"
                viewBox="0 0 36 36"
                fill="none"
              >
                <path
                  d="M18 0.5C27.665 0.5 35.5 8.33502 35.5 18C35.5 27.665 27.665 35.5 18 35.5C8.33502 35.5 0.5 27.665 0.5 18C0.5 8.33502 8.33502 0.5 18 0.5Z"
                  fill="currentColor"
                  stroke="#191919"
                />
                <path
                  d="M18 35C27.3888 35 35 27.3888 35 18C35 8.61116 27.3888 1 18 1C8.61116 1 1 8.61116 1 18C1 27.3888 8.61116 35 18 35Z"
                  stroke="#191919"
                  stroke-width="2"
                />
                <path
                  d="M18.3137 29.313V6.68556"
                  stroke="white"
                  stroke-width="2"
                />
                <path
                  d="M7.00148 18.0005H29.6289"
                  stroke="white"
                  stroke-width="2"
                />
              </svg>
            </button>
          </Tooltip>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// Demo City tenant override of TileCard.vue (theming PoC, Approach A -
// component-override registry). Based on the default TileCard; the
// restructuring this override demonstrates (trend moved next to the value,
// badge added) cannot be expressed through color/font tokens alone.
import { ref, computed, onMounted } from 'vue';
import { DotLottiePlayer } from '@johanaarstein/dotlottie-player';
import VueSlider from 'vue-slider-component/lib/vue-slider.vue';
import 'vue-slider-component/theme/default.css';
import IndicatorBig from '../../components/cards/indicators/IndicatorBig.vue';
import IndicatorSmall from '../../components/cards/indicators/IndicatorSmall.vue';
import Tooltip from '../../components/help/Tooltip.vue';
import { useFilterStore } from '../../stores/filter';
import { useOverlayStore } from '../../stores/overlay';
import { useBrandingStore } from '../../stores/branding';
import { useTenantStore } from '../../stores/tenant';
import { useLocale } from '../../composables/useLocale';
import { useHelpContext } from '../../composables/useHelpContext';
import { blockHasVisibleContent } from '../../utils/jumpMarks';

const props = defineProps({
    tile: {
        type: Object,
        required: true,
    },
    isIframe: {
        type: Boolean,
        default: false,
    },
});

const filterStore = useFilterStore();
const overlayStore = useOverlayStore();
const brandingStore = useBrandingStore();
const tenantStore = useTenantStore();
const { getTooltip } = useHelpContext();
const { currentLocale } = useLocale();

const header = computed(() => {
    const source = props.tile.title;
    if (typeof source === 'string') return source.trim();
    if (source && typeof source === 'object') {
        const resolved =
            source[currentLocale.value] ??
            source.de ??
            Object.values(source).find((v) => typeof v === 'string' && v.trim().length > 0) ??
            '';
        return typeof resolved === 'string' ? resolved.trim() : '';
    }
    return '';
});

// Trend label based on tile's time_granularity
const trendLabels = {
    de: { year: 'Veränderung zum Vorjahr', quarter: 'Veränderung zum Vorquartal', month: 'Veränderung zum Vormonat', week: 'Veränderung zur Vorwoche', day: 'Veränderung zum Vortag' },
    en: { year: 'Change from previous year', quarter: 'Change from previous quarter', month: 'Change from previous month', week: 'Change from previous week', day: 'Change from previous day' },
};
const trendLabel = computed(() => {
    const granularity = props.tile.time_granularity || 'year';
    const locale = currentLocale.value === 'en' ? 'en' : 'de';
    return trendLabels[locale]?.[granularity] || trendLabels.de.year;
});

const subheader = computed(() => {
    const desc = props.tile.description?.[currentLocale.value] || props.tile.description?.de || props.tile.description || '';
    // Strip HTML tags since description comes from a rich text editor
    const plainText = desc.replace(/<[^>]*>/g, '').trim();
    // Extract first sentence or first 100 chars as subheader
    const firstSentence = plainText.split('.')[0];
    return firstSentence.length > 100 ? firstSentence.slice(0, 100) + '…' : firstSentence;
});

const hint = computed(() => {
    const source = props.tile.hint;
    if (typeof source === 'string') return source.trim();
    if (source && typeof source === 'object') {
        const resolved =
            source[currentLocale.value] ??
            source.de ??
            Object.values(source).find((v) => typeof v === 'string' && v.trim().length > 0) ??
            '';
        return typeof resolved === 'string' ? resolved.trim() : '';
    }
    return '';
});

const footnote = computed(() => {
    return null;
});

const tileColor = computed(() => {
    if (props.tile.tile_color) {
        return props.tile.tile_color;
    }
    const groupKey = brandingStore.tileColorSourceGroupKey;
    if (!groupKey) return null;
    const categories = props.tile.categories || [];
    const colorCategory = categories.find(category => category.group?.key === groupKey && category.color);
    return colorCategory?.color || null;
});

const backgroundClass = computed(() => {
    if (tileColor.value) {
        return null;
    }
    return 'bg-gray-100';
});

const backgroundColorStyle = computed(() => {
    if (tileColor.value) {
        return { backgroundColor: tileColor.value };
    }
    return {};
});

// Demo City structural look: square corners + a red border. --card-radius is
// scoped to 0 locally (matches the site-wide default, kept explicit here to
// show the override can set its own value independent of BrandingSettings);
// --card-border-color is Demo City-specific styling, not a generic branding
// token, so it lives here rather than in the Settings/API channel.
const cardContainerStyle = {
    borderRadius: 'var(--card-radius, 0)',
    borderWidth: 'var(--card-border-width, 3px)',
    borderStyle: 'solid',
    borderColor: 'var(--card-border-color, #1465A4)',
    overflow: 'hidden',
};

const shouldShow = computed(() => {
    if (!filterStore.level2Filter?.key) {
        return true;
    }

    const filterType = filterStore.level1Filter;
    const filterKey = filterStore.level2Filter.key;

    const categories = props.tile.categories || [];
    if (Array.isArray(categories) && filterType) {
        return categories.some((category) => {
            if (category.group?.key !== filterType) {
                return false;
            }
            const categoryKey = category.id?.toString() || category.key;
            return categoryKey === filterKey || category.key === filterKey;
        });
    }

    return true;
});

const indicators = ref([]);
const years = ref([]);
const currentYear = ref(null);
const periodLabelMap = ref({});
const imageUrl = ref(null);
const lottieUrl = ref(null);
const lottiePlayer = ref(null);
const intersected = ref(false);
const infoButtonVisible = computed(
    () => (props.tile.background_blocks || []).some(blockHasVisibleContent),
);
const isSliderInteracting = ref(false);

// Process indicators from metric definitions (new API structure) - identical
// to the default TileCard so the Demo City override renders real tile data.
onMounted(() => {
    if (props.tile.metric_definitions && Array.isArray(props.tile.metric_definitions)) {
        const periodsSet = new Set();
        const labels = {};

        props.tile.metric_definitions
            .filter((definition) => definition?.is_active !== false)
            .forEach((definition) => {
            if (definition.values && Array.isArray(definition.values)) {
                definition.values
                    .filter((valueData) => valueData?.is_active !== false)
                    .forEach((valueData) => {
                    const key = valueData.period_key?.toString() || valueData.year?.toString() || '';
                    if (key) {
                        periodsSet.add(key);
                        if (valueData.label) {
                            labels[key] = valueData.label;
                        }
                    }
                });
            }
        });

        years.value = Array.from(periodsSet).sort((a, b) => a.localeCompare(b));
        periodLabelMap.value = labels;
        if (years.value.length > 0) {
            currentYear.value = years.value[years.value.length - 1];
        }

        props.tile.metric_definitions
            .filter((definition) => definition?.is_active !== false)
            .forEach((definition) => {
            const label = definition.label?.[currentLocale.value] || definition.label || '';
            const labelEn = definition.label?.en || '';

            let unitValue = '';
            let unitEnValue = '';
            if (Array.isArray(definition.unit)) {
                unitValue = '';
                unitEnValue = '';
            } else if (typeof definition.unit === 'object' && definition.unit !== null) {
                unitValue = definition.unit[currentLocale.value] || definition.unit.de || '';
                unitEnValue = definition.unit.en || '';
            } else {
                unitValue = definition.unit || '';
                unitEnValue = definition.unit || '';
            }

            const indicatorType = definition.indicator_type || 'small';

            const sortedYears = {};
            const yearsArray = [];

            if (definition.values && Array.isArray(definition.values)) {
                definition.values
                    .filter((valueData) => valueData?.is_active !== false)
                    .forEach((valueData) => {
                    const key = valueData.period_key?.toString() || valueData.year?.toString() || '';
                    if (key) {
                        sortedYears[key] = {
                            title: valueData.label || labels[key] || key,
                            value: valueData.value,
                        };
                        yearsArray.push({
                            year: key,
                            title: valueData.label || labels[key] || key,
                            value: valueData.value,
                        });
                    }
                });
            }

            const indicator = {
                id: definition.metric_key,
                title: label,
                title_en: labelEn,
                unit: unitValue,
                unit_en: unitEnValue,
                type: indicatorType,
                indicator_type: indicatorType,
                indikatortyp: indicatorType === 'big' ? 'groß' : 'normal',
                years: yearsArray,
                sortedYears: sortedYears,
                show_arrow: definition.show_arrow || false,
                icon: definition.icon || null,
            };

            indicators.value.push(indicator);
        });
    }

    if (props.tile.icon) {
        const url = props.tile.icon;
        if (url.endsWith('.lottie')) {
            lottieUrl.value = url;
        } else {
            imageUrl.value = url;
        }
    }
});

function onImageError() {
    imageUrl.value = null;
}

function onIntersectionObserver([{ isIntersecting }]) {
    if (isIntersecting && !intersected.value) {
        intersected.value = true;
        if (lottieUrl.value && lottiePlayer.value) {
            lottiePlayer.value.load(lottieUrl.value);
        }
    }
}

function toggleOverlay() {
    overlayStore.openOverlay(props.tile);
}

function getInfoButtonTooltip() {
    return getTooltip('tileInfoButton') || (currentLocale.value === 'en' ? 'Shows additional information about this tile' : 'Zeigt weitere Informationen zu dieser Kachel');
}

function getYearSliderTooltip() {
    return getTooltip('yearSlider') || (currentLocale.value === 'en' ? 'Select a year to display values for that year' : 'Wählen Sie ein Jahr aus, um die Werte für dieses Jahr anzuzeigen');
}

function handleSliderInteractionStart() {
    isSliderInteracting.value = true;
}

function handleSliderInteractionEnd() {
    setTimeout(() => {
        isSliderInteracting.value = false;
    }, 100);
}
</script>

<style scoped>
/* See TileCard.vue for the column-width rationale - mirrored here
   so the Demo City override sizes identically inside the waterfall grid. */
.tile-waterfall-item {
    width: 100%;
}

@media (min-width: 826px) {
    .tile-waterfall-item {
        width: calc((100% - (var(--waterfall-col-tablet, 2) - 1) * 40px) / var(--waterfall-col-tablet, 2));
    }
}

@media (min-width: 1281px) {
    .tile-waterfall-item {
        width: calc((100% - (var(--waterfall-col-desktop, 3) - 1) * 40px) / var(--waterfall-col-desktop, 3));
    }
}

/* Demo City look: full-width blue title bar with white bold heading. */
.demo-city-title-bar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    background-color: #1465A4;
    padding: 1rem;
}
.demo-city-title-text {
    color: #FFFFFF;
    font-weight: 800;
    font-size: 1.35rem;
    line-height: 1.12;
    letter-spacing: -0.01em;
}
/* Inverted tag inside the blue bar: white block, blue caps, kantig. */
.demo-city-badge {
    background-color: #FFFFFF;
    color: #1465A4;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.5rem;
    border-radius: 0;
    align-self: flex-start;
}

:deep(.vue-slider-rail),
:deep(.vue-slider-process) {
    background: var(--slider-rail-color, #191919);
}

:deep(.vue-slider-dot) {
    width: 32px !important;
    height: 32px !important;
}

:deep(.vue-slider-dot-handle) {
    box-shadow: none;
    background: var(--slider-handle-color, var(--accent-color, #E30613));
    border: 2px solid var(--slider-handle-border-color, #191919);
}

:deep(.vue-slider-dot-handle-focus) {
    box-shadow: 0 1px 10px #707070;
}
</style>
