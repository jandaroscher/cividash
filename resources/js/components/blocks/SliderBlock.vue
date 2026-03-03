<template>
    <div v-if="slides.length > 0" class="px-0">
        <div class="overflow-visible relative">
            <Flicking
                ref="flicking"
                class="-mx-5 px-5 -mt-5 pt-5"
                :class="[slides.length > 1 ? 'pb-10' : '-mb-5 pb-5']"
                :plugins="plugins"
                :options="{
                    align,
                    defaultIndex: 0,
                    circular,
                    circularFallback: 'bound',
                    moveType: 'snap',
                    panelsPerView,
                    bound: true
                }"
            >
                <template v-for="(slide, index) in slides" :key="index">
                    <div class="block hyphens-auto mr-10 min-h-full">
                        <div class="shadow-card h-full flex flex-col">
                            <div class="relative h-fit" v-if="slide.image">
                                <img
                                    :src="slide.imageUrl"
                                    :alt="slide.title || ''"
                                    class="aspect-[3/2] w-full object-cover slide-image"
                                />
                            </div>

                            <div v-if="!slide.image" class="bg-gray-200 pt-[66.66%]"></div>

                            <div class="px-4 pt-5 pb-6 flex flex-col justify-between h-full" :style="{ backgroundColor: 'var(--card-background-color, #FFFFFF)' }">
                                <div>
                                    <p v-if="slide.title" class="text-xl font-bold mb-2">{{ slide.title }}</p>
                                    <p v-if="slide.description" class="whitespace-break-spaces">{{ slide.description }}</p>
                                </div>

                                <a
                                    v-if="slide.link_url"
                                    :href="slide.link_url"
                                    :target="isExternalUrl(slide.link_url) ? '_blank' : undefined"
                                    :rel="isExternalUrl(slide.link_url) ? 'noopener noreferrer' : undefined"
                                    :aria-label="`${slide.link_text || (currentLocale === 'en' ? 'Learn more' : 'Mehr erfahren')}${isExternalUrl(slide.link_url) ? ` (${currentLocale === 'en' ? 'opens in new tab' : 'öffnet in neuem Tab'})` : ''}`"
                                    class="self-start inline-block p-2 text-white mt-6 hover:shadow-info transition-shadow duration-200"
                                    :style="{ backgroundColor: brandingStore.primaryColor }"
                                >
                                    {{ slide.link_text || (currentLocale === 'en' ? 'Learn more' : 'Mehr erfahren') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </template>

                <template #viewport>
                    <div v-show="slides.length > 1" class="flicking-pagination"></div>
                </template>
            </Flicking>

            <button
                v-show="slides.length > 1"
                type="button"
                class="flicking-arrow-prev flicking-arrow-prev-overlay"
                :style="{ top: (imageHeight - 18) + 'px' }"
                :aria-label="currentLocale === 'en' ? 'Previous slide' : 'Vorherige Folie'"
                @click="$refs.flicking?.prev()"
                @keydown.enter.prevent="$refs.flicking?.prev()"
                @keydown.space.prevent="$refs.flicking?.prev()"
            ></button>
            <button
                v-show="slides.length > 1"
                type="button"
                class="flicking-arrow-next flicking-arrow-next-overlay"
                :style="{ top: (imageHeight - 18) + 'px' }"
                :aria-label="currentLocale === 'en' ? 'Next slide' : 'Nächste Folie'"
                @click="$refs.flicking?.next()"
                @keydown.enter.prevent="$refs.flicking?.next()"
                @keydown.space.prevent="$refs.flicking?.next()"
            ></button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import Flicking from '@egjs/vue3-flicking';
import { Arrow } from '@egjs/flicking-plugins';
import { Pagination } from '@egjs/flicking-plugins';
import '@egjs/vue3-flicking/dist/flicking.css';
import '@egjs/flicking-plugins/dist/arrow.css';
import '@egjs/flicking-plugins/dist/pagination.css';
import { useLocale } from '../../composables/useLocale';
import { useBrandingStore } from '../../stores/branding';
import { isExternalUrl } from '../../utils/sanitizeHtml';

const brandingStore = useBrandingStore();

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const { currentLocale } = useLocale();

const flicking = ref(null);
const imageHeight = ref(300);
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const plugins = ref([]);

const panelsPerView = computed(() => {
    return windowWidth.value >= 768 ? 2 : 1;
});

const align = computed(() => 'prev');
const circular = computed(() => false);

const slides = computed(() => {
    if (!props.block?.props?.items || !Array.isArray(props.block.props.items)) {
        return [];
    }

    return props.block.props.items
        .filter((item) => item?.is_active !== false)
        .map((item) => {
            // Handle translations - data comes as title/description (DE) and title_en/description_en (EN)
            let title = '';
            if (currentLocale.value === 'en') {
                title = item.title_en || item.title || '';
            } else {
                title = item.title || item.title_en || '';
            }
            
            let description = '';
            if (currentLocale.value === 'en') {
                description = item.description_en || item.description || '';
            } else {
                description = item.description || item.description_en || '';
            }
            
            // Handle image URL - can be a path or full URL
            let imageUrl = '';
            if (item.image) {
                if (item.image.startsWith('http://') || item.image.startsWith('https://')) {
                    imageUrl = item.image;
                } else {
                    imageUrl = `/storage/${item.image}`;
                }
            }

            let link_text = '';
            if (item.link_text) {
                if (currentLocale.value === 'en') {
                    link_text = item.link_text_en || item.link_text || '';
                } else {
                    link_text = item.link_text || item.link_text_en || '';
                }
            }

            return {
                title,
                description,
                image: item.image || null,
                imageUrl,
                link_url: item.link_url || null,
                link_text: link_text || (currentLocale.value === 'en' ? 'Learn more' : 'Mehr erfahren'),
            };
        })
        .filter((slide) => {
            // Only include slides that have at least title, description, or image
            return slide.title || slide.description || slide.image;
        });
});

function setImageHeight() {
    nextTick(() => {
        // Scope DOM queries to component instance to avoid conflicts
        const flickingContainer = flicking.value?.$el;
        if (!flickingContainer) return;
        
        // Find the first visible slide image within this component instance
        const image = flickingContainer.querySelector('.slide-image');
        if (image && image.offsetHeight !== 0) {
            // Get the image's position relative to the flicking container
            const imageRect = image.getBoundingClientRect();
            const containerRect = flickingContainer.getBoundingClientRect();
            // Calculate the top position relative to the container
            const relativeTop = imageRect.top - containerRect.top;
            // Set height to image height + its offset from top of container
            imageHeight.value = image.offsetHeight + relativeTop;
        }
    });
}

function onResize() {
    windowWidth.value = window.innerWidth;
    setImageHeight();
}

watch(windowWidth, () => {
    setImageHeight();
});

onMounted(() => {
    // Initialize plugins after component is mounted to avoid SSR issues
    plugins.value = [
        new Arrow({
            parentEl: document.body,
            prevElSelector: '.flicking-arrow-prev-overlay',
            nextElSelector: '.flicking-arrow-next-overlay',
        }),
        new Pagination({ type: 'bullet' }),
    ];
    
    setImageHeight();
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
});
</script>

<style scoped>
.shadow-card {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.shadow-info {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}
</style>

<style>
.flicking-arrow-prev,
.flicking-arrow-next {
    height: 36px;
    width: 36px !important;
    border-radius: 50%;
    box-shadow: 0px 1px 10px #707070;
    transform: none;
    cursor: pointer;
}

.flicking-arrow-prev {
    left: -18px;
    background-image: url('/assets/images/arrow-left.svg');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    position: absolute;
}

.flicking-arrow-next {
    right: -18px;
    background-image: url('/assets/images/arrow-right.svg');
    background-size: contain;
    background-repeat: no-repeat;
    background-position: center;
    position: absolute;
}

.flicking-arrow-prev::before,
.flicking-arrow-prev::after {
    display: none !important;
}

.flicking-arrow-next::before,
.flicking-arrow-next::after {
    display: none !important;
}

.flicking-arrow-prev:hover,
.flicking-arrow-next:hover {
    box-shadow: 0px 1px 10px #707070;
}

.flicking-arrow-prev.flicking-arrow-disabled {
    background-image: url('/assets/images/arrow-left-inactive.svg');
}

.flicking-arrow-next.flicking-arrow-disabled {
    background-image: url('/assets/images/arrow-right-inactive.svg');
}

.flicking-pagination-bullet-active {
    background-color: var(--primary-color, #e30613) !important;
}

.flicking-pagination-scroll {
    width: auto !important;
}
</style>

