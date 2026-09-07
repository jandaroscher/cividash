<template>
  <Teleport to="body">
    <aside
      v-if="isVisible"
      ref="overlayRef"
      tabindex="0"
      class="fixed z-50 w-full top-0 left-0 h-dvh bg-[rgba(0,0,0,.5)] transition-opacity duration-200"
      :class="{ 'opacity-0 pointer-events-none': !isVisible }"
      aria-label="Help Overlay"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      @keydown.esc="closeOverlay"
      @click.self="handleBackdropClick"
    >
      <div
        ref="contentRef"
        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto"
        :class="{ 'opacity-0 scale-95': !isVisible, 'opacity-100 scale-100': isVisible }"
        style="transition: opacity 0.2s, transform 0.2s;"
      >
        <div class="p-6">
          <!-- Header -->
          <div class="flex items-start justify-between mb-4">
            <h2
              :id="titleId"
              class="text-2xl font-bold text-gray-900"
            >
              {{ title }}
            </h2>
            <button
              v-if="dismissible"
              type="button"
              class="ml-4 text-gray-400 hover:text-gray-600 transition-colors"
              aria-label="Close help"
              @click="closeOverlay"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-6 w-6"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
            </button>
          </div>
                    
          <!-- Content -->
          <div class="prose prose-sm max-w-none">
            <div
              v-if="typeof content === 'string'"
              class="text-gray-700"
              v-html="sanitizedContent"
            />
            <div
              v-else-if="content"
              class="text-gray-700"
            >
              <component
                :is="content.component"
                v-if="typeof content === 'object' && content.component"
                v-bind="content.props || {}"
              />
              <div v-else>
                {{ content }}
              </div>
            </div>
          </div>
                    
          <!-- Footer -->
          <div
            v-if="dismissible"
            class="mt-6 flex justify-end"
          >
            <button
              type="button"
              class="px-4 py-2 rounded font-medium transition-colors"
              :style="{
                backgroundColor: brandingStore.primaryColor,
                color: 'white'
              }"
              @click="closeOverlay"
            >
              {{ closeButtonText }}
            </button>
          </div>
        </div>
      </div>
    </aside>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import DOMPurify from 'dompurify';
import { useBrandingStore } from '../../stores/branding';
import { useLocale } from '../../composables/useLocale';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    content: {
        type: [String, Object],
        required: true,
    },
    dismissible: {
        type: Boolean,
        default: true,
    },
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'close']);

const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();

const overlayRef = ref(null);
const contentRef = ref(null);
const isVisible = ref(props.modelValue);
// Generate a stable, per-instance ID once during setup (SSR-safe)
const titleId = ref(`help-overlay-title-${Math.random().toString(36).substr(2, 9)}`);
const sanitizedContent = computed(() => {
    if (typeof props.content === 'string') {
        return DOMPurify.sanitize(props.content);
    }
    return props.content;
});
let previousActiveElement = null;
let focusTrapHandler = null;

const closeButtonText = computed(() => {
    return currentLocale.value === 'en' ? 'Close' : 'Schließen';
});

watch(
    () => props.modelValue,
    (newValue) => {
        if (isVisible.value === newValue) return;
        isVisible.value = newValue;
        if (newValue) {
            openOverlay();
        } else {
            closeOverlay();
        }
    }
);

watch(isVisible, (newValue) => {
    emit('update:modelValue', newValue);
});

function openOverlay() {
    isVisible.value = true;
    previousActiveElement = document.activeElement;
    
    // Lock body scroll
    document.body.style.overflow = 'hidden';
    
    // Focus first focusable element
    nextTick(() => {
        setTimeout(() => {
            const focusableElements = getFocusableElements();
            if (focusableElements.length > 0) {
                focusableElements[0].focus();
            } else if (overlayRef.value) {
                overlayRef.value.focus();
            }
        }, 100);
    });
    
    // Setup focus trap
    setupFocusTrap();
}

function closeOverlay() {
    isVisible.value = false;
    
    // Restore body scroll
    document.body.style.overflow = '';
    
    // Remove focus trap
    if (focusTrapHandler) {
        document.removeEventListener('keydown', focusTrapHandler);
        focusTrapHandler = null;
    }
    
    // Restore focus
    if (previousActiveElement) {
        previousActiveElement.focus();
        previousActiveElement = null;
    }
    
    emit('close');
}

function handleBackdropClick() {
    if (props.dismissible) {
        closeOverlay();
    }
}

function getFocusableElements() {
    if (!contentRef.value) return [];
    
    const selector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    return Array.from(contentRef.value.querySelectorAll(selector)).filter(
        (el) => {
            const style = window.getComputedStyle(el);
            return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
        }
    );
}

function setupFocusTrap() {
    focusTrapHandler = (e) => {
        if (!isVisible.value || e.key !== 'Tab') return;
        
        const focusableElements = getFocusableElements();
        if (focusableElements.length === 0) return;
        
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];
        
        if (e.shiftKey && document.activeElement === firstElement) {
            e.preventDefault();
            lastElement.focus();
        } else if (!e.shiftKey && document.activeElement === lastElement) {
            e.preventDefault();
            firstElement.focus();
        }
    };
    
    document.addEventListener('keydown', focusTrapHandler);
}

onMounted(() => {
    if (props.modelValue) {
        openOverlay();
    }
});

onBeforeUnmount(() => {
    if (focusTrapHandler) {
        document.removeEventListener('keydown', focusTrapHandler);
    }
    if (isVisible.value) {
        document.body.style.overflow = '';
        if (previousActiveElement) {
            previousActiveElement.focus();
        }
    }
});
</script>

<style scoped>
.prose {
    color: #374151; /* gray-700 */
}

.prose :deep(h1),
.prose :deep(h2),
.prose :deep(h3) {
    color: #111827; /* gray-900 */
    font-weight: 700;
    margin-top: 1.5em;
    margin-bottom: 0.75em;
}

.prose :deep(p) {
    margin-bottom: 1em;
}

.prose :deep(ul),
.prose :deep(ol) {
    margin-bottom: 1em;
    padding-left: 1.5em;
}

.prose :deep(li) {
    margin-bottom: 0.5em;
}

.prose :deep(a) {
    color: v-bind('brandingStore.primaryColor');
    text-decoration: underline;
}

.prose :deep(a:hover) {
    opacity: 0.8;
}
</style>

