<template>
    <div class="container">
        <h1
            v-if="block.props.heading"
            class="text-4xl lg:text-5xl text-black font-bold mb-6 hyphens-auto"
        >
            {{ block.props.heading }}
        </h1>

        <div class="lg:grid lg:grid-cols-3 lg:gap-10 mb-10 lg:mb-14">
            <div class="col-span-2">
                <h2
                    v-if="block.props.subheading"
                    class="text-3xl text-black font-bold mb-6 lg:mb-12 hyphens-auto"
                >
                    {{ block.props.subheading }}
                </h2>
                
                <img 
                    v-if="imageUrl"
                    :alt="block.props.image_alt || ''" 
                    class="lg:hidden mb-6 block" 
                    :src="imageUrl" 
                    loading="lazy"
                />

                <div
                    v-if="block.props.text"
                    class="prose prose-lg max-w-none [&_p]:text-xl [&_p]:text-black [&_p]:mb-5 [&_p:last-child]:mb-10 [&_a:hover]:text-accent [&_a]:transition-colors [&_a]:duration-300"
                    v-html="sanitizedText"
                />
            </div>
            
            <div class="col-span-1">
                <img 
                    v-if="imageUrl"
                    :alt="block.props.image_alt || ''" 
                    class="hidden lg:block mb-10" 
                    :src="imageUrl" 
                    loading="lazy"
                />
                <img 
                    v-if="imageSecondaryUrl"
                    :alt="block.props.image_secondary_alt || ''" 
                    class="hidden lg:block" 
                    :src="imageSecondaryUrl" 
                    loading="lazy"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, toRef } from 'vue';
import { sanitizeHtml } from '../../utils/sanitizeHtml';
import { useImageUrl } from '../../composables/useImageUrl';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

// Get image URLs using composable
const imageUrl = useImageUrl(toRef(() => props.block.props.image));
const imageSecondaryUrl = useImageUrl(toRef(() => props.block.props.image_secondary));

const sanitizedText = computed(() => {
    return props.block.props.text ? sanitizeHtml(props.block.props.text) : '';
});
</script>



