<template>
    <div
        v-show="shouldShow"
        class="max-w-[363px] block hyphens-auto"
        :class="{ 'pb-5 md:pb-10': !isIframe }"
    >
        <div class="shadow-card">
            <div :class="backgroundClass ? [backgroundClass] : []" :style="backgroundColorStyle" class="py-6 text-black relative">
                <div class="flex flex-row justify-between gap-2 px-4">
                    <div class="text-3xl font-bold mb-4 hyphens-auto">{{ header }}</div>
                </div>

                <div v-if="subheader" class="text-lg font-bold px-4">{{ subheader }}</div>

                <template v-for="indicator in indicators" :key="indicator.id">
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
                />

                <div v-if="lottieUrl" class="text-center" ref="target" v-intersection-observer="onIntersectionObserver">
                    <dotlottie-player
                        ref="lottiePlayer"
                        autoplay="true"
                        loop="true"
                        class="mx-auto h-[216px]"
                    />
                </div>
            </div>

            <div class="py-6 px-4" :style="{ backgroundColor: 'var(--card-background-color, #FFFFFF)' }">
                <template v-for="indicator in indicators" :key="indicator.id">
                    <IndicatorSmall
                        v-if="indicator.type === 'small' || indicator.indikatortyp === 'normal' || lottieUrl || imageUrl"
                        :indicator="indicator"
                        :current-year="currentYear"
                    />
                </template>

                <div v-if="years.length > 0" class="max-w-[214px] mx-auto my-4">
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
                                :data="years"
                                v-model="currentYear"
                                :tooltip="'none'"
                                :dot-attrs="{ 'aria-label': 'Select year' }"
                            />
                        </div>
                    </Tooltip>
                </div>
                <div v-if="years.length > 0" class="text-black font-bold text-center text-lg mb-4">
                    {{ currentYear }}
                </div>

                <div v-if="footnote" class="text-sm text-gray-300">{{ footnote }}</div>

                <div v-if="!isIframe" class="flex justify-end mt-2.5 space-x-2.5">
                    <Tooltip
                        v-if="infoButtonVisible"
                        :text="getInfoButtonTooltip()"
                        position="left"
                    >
                        <button
                            @click="toggleOverlay"
                            class="rounded-full shrink-0 w-9 h-9 text-xl font-bold hover:shadow-info transition-shadow duration-200"
                            :style="{ color: brandingStore.primaryColor }"
                            aria-label="Info"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                width="36"
                                height="36"
                                viewBox="0 0 36 36"
                                fill="none"
                            >
                                <path d="M18 0.5C27.665 0.5 35.5 8.33502 35.5 18C35.5 27.665 27.665 35.5 18 35.5C8.33502 35.5 0.5 27.665 0.5 18C0.5 8.33502 8.33502 0.5 18 0.5Z" fill="currentColor" stroke="#191919"/>
                                <path d="M18 35C27.3888 35 35 27.3888 35 18C35 8.61116 27.3888 1 18 1C8.61116 1 1 8.61116 1 18C1 27.3888 8.61116 35 18 35Z" stroke="#191919" stroke-width="2"/>
                                <path d="M18.3137 29.313V6.68556" stroke="white" stroke-width="2"/>
                                <path d="M7.00148 18.0005H29.6289" stroke="white" stroke-width="2"/>
                            </svg>
                        </button>
                    </Tooltip>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { DotLottiePlayer } from '@johanaarstein/dotlottie-player';
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
    return (
        props.tile.title?.[currentLocale.value] ||
        props.tile.title?.de ||
        props.tile.title ||
        ''
    );
});

const subheader = computed(() => {
    const desc = props.tile.description?.[currentLocale.value] || props.tile.description?.de || props.tile.description || '';
    // Extract first sentence or first 100 chars as subheader
    const firstSentence = desc.split('.')[0];
    return firstSentence.length > 100 ? firstSentence.slice(0, 100) + '…' : firstSentence;
});

const footnote = computed(() => {
    // Footnote is not part of the tile API yet.
    return null;
});

const backgroundClass = computed(() => {
    // Verwende color direkt aus handlungsdimension, falls vorhanden
    if (props.tile.handlungsdimension?.color) {
        // Keine Klasse, verwende inline style
        return null;
    }
    
    // Fallback: Wenn keine color vorhanden, verwende Standard-Grau
    return 'bg-gray-100';
});

const backgroundColorStyle = computed(() => {
    // Verwende color direkt aus handlungsdimension, falls vorhanden
    if (props.tile.handlungsdimension?.color) {
        return { backgroundColor: props.tile.handlungsdimension.color };
    }
    return {};
});

const shouldShow = computed(() => {
    if (!filterStore.level2Filter?.key) {
        return true;
    }
    
    const filterType = filterStore.level1Filter; // 'dimensions' | 'fields' | 'sdg' | null
    const filterKey = filterStore.level2Filter.key;
    const filters = [];
    
    // Dimensions filter: check handlungsdimension
    if (filterType === 'dimensions') {
        const dimension = props.tile.handlungsdimension;
        if (dimension) {
            const dimensionKey = typeof dimension === 'string' 
                ? dimension 
                : (dimension.key || dimension.id?.toString() || null);
            if (dimensionKey) {
                filters.push(String(dimensionKey));
            }
        }
        return filters.includes(filterKey);
    }
    
    // Fields filter: check handlungsfelder (categories)
    if (filterType === 'fields' || !filterType) {
        const categories = props.tile.handlungsfelder || props.tile.categories || [];
        
        categories.forEach((cat) => {
            if (cat.id) {
                filters.push(cat.id.toString());
            }
        });
        return filters.includes(filterKey);
    }
    
    // SDG filter: check sdg_ziele
    if (filterType === 'sdg') {
        const sdgZiele = props.tile.sdg_ziele || [];
        sdgZiele.forEach((sdg) => {
            if (sdg.id) {
                filters.push(sdg.id.toString());
            }
        });
        return filters.includes(filterKey);
    }
    
    return true;
});

const indicators = ref([]);
const years = ref([]);
const currentYear = ref(null);
const imageUrl = ref(null);
const lottieUrl = ref(null);
const lottiePlayer = ref(null);
const intersected = ref(false);
const infoButtonVisible = ref(false);
const isSliderInteracting = ref(false);

// Process indicators from metric definitions (new API structure)
onMounted(() => {
    // New API structure: tile.metric_definitions[] -> each definition has values[]
    if (props.tile.metric_definitions && Array.isArray(props.tile.metric_definitions)) {
        // Collect all unique years from metric values
        const yearsSet = new Set();
        
        props.tile.metric_definitions.forEach((definition) => {
            if (definition.values && Array.isArray(definition.values)) {
                definition.values.forEach((valueData) => {
                    const yearStr = valueData.year?.toString() || valueData.year;
                    if (yearStr) {
                        yearsSet.add(yearStr);
                    }
                });
            }
        });

        // Convert set to array and sort
        years.value = Array.from(yearsSet).sort((a, b) => parseInt(a) - parseInt(b));
        if (years.value.length > 0) {
            currentYear.value = years.value[years.value.length - 1];
        }

        // Build indicators from metric definitions
        props.tile.metric_definitions.forEach((definition) => {
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
                definition.values.forEach((valueData) => {
                    const yearStr = valueData.year?.toString() || valueData.year;
                    if (yearStr) {
                        sortedYears[yearStr] = {
                            title: yearStr,
                            value: valueData.value,
                        };
                        yearsArray.push({
                            year: yearStr,
                            title: yearStr,
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

    // Check if overlay should be available (if tile has background content or additional data)
    if (props.tile.background_blocks?.length > 0) {
        infoButtonVisible.value = true;
    }
});

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
    background: var(--slider-handle-color, #E30613);
    border: 2px solid var(--slider-handle-border-color, #191919);
}

:deep(.vue-slider-dot-handle-focus) {
    box-shadow: 0 1px 10px #707070;
}
</style>
