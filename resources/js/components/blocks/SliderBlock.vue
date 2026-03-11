<template>
  <div
    v-if="slides.length > 0"
    class="px-0"
  >
    <h2
      v-if="heading"
      class="text-theme-h2 font-bold mb-6"
    >
      {{ heading }}
    </h2>
    <div
      ref="sliderRoot"
      class="overflow-visible relative"
    >
      <Flicking
        ref="flicking"
        class="-mx-5 px-5 -mt-5 pt-5"
        :class="[slides.length > panelsPerView ? 'pb-10' : '-mb-5 pb-5']"
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
        <template
          v-for="(slide, index) in slides"
          :key="index"
        >
          <div class="block hyphens-auto mr-10 min-h-full">
            <div class="shadow-card h-full flex flex-col">
              <div
                v-if="slide.image"
                class="relative h-fit slide-media"
              >
                <img
                  :src="slide.imageUrl"
                  :alt="slide.title || ''"
                  class="aspect-[3/2] w-full object-cover slide-image"
                >
              </div>

              <div
                v-if="!slide.image"
                class="bg-gray-200 pt-[66.66%] slide-media"
              />

              <div
                class="px-4 pt-5 pb-6 flex flex-col justify-between h-full"
                :style="{ backgroundColor: 'var(--card-background-color, #FFFFFF)' }"
              >
                <div>
                  <p
                    v-if="slide.title"
                    class="text-theme-h5 font-bold mb-2"
                  >
                    {{ slide.title }}
                  </p>
                  <p
                    v-if="slide.description"
                    class="whitespace-break-spaces"
                  >
                    {{ slide.description }}
                  </p>
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
          <div
            v-show="slides.length > panelsPerView"
            class="flicking-pagination"
          />
        </template>
      </Flicking>

      <button
        v-show="slides.length > panelsPerView"
        type="button"
        class="flicking-arrow-prev flicking-arrow-prev-overlay"
        :style="{ top: imageCenterY + 'px' }"
        :aria-label="currentLocale === 'en' ? 'Previous slide' : 'Vorherige Folie'"
        @click="$refs.flicking?.prev()"
        @keydown.enter.prevent="$refs.flicking?.prev()"
        @keydown.space.prevent="$refs.flicking?.prev()"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="36"
          height="36"
          viewBox="0 0 36 36"
          aria-hidden="true"
        >
          <circle
            cx="18"
            cy="18"
            r="18"
            fill="#fff"
          />
          <path
            d="M1.061,1.061l9.238,9.5-9.238,9.5"
            transform="translate(22.806 29.558) rotate(180)"
            fill="none"
            stroke="currentColor"
            stroke-miterlimit="10"
            stroke-width="3"
          />
        </svg>
      </button>
      <button
        v-show="slides.length > panelsPerView"
        type="button"
        class="flicking-arrow-next flicking-arrow-next-overlay"
        :style="{ top: imageCenterY + 'px' }"
        :aria-label="currentLocale === 'en' ? 'Next slide' : 'Nächste Folie'"
        @click="$refs.flicking?.next()"
        @keydown.enter.prevent="$refs.flicking?.next()"
        @keydown.space.prevent="$refs.flicking?.next()"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="36"
          height="36"
          viewBox="0 0 36 36"
          aria-hidden="true"
        >
          <circle
            cx="18"
            cy="18"
            r="18"
            fill="#fff"
          />
          <path
            d="M0,18.995,9.238,9.5,0,0"
            transform="translate(14.254 9.503)"
            fill="none"
            stroke="currentColor"
            stroke-miterlimit="10"
            stroke-width="3"
          />
        </svg>
      </button>
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

const heading = computed(() => {
    return props.block?.props?.heading || '';
});

const flicking = ref(null);
const sliderRoot = ref(null);
const imageCenterY = ref(150);
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
            // Handle image URL - can be a path or full URL
            let imageUrl = '';
            if (item.image) {
                if (item.image.startsWith('http://') || item.image.startsWith('https://')) {
                    imageUrl = item.image;
                } else {
                    imageUrl = `/storage/${item.image}`;
                }
            }

            return {
                title: item.title || '',
                description: item.description || '',
                image: item.image || null,
                imageUrl,
                link_url: item.link_url || null,
                link_text: item.link_text || '',
            };
        })
        .filter((slide) => {
            // Only include slides that have at least title, description, or image
            return slide.title || slide.description || slide.image;
        });
});

function setImageCenterY() {
    nextTick(() => {
        const container = sliderRoot.value;
        const media = container?.querySelector('.slide-image, .slide-media');
        if (!container || !media) return;

        const imageRect = media.getBoundingClientRect();
        const containerRect = container.getBoundingClientRect();
        imageCenterY.value = imageRect.top - containerRect.top + (media.offsetHeight / 2);
    });
}

function onResize() {
    windowWidth.value = window.innerWidth;
    setImageCenterY();
}

function updateImageCenterYWhenReady() {
    const container = sliderRoot.value;
    const media = container?.querySelector('.slide-image, .slide-media');
    if (!media) return;

    if (!(media instanceof HTMLImageElement)) {
        setImageCenterY();
    } else if (media.complete && media.naturalHeight > 0) {
        setImageCenterY();
    } else {
        media.addEventListener('load', setImageCenterY, { once: true });
    }
}

watch([heading, slides], () => {
    updateImageCenterYWhenReady();
}, { flush: 'post' });

onMounted(() => {
    // Initialize plugins after component is mounted to avoid SSR issues
    plugins.value = [
        new Arrow({
            parentEl: sliderRoot.value,
            prevElSelector: '.flicking-arrow-prev-overlay',
            nextElSelector: '.flicking-arrow-next-overlay',
        }),
        new Pagination({ type: 'bullet' }),
    ];
    
    // Wait for first image to load before calculating center
    updateImageCenterYWhenReady();
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
});
</script>


<style>
.flicking-arrow-prev,
.flicking-arrow-next {
    height: 36px;
    width: 36px !important;
    border-radius: 50%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    transform: translateY(-50%);
    cursor: pointer;
    color: var(--accent-color, #E30613);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 0;
}

.flicking-arrow-prev {
    left: -18px;
    position: absolute;
}

.flicking-arrow-next {
    right: -18px;
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
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
}

.flicking-arrow-prev.flicking-arrow-disabled,
.flicking-arrow-next.flicking-arrow-disabled {
    color: #e5e5e5;
    opacity: 0.4;
    cursor: default;
    box-shadow: 0px 3px 6px #00000029;
}

.flicking-pagination-bullet-active {
    background-color: var(--primary-color, #e30613) !important;
}

.flicking-pagination-scroll {
    width: auto !important;
}
</style>

