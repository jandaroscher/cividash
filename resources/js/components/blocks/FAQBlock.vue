<template>
    <section class="py-12 md:py-16">
        <div class="space-y-4">
            <details
                v-for="(item, index) in block.props.items"
                :key="item.id || `faq-${index}`"
                class="group border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors"
            >
                <summary class="cursor-pointer font-semibold text-lg text-gray-900 hover:text-gray-700">
                    {{ item.question }}
                </summary>
                <div
                    v-if="item.answer"
                    class="mt-4 prose prose-sm max-w-none text-gray-600"
                    v-html="getSanitizedAnswer(item, index)"
                />
            </details>
        </div>
    </section>
</template>

<script setup>
import { sanitizeHtml } from '../../utils/sanitizeHtml';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

function getSanitizedAnswer(item, index) {
    if (!item.answer) return '';
    return sanitizeHtml(item.answer);
}
</script>

