<template>
    <section 
        class="py-12 md:py-16"
        :style="{ backgroundColor: block.props.background_color || 'var(--card-background-color, #FFFFFF)' }"
    >
        <div
            v-if="block.props.content"
            class="prose prose-lg max-w-none"
            v-html="sanitizedContent"
        />
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { sanitizeHtml } from '../../utils/sanitizeHtml';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const sanitizedContent = computed(() => {
    return props.block.props.content ? sanitizeHtml(props.block.props.content) : '';
});
</script>



