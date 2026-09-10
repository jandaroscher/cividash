<template>
  <div
    v-show="shouldShow"
    class="tile-waterfall-item block hyphens-auto"
    :class="{ 'pb-5 md:pb-10': !isIframe }"
  >
    <div
      class="shadow-card"
      :style="cardContainerStyle"
    >
      <div
        :class="backgroundClass ? [backgroundClass] : []"
        :style="backgroundColorStyle"
        class="py-6 text-black relative"
      >
        <div class="flex flex-row justify-between gap-2 px-4">
          <div class="text-theme-h3 font-bold mb-4 hyphens-auto">
            {{ header }}
          </div>
          <!-- Approach B: additive region, renders nothing unless a
               tenant slot override provides content (e.g. a badge like the
               Demo City Approach-A override adds via its own template). -->
          <slot name="badge" />
        </div>

        <div
          v-if="subheader"
          class="text-lg font-bold px-4"
        >
          {{ subheader }}
        </div>
        <!-- Named slot, default content = today's markup,
             so no override means pixel-identical output. -->
        <slot name="value">
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
        </slot>

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
        <!-- Separate name from #value above: both regions can
             render for the same tile (a big + a small indicator at once),
             so one slot override must not duplicate into both spots. -->
        <slot name="value-small">
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
        </slot>

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

        <!-- Named slot, default content = today's trend arrow +
             label markup, so no override renders identically. -->
        <slot name="trend">
          <p
            v-if="years.length > 1 && currentYear !== years[0]"
            class="text-theme-base text-gray-400 font-semibold flex flex-row items-center gap-2 justify-end text-sm"
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
                <path
                  d="M12,0A12,12,0,1,1,0,12,12,12,0,0,1,12,0Z"
                  fill="none"
                />
                <g transform="translate(0 15.48) rotate(-45)">
                  <path
                    d="M0,0H18.789"
                    transform="translate(0 4.311)"
                    fill="none"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-width="3"
                  />
                  <path
                    d="M0,0,4.359,4.359,0,8.719"
                    transform="translate(14.705)"
                    fill="none"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-width="3"
                  />
                </g>
              </g>
            </svg>
            <span>{{ trendLabel }}</span>
          </p>
        </slot>

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
import { ref, computed, onMounted } from 'vue';
import '@johanaarstein/dotlottie-player';
import VueSlider from 'vue-slider-component/lib/vue-slider.vue';
import 'vue-slider-component/theme/default.css';
import IndicatorBig from './cards/indicators/IndicatorBig.vue';
import IndicatorSmall from './cards/indicators/IndicatorSmall.vue';
import Tooltip from './help/Tooltip.vue';
import { useFilterStore } from '../stores/filter';
import { useOverlayStore } from '../stores/overlay';
import { useBrandingStore } from '../stores/branding';
import { useLocale } from '../composables/useLocale';
import { useHelpContext } from '../composables/useHelpContext';
import { blockHasVisibleContent } from '../utils/jumpMarks';

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
    // First sentence: a period ends it only at the end of the text or when the
    // next word starts with an uppercase letter or digit, and not after a word
    // of 1-3 letters ("Dr.", "z. B.", "ca."), so abbreviations and stray
    // periods ("Reduzierung. der ...") do not cut the text.
    const firstSentence = plainText.split(/(?<!(?:^|[^\p{L}])\p{L}{1,3})\.(?:\s+(?=[A-ZÄÖÜ0-9„"(])|$)/u)[0];
    return firstSentence.length > 200 ? firstSentence.slice(0, 200) + '…' : firstSentence;
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
    // Footnote is not part of the tile API yet.
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

// Structural card tokens: defaults (0)
// match the pre-existing look, so untouched tenants render unchanged.
// overflow: hidden is only needed to clip descendants to a non-zero radius;
// applying it unconditionally would clip descendants for every tenant even
// when the radius is 0, changing behavior for no visual benefit.
//
// cardRadius is a free-form CSS-length string (e.g. "0", "0px", "calc(0px)"),
// so a naive parseFloat() misreads non-numeric-prefixed values like calc(0px)
// as NaN !== 0. Let the browser resolve the value instead: apply it to a
// detached probe element and read the computed pixel result back.
let radiusProbe = null;

function isZeroCssLength(value) {
    if (!value) return true;
    if (typeof document === 'undefined') return false;

    if (!radiusProbe) {
        radiusProbe = document.createElement('div');
        radiusProbe.style.position = 'absolute';
        radiusProbe.style.visibility = 'hidden';
        radiusProbe.style.pointerEvents = 'none';
        document.body.appendChild(radiusProbe);
    }

    radiusProbe.style.borderRadius = value;
    const resolved = getComputedStyle(radiusProbe).borderRadius;

    return parseFloat(resolved) === 0;
}

const cardContainerStyle = computed(() => ({
    borderRadius: 'var(--card-radius, 0)',
    borderWidth: 'var(--card-border-width, 0)',
    borderStyle: 'solid',
    borderColor: 'var(--card-border-color, transparent)',
    overflow: isZeroCssLength(brandingStore.cardRadius) ? 'visible' : 'hidden',
}));

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
// The info/"+" button opens the tile's background-page overlay, so it should
// only be shown when at least one background block actually renders visible
// content - otherwise the button is clickable but the overlay is empty.
const infoButtonVisible = computed(
    () => (props.tile.background_blocks || []).some(blockHasVisibleContent),
);
const isSliderInteracting = ref(false);

// Process indicators from metric definitions (new API structure)
onMounted(() => {
    // New API structure: tile.metric_definitions[] -> each definition has values[]
    if (props.tile.metric_definitions && Array.isArray(props.tile.metric_definitions)) {
        // Collect all unique period keys from metric values
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

        // Convert set to array and sort (all period_key formats are lexicographically sortable)
        years.value = Array.from(periodsSet).sort((a, b) => a.localeCompare(b));
        periodLabelMap.value = labels;
        if (years.value.length > 0) {
            currentYear.value = years.value[years.value.length - 1];
        }

        // Build indicators from metric definitions
        props.tile.metric_definitions
            .filter((definition) => definition?.is_active !== false)
            .forEach((definition) => {
            const label = definition.label?.[currentLocale.value] || definition.label || '';
            const labelEn = definition.label?.en || '';
            
            // Handle unit - can be string, object, or empty array
            let unitValue = '';
            let unitEnValue = '';
            if (Array.isArray(definition.unit)) {
                // Empty array - no unit
                unitValue = '';
                unitEnValue = '';
            } else if (typeof definition.unit === 'object' && definition.unit !== null) {
                // Object with translations
                unitValue = definition.unit[currentLocale.value] || definition.unit.de || '';
                unitEnValue = definition.unit.en || '';
            } else {
                // String
                unitValue = definition.unit || '';
                unitEnValue = definition.unit || '';
            }
            
            // Determine indicator type from API field
            const indicatorType = definition.indicator_type || 'small';
            
            // Build sortedYears object from values
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
                id: definition.metric_key, // Use metric_key as stable ID
                title: label,
                title_en: labelEn,
                unit: unitValue,
                unit_en: unitEnValue,
                type: indicatorType,
                indicator_type: indicatorType,
                indikatortyp: indicatorType === 'big' ? 'groß' : 'normal',
                years: yearsArray, // Keep for compatibility
                sortedYears: sortedYears, // Object with years as keys (like reference app)
                show_arrow: definition.show_arrow || false,
                icon: definition.icon || null, // Icon URL from API (already full URL)
            };
            
            indicators.value.push(indicator);
        });
    }

    // Check for tile image/lottie
    if (props.tile.icon) {
        const url = props.tile.icon;
        if (url.endsWith('.lottie')) {
            lottieUrl.value = url;
        } else {
            imageUrl.value = url;
        }
    }
});

// Gracefully degrade when the tile icon/image fails to load (e.g. broken or
// missing file on the backend) instead of showing the browser's broken-image
// placeholder with visible alt text. Clearing imageUrl lets the
// existing v-if fallbacks (e.g. IndicatorBig) take over.
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
    // Small delay to prevent tooltip from showing immediately after interaction
    setTimeout(() => {
        isSliderInteracting.value = false;
    }, 100);
}

</script>

<style scoped>
/*
 * Tiles use the fixed design card width of 363px (matching the
 * reference at zukunft.regensburg.de). The grid fits as many 363px columns as
 * the container allows (3 at desktop where .container maxes at 1230/inner 1170
 * = 3×363 + 2×40 gap; 2 on tablet; 1 on mobile), controlled by Cards.vue's
 * `:col` / `break-at`. A fixed width (not `max-w`, not a container fraction)
 * keeps tiles at 363px regardless of content amount or result count, so sparse
 * rows stay left-aligned at the standard size instead of shrinking or being
 * stretched to fill the row.
 */
.tile-waterfall-item {
    width: 100%; /* <=825px: single column, wrapper below already caps + centers at 363px */
}

@media (min-width: 826px) {
    .tile-waterfall-item {
        width: 363px;
    }
}

/* Vue slider styles - use :deep() to style child components */
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
