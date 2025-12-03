import { ref, computed, watch, onMounted } from 'vue';
import { useLocale } from './useLocale';

/**
 * Composable for shared indicator logic used by IndicatorBig and IndicatorSmall components.
 * 
 * @param {Object} indicator - The indicator object with years/sortedYears data
 * @param {String|Number} currentYear - The current year to display
 * @returns {Object} Object containing all computed properties, refs, functions, and watchers
 */
export function useIndicator(indicator, currentYear) {
    const { currentLocale } = useLocale();

    // Reactive state
    const lottiePlayer = ref(null);
    const intersected = ref(false);
    const lottieUrl = ref(null);
    const imageUrl = ref(null);
    const title = ref('');
    const unit = ref('');
    const arrow = ref(false);

    // Normalize currentYear to number for comparison
    const currentYearNum = computed(() => parseInt(currentYear.value) || 0);
    const currentYearStr = computed(() => currentYear.value?.toString() || '');

    // Arrow URLs with SSR fallback
    const arrowUpUrl = computed(() => {
        const origin = typeof window !== 'undefined' ? window.location.origin : '';
        return origin ? new URL('/assets/images/arrow-up.svg', origin).href : '/assets/images/arrow-up.svg';
    });
    const arrowStraightUrl = computed(() => {
        const origin = typeof window !== 'undefined' ? window.location.origin : '';
        return origin ? new URL('/assets/images/arrow-straight.svg', origin).href : '/assets/images/arrow-straight.svg';
    });
    const arrowDownUrl = computed(() => {
        const origin = typeof window !== 'undefined' ? window.location.origin : '';
        return origin ? new URL('/assets/images/arrow-down.svg', origin).href : '/assets/images/arrow-down.svg';
    });

    // Use sortedYears object like reference app, fallback to years array
    const sortedKeys = computed(() => {
        if (indicator.value?.sortedYears) {
            return Object.keys(indicator.value.sortedYears).sort((a, b) => parseInt(a) - parseInt(b));
        }
        if (indicator.value?.years) {
            return indicator.value.years.map(y => y.year || y.title).sort((a, b) => parseInt(a) - parseInt(b));
        }
        return [];
    });

    const indicatorValue = computed(() => {
        if (!currentYear.value) {
            return null;
        }
        const yearStr = currentYear.value.toString();
        
        // Try sortedYears object first (like reference app)
        if (indicator.value?.sortedYears?.[yearStr]) {
            return indicator.value.sortedYears[yearStr].value;
        }
        
        // Fallback to years array
        if (indicator.value?.years) {
            const yearData = indicator.value.years.find(
                (y) => (y.year?.toString() || y.year) === yearStr || (y.title?.toString() || y.title) === yearStr
            );
            return yearData?.value || null;
        }
        
        return null;
    });

    const formattedValue = computed(() => {
        if (!indicatorValue.value) return '-';
        return Number(indicatorValue.value).toLocaleString(currentLocale.value);
    });

    function setText() {
        if (currentLocale.value === 'en') {
            title.value = indicator.value.title_en || indicator.value.title || '';
            unit.value = indicator.value.unit_en || indicator.value.unit || '';
        } else {
            title.value = indicator.value.title || '';
            unit.value = indicator.value.unit || '';
        }
    }

    const arrowType = computed(() => {
        const keys = sortedKeys.value;
        if (keys.length < 2) {
            return 'normal';
        }

        const currentIndex = keys.findIndex(key => parseInt(key) === currentYearNum.value);

        if (currentIndex === 0 || currentIndex === -1) {
            return 'normal';
        }

        // Use sortedYears object if available, otherwise use years array
        let currentValue, previousValue;
        
        if (indicator.value?.sortedYears) {
            const currentYearData = indicator.value.sortedYears[currentYearStr.value];
            const previousYearData = indicator.value.sortedYears[keys[currentIndex - 1]];
            
            if (!currentYearData || !previousYearData) {
                return 'normal';
            }
            
            // Handle value - can be string or number
            const currentVal = currentYearData.value;
            const previousVal = previousYearData.value;
            
            currentValue = typeof currentVal === 'string' ? parseFloat(currentVal.replace(/[^\d.-]/g, '')) : parseFloat(currentVal);
            previousValue = typeof previousVal === 'string' ? parseFloat(previousVal.replace(/[^\d.-]/g, '')) : parseFloat(previousVal);
        } else if (indicator.value?.years) {
            const currentYearData = indicator.value.years.find(
                (y) => (y.year?.toString() || y.year) === currentYearStr.value || (y.title?.toString() || y.title) === currentYearStr.value
            );
            const previousYearStr = keys[currentIndex - 1];
            const previousYearData = indicator.value.years.find(
                (y) => (y.year?.toString() || y.year) === previousYearStr || (y.title?.toString() || y.title) === previousYearStr
            );
            
            if (!currentYearData || !previousYearData) {
                return 'normal';
            }
            
            // Handle value - can be string or number
            const currentVal = currentYearData.value;
            const previousVal = previousYearData.value;
            
            currentValue = typeof currentVal === 'string' ? parseFloat(currentVal.replace(/[^\d.-]/g, '')) : parseFloat(currentVal);
            previousValue = typeof previousVal === 'string' ? parseFloat(previousVal.replace(/[^\d.-]/g, '')) : parseFloat(previousVal);
        } else {
            return 'normal';
        }

        if (isNaN(currentValue) || isNaN(previousValue)) {
            return 'normal';
        }

        if (currentValue > previousValue) return 'up';
        if (currentValue < previousValue) return 'down';
        return 'normal';
    });

    function onIntersectionObserver([{ isIntersecting }]) {
        if (isIntersecting && !intersected.value) {
            intersected.value = true;

            if (lottieUrl.value && lottiePlayer.value) {
                lottiePlayer.value.load(lottieUrl.value);
            }
        }
    }

    function updateArrowVisibility() {
        // Show arrows if explicitly set OR if there are multiple years AND current year is not the first year
        const keys = sortedKeys.value;
        const hasMultipleYears = keys.length > 1;
        const currentIndex = keys.findIndex(key => parseInt(key) === currentYearNum.value);
        const hasPreviousYear = currentIndex > 0;
        
        if (indicator.value.show_arrow === true || indicator.value.pfeilausgabe === 'ja' || (hasMultipleYears && hasPreviousYear)) {
            arrow.value = true;
        } else {
            arrow.value = false;
        }
    }

    // Setup on mount
    onMounted(() => {
        setText();
        updateArrowVisibility();

        // Check for image/lottie icon
        if (indicator.value.icon) {
            const iconUrl = indicator.value.icon;
            
            // Icon URL is already a full URL from API
            if (typeof iconUrl === 'string' && iconUrl.endsWith('.lottie')) {
                lottieUrl.value = iconUrl;
            } else {
                imageUrl.value = iconUrl;
            }
        }
    });

    // Watch for currentYear changes
    watch(
        currentYear,
        () => {
            updateArrowVisibility();
        }
    );

    // Watch for locale changes
    watch(
        () => currentLocale.value,
        () => {
            setText();
        }
    );

    return {
        // Refs
        lottiePlayer,
        intersected,
        lottieUrl,
        imageUrl,
        title,
        unit,
        arrow,
        
        // Computed
        currentYearNum,
        currentYearStr,
        arrowUpUrl,
        arrowStraightUrl,
        arrowDownUrl,
        sortedKeys,
        indicatorValue,
        formattedValue,
        arrowType,
        
        // Functions
        setText,
        onIntersectionObserver,
        updateArrowVisibility,
    };
}

