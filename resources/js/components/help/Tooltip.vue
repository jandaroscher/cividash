<template>
  <div
    class="tooltip-wrapper"
    :class="wrapperClass"
  >
    <div
      ref="triggerRef"
      :aria-describedby="tooltipId"
      :class="triggerClass"
      @mouseenter="handleMouseEnter"
      @mouseleave="handleMouseLeave"
      @focus="handleFocus"
      @blur="handleBlur"
      @keydown.enter="handleKeyEnter"
      @keydown.space.prevent="handleKeyEnter"
    >
      <slot />
    </div>
        
    <Teleport to="body">
      <div
        v-if="isVisible && !props.disabled"
        :id="tooltipId"
        ref="tooltipRef"
        role="tooltip"
        :class="[
          'tooltip z-30 px-3 py-2 text-sm rounded shadow-lg pointer-events-none transition-opacity duration-200',
          positionClasses,
          'max-w-xs'
        ]"
        :style="tooltipStyles"
      >
        <div
          class="tooltip-content text-white"
          v-html="sanitizedText"
        />
        <!-- Arrow -->
        <div
          :class="[
            'tooltip-arrow absolute w-2 h-2',
            arrowClasses
          ]"
          :style="arrowStyles"
        />
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import DOMPurify from 'dompurify';

const props = defineProps({
    text: {
        type: String,
        required: true,
    },
    position: {
        type: String,
        default: 'top',
        validator: (value) => ['top', 'bottom', 'left', 'right'].includes(value),
    },
    delay: {
        type: Number,
        default: 300,
    },
    trigger: {
        type: String,
        default: 'hover',
        validator: (value) => ['hover', 'click', 'focus'].includes(value),
    },
    wrapperClass: {
        type: String,
        default: 'relative inline-block',
    },
    triggerClass: {
        type: String,
        default: 'inline-block',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const triggerRef = ref(null);
const tooltipRef = ref(null);
const isVisible = ref(false);
const tooltipId = computed(() => `tooltip-${Math.random().toString(36).substr(2, 9)}`);
const sanitizedText = computed(() => {
    if (!props.text) return '';
    return DOMPurify.sanitize(props.text);
});
let showTimeout = null;
let hideTimeout = null;

const positionClasses = computed(() => {
    const base = 'bg-gray-900';
    return base;
});

const arrowClasses = computed(() => {
    const map = {
        top: 'bottom-0 left-1/2 -translate-x-1/2 translate-y-full border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900',
        bottom: 'top-0 left-1/2 -translate-x-1/2 -translate-y-full border-l-4 border-r-4 border-b-4 border-transparent border-b-gray-900',
        left: 'right-0 top-1/2 -translate-y-1/2 translate-x-full border-t-4 border-b-4 border-l-4 border-transparent border-l-gray-900',
        right: 'left-0 top-1/2 -translate-y-1/2 -translate-x-full border-t-4 border-b-4 border-r-4 border-transparent border-r-gray-900',
    };
    return map[props.position] || map.top;
});

const tooltipStyles = computed(() => {
    if (!isVisible.value || !triggerRef.value || !tooltipRef.value) {
        return {};
    }
    
    const triggerRect = triggerRef.value.getBoundingClientRect();
    const tooltipRect = tooltipRef.value.getBoundingClientRect();
    const spacing = 8;
    
    let top = 0;
    let left = 0;
    
    switch (props.position) {
        case 'top':
            top = triggerRect.top - tooltipRect.height - spacing;
            left = triggerRect.left + (triggerRect.width / 2) - (tooltipRect.width / 2);
            break;
        case 'bottom':
            top = triggerRect.bottom + spacing;
            left = triggerRect.left + (triggerRect.width / 2) - (tooltipRect.width / 2);
            break;
        case 'left':
            top = triggerRect.top + (triggerRect.height / 2) - (tooltipRect.height / 2);
            left = triggerRect.left - tooltipRect.width - spacing;
            break;
        case 'right':
            top = triggerRect.top + (triggerRect.height / 2) - (tooltipRect.height / 2);
            left = triggerRect.right + spacing;
            break;
    }
    
    // Auto-adjust if tooltip goes outside viewport
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Ensure tooltip doesn't go off-screen horizontally
    if (left < spacing) {
        left = spacing;
    } else if (left + tooltipRect.width > viewportWidth - spacing) {
        left = viewportWidth - tooltipRect.width - spacing;
    }
    
    // Ensure tooltip doesn't go off-screen vertically
    if (top < spacing) {
        // If top doesn't fit, try bottom instead
        if (props.position === 'top') {
            top = triggerRect.bottom + spacing;
        } else {
            top = spacing;
        }
    } else if (top + tooltipRect.height > viewportHeight - spacing) {
        // If bottom doesn't fit, try top instead
        if (props.position === 'bottom') {
            top = triggerRect.top - tooltipRect.height - spacing;
        } else {
            top = viewportHeight - tooltipRect.height - spacing;
        }
    }
    
    return {
        top: `${top}px`,
        left: `${left}px`,
        position: 'fixed',
    };
});

const arrowStyles = computed(() => {
    // Arrow positioning is handled via CSS classes
    return {};
});

function showTooltip() {
    if (props.disabled) return;
    
    if (hideTimeout) {
        clearTimeout(hideTimeout);
        hideTimeout = null;
    }
    
    if (showTimeout) {
        clearTimeout(showTimeout);
    }
    
    showTimeout = setTimeout(() => {
        if (props.disabled) return;
        isVisible.value = true;
        nextTick(() => {
            updatePosition();
        });
    }, props.delay);
}

function hideTooltip() {
    if (showTimeout) {
        clearTimeout(showTimeout);
        showTimeout = null;
    }

    hideTimeout = setTimeout(() => {
        isVisible.value = false;
    }, 100); // Small delay to allow moving from trigger to tooltip
}

// Watch for disabled prop changes to hide tooltip immediately
watch(() => props.disabled, (disabled) => {
    if (disabled && isVisible.value) {
        isVisible.value = false;
        if (showTimeout) {
            clearTimeout(showTimeout);
            showTimeout = null;
        }
    }
});

function handleMouseEnter() {
    if (props.disabled) return;
    if (props.trigger === 'hover' || props.trigger === 'click') {
        showTooltip();
    }
}

function handleMouseLeave() {
    if (props.trigger === 'hover' || props.trigger === 'click') {
        hideTooltip();
    }
}

function handleFocus() {
    if (props.disabled) return;
    if (props.trigger === 'focus') {
        showTooltip();
    }
}

function handleBlur() {
    if (props.trigger === 'focus') {
        hideTooltip();
    }
}

function handleKeyEnter() {
    if (props.trigger === 'click') {
        if (isVisible.value) {
            hideTooltip();
        } else {
            showTooltip();
        }
    }
}

function updatePosition() {
    // Force re-computation of position
    if (tooltipRef.value && triggerRef.value) {
        // Position is computed via tooltipStyles, so this is mainly for triggering reactivity
        nextTick(() => {
            // Position will be recalculated automatically
        });
    }
}

// Handle window resize and scroll
function handleResize() {
    if (isVisible.value) {
        updatePosition();
    }
}

onMounted(() => {
    window.addEventListener('resize', handleResize);
    window.addEventListener('scroll', handleResize, true);
});

onBeforeUnmount(() => {
    if (showTimeout) {
        clearTimeout(showTimeout);
    }
    if (hideTimeout) {
        clearTimeout(hideTimeout);
    }
    window.removeEventListener('resize', handleResize);
    window.removeEventListener('scroll', handleResize, true);
});
</script>

<style scoped>
.tooltip {
    background-color: rgba(17, 24, 39, 0.95); /* gray-900 with slight transparency */
}

.tooltip-content {
    word-wrap: break-word;
    white-space: normal;
}
</style>

