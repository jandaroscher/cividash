<template>
    <section :class="['py-12 md:py-16', block.props.background_color || 'bg-white']">
        <div class="container">
            <div
                v-if="block.props.content"
                class="prose prose-lg max-w-none"
                v-html="sanitizedContent"
            />
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import DOMPurify from 'dompurify';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const sanitizedContent = computed(() => {
    return props.block.props.content ? DOMPurify.sanitize(props.block.props.content) : '';
});
</script>



