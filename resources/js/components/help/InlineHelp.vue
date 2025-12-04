<template>
    <div class="inline-help">
        <div
            v-if="!collapsible || isExpanded"
            class="inline-help-content p-4 rounded-lg border"
            :style="{
                borderColor: brandingStore.primaryColor + '40',
                backgroundColor: brandingStore.primaryColor + '08'
            }"
        >
            <div class="flex items-start">
                <div
                    v-if="showIcon"
                    class="flex-shrink-0 mr-3 mt-0.5"
                    :style="{ color: brandingStore.primaryColor }"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3
                        v-if="title"
                        class="text-sm font-semibold mb-2"
                        :style="{ color: brandingStore.primaryColor }"
                    >
                        {{ title }}
                    </h3>
                    <div
                        class="text-sm text-gray-700"
                        v-html="sanitizedContent"
                    ></div>
                </div>
                <button
                    v-if="collapsible"
                    @click="toggle"
                    type="button"
                    class="ml-3 flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors"
                    :aria-label="isExpanded ? 'Collapse help' : 'Expand help'"
                    :aria-expanded="isExpanded"
                >
                    <svg
                        v-if="isExpanded"
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 15l7-7 7 7"
                        />
                    </svg>
                    <svg
                        v-else
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 9l-7 7-7-7"
                        />
                    </svg>
                </button>
            </div>
        </div>
        <button
            v-else
            @click="toggle"
            type="button"
            class="inline-help-toggle flex items-center text-sm font-medium transition-colors"
            :style="{ color: brandingStore.primaryColor }"
            :aria-label="'Show help'"
            :aria-expanded="false"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="h-4 w-4 mr-1"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>
            {{ toggleText }}
        </button>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import DOMPurify from 'dompurify';
import { useBrandingStore } from '../../stores/branding';
import { useLocale } from '../../composables/useLocale';

const props = defineProps({
    title: {
        type: String,
        default: null,
    },
    content: {
        type: String,
        required: true,
    },
    icon: {
        type: Boolean,
        default: true,
    },
    collapsible: {
        type: Boolean,
        default: false,
    },
    defaultExpanded: {
        type: Boolean,
        default: true,
    },
});

const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();

const isExpanded = ref(props.defaultExpanded);
const showIcon = computed(() => props.icon);
const sanitizedContent = computed(() => {
    return DOMPurify.sanitize(props.content);
});

const toggleText = computed(() => {
    return currentLocale.value === 'en' ? 'Show help' : 'Hilfe anzeigen';
});

function toggle() {
    isExpanded.value = !isExpanded.value;
}
</script>

<style scoped>
.inline-help {
    margin: 1rem 0;
}

.inline-help-content {
    transition: all 0.2s ease;
}

.inline-help-toggle {
    padding: 0.5rem 0;
}

.inline-help-toggle:hover {
    opacity: 0.8;
}

.inline-help-content :deep(p) {
    margin-bottom: 0.5rem;
}

.inline-help-content :deep(p:last-child) {
    margin-bottom: 0;
}

.inline-help-content :deep(ul),
.inline-help-content :deep(ol) {
    margin-left: 1.5rem;
    margin-bottom: 0.5rem;
}

.inline-help-content :deep(li) {
    margin-bottom: 0.25rem;
}

.inline-help-content :deep(a) {
    color: v-bind('brandingStore.primaryColor');
    text-decoration: underline;
}

.inline-help-content :deep(a:hover) {
    opacity: 0.8;
}
</style>

