<template>
    <section class="py-12 md:py-16">
        <h2
            v-if="block.props.heading"
            class="text-3xl font-bold mb-6"
        >
            {{ block.props.heading }}
        </h2>
        <div
            v-if="block.props.text"
            class="prose prose-lg max-w-none"
            v-html="sanitizedText"
        />
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

const sanitizedText = computed(() => {
    return props.block.props.text ? DOMPurify.sanitize(props.block.props.text) : '';
});
</script>



