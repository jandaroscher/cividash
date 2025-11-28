<template>
    <section class="py-12 md:py-16">
        <div class="container">
            <a
                :href="linkUrl"
                :class="[
                    'inline-block',
                    block.props.style === 'button' ? 'bg-accent hover:bg-accent-dark text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200' : 'text-accent hover:text-accent-dark underline',
                ]"
            >
                {{ block.props.text }}
            </a>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const linkUrl = computed(() => {
    const url = props.block.props.url;
    if (!url) return '#';
    if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('/')) {
        return url;
    }
    return `/${url}`;
});
</script>



