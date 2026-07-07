<template>
    <section
        v-if="visibleItems.length > 0"
        class="py-12 md:py-16"
    >
        <div class="container">
            <details
                v-for="(item, index) in visibleItems"
                :key="item.id || `faq-${index}`"
                class="group faq-item"
                @toggle="isOpen[index] = $event.target.open"
            >
                <summary
                    class="cursor-pointer grid grid-cols-12 py-5 pl-5 font-bold select-none transition-colors duration-200"
                    :style="{ color: isOpen[index] ? brandingStore.primaryColor : 'var(--text-primary-color, #191919)' }"
                >
                    <div class="col-span-12 lg:col-span-11 flex items-center gap-5">
                        <span
                            class="faq-chevron shrink-0 inline-flex items-center justify-center transition-transform duration-300"
                            :class="isOpen[index] ? '' : 'rotate-180'"
                            :style="{ color: brandingStore.primaryColor }"
                        >
                            <svg
                                width="22"
                                height="13"
                                viewBox="0 0 22 13"
                                fill="currentColor"
                                xmlns="http://www.w3.org/2000/svg"
                                aria-hidden="true"
                            >
                                <path d="M10.5459 0L21.0918 10.2549L19 12.4053L10.5459 4.18457L2.0918 12.4053L0 10.2549L10.5459 0Z" />
                            </svg>
                        </span>
                        <span class="text-base leading-6">{{ item.question }}</span>
                    </div>
                </summary>
                <div
                    v-if="item.answer"
                    class="grid grid-cols-12 pb-5"
                >
                    <div
                        class="col-span-12 lg:col-span-11 prose max-w-none text-theme-secondary"
                        style="padding-left: calc(20px + 22px + 20px);"
                        v-html="getSanitizedAnswer(item)"
                    />
                </div>
            </details>
        </div>
    </section>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { sanitizeHtml } from '../../utils/sanitizeHtml';
import { useBrandingStore } from '../../stores/branding';

const brandingStore = useBrandingStore();

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const isOpen = reactive({});

// Items without an answer would render an openable/collapsible <details> element
// with nothing inside it. Filter them out so only items that actually
// have content are shown and clickable.
const visibleItems = computed(() => {
    return (props.block.props.items || []).filter(item => !!item?.answer);
});

function getSanitizedAnswer(item) {
    if (!item.answer) return '';
    return sanitizeHtml(item.answer);
}
</script>

<style scoped>
/* Horizontal dividers between items - full width */
.faq-item {
    border-top: 1px solid var(--border-color, #757575);
}

.faq-item:last-child {
    border-bottom: 1px solid var(--border-color, #757575);
}

/* Hide default browser marker/arrow */
.faq-item summary {
    list-style: none;
}
.faq-item summary::-webkit-details-marker {
    display: none;
}
.faq-item summary::marker {
    display: none;
    content: '';
}
</style>
