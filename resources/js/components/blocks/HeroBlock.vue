<template>
    <section 
        class="relative bg-gray-900 text-white py-20"
        :aria-label="block.props.title || 'Hero section'"
    >
        <div v-if="imageUrl" class="absolute inset-0 z-10">
            <img
                :src="imageUrl"
                :alt="block.props.image_alt || ''"
                class="w-full h-full object-cover opacity-50"
            />
        </div>
        <div class="container relative z-20">
            <div>
                <h1
                    v-if="block.props.title"
                    class="text-4xl lg:text-5xl text-white font-bold mb-6 hyphens-auto"
                >
                    {{ block.props.title }}
                </h1>
                <p
                    v-if="block.props.subtitle"
                    class="text-xl md:text-2xl mb-8 text-gray-200"
                >
                    {{ block.props.subtitle }}
                </p>
                <a
                    v-if="block.props.cta_text && block.props.cta_url"
                    :href="ctaUrl"
                    :rel="isExternal ? 'noopener noreferrer' : undefined"
                    class="inline-block bg-accent hover:bg-accent-dark text-white font-semibold py-3 px-6 rounded-lg transition-colors duration-200"
                >
                    {{ block.props.cta_text }}
                </a>
            </div>
        </div>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue';

interface HeroBlockProps {
    type: string;
    props: {
        title?: string;
        subtitle?: string;
        image?: string;
        image_alt?: string;
        cta_text?: string;
        cta_url?: string;
    };
}

const props = defineProps<{
    block: HeroBlockProps;
}>();

const imageUrl = computed(() => {
    if (!props.block.props.image) return null;
    // If image is already a full URL, return it; otherwise prepend storage URL
    if (props.block.props.image.startsWith('http://') || props.block.props.image.startsWith('https://')) {
        return props.block.props.image;
    }
    return `/storage/${props.block.props.image}`;
});

const ctaUrl = computed(() => {
    const url = props.block.props.cta_url;
    if (!url) return '#';
    // If URL starts with http://, https://, or /, use it as-is
    if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('/')) {
        return url;
    }
    return `/${url}`;
});

const isExternal = computed(() => {
    const url = props.block.props.cta_url;
    if (!url) return false;
    return url.startsWith('http://') || url.startsWith('https://');
});
</script>

