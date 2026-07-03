<template>
    <section 
        class="relative bg-gray-900 text-white py-20"
        :style="{ backgroundColor: 'var(--hero-background-color, #111827)' }"
        :aria-label="block.props.title || 'Hero section'"
    >
        <div v-if="imageUrl" class="absolute inset-0" style="z-index: var(--z-base);">
            <img
                :src="imageUrl"
                :alt="block.props.image_alt || ''"
                class="w-full h-full object-cover opacity-50"
            />
        </div>
        <div class="container relative" style="z-index: var(--z-dropdown);">
            <div>
                <h1
                    v-if="block.props.title"
                    class="content-heading text-white mb-6 hyphens-auto"
                >
                    {{ block.props.title }}
                </h1>
                <p
                    v-if="block.props.subtitle"
                    class="text-theme-h5 mb-8 text-gray-200"
                >
                    {{ block.props.subtitle }}
                </p>
                <a
                    v-if="block.props.cta_text && block.props.cta_url"
                    :href="ctaUrl"
                    :target="isExternal ? '_blank' : undefined"
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
import { computed, toRef } from 'vue';
import { useImageUrl } from '../../composables/useImageUrl';
import { isExternalUrl } from '../../utils/sanitizeHtml';

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

const imageUrl = useImageUrl(toRef(() => props.block.props.image));

const ctaUrl = computed(() => {
    const url = props.block.props.cta_url;
    if (!url) return '#';
    // If URL starts with http://, https://, or /, use it as-is
    if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('/')) {
        return url;
    }
    return `/${url}`;
});

const isExternal = computed(() => isExternalUrl(props.block.props.cta_url));
</script>

