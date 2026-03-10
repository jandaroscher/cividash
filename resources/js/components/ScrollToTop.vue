<template>
    <Transition name="fade">
        <button
            v-show="isVisible"
            type="button"
            class="fixed bottom-5 sm:bottom-10 right-5 sm:right-10 z-40 rounded-full shadow-arrow hover:shadow-info transition-shadow duration-200 bg-transparent border-none p-0 cursor-pointer"
            :style="{ color: brandingStore.accentColor || brandingStore.primaryColor }"
            :aria-label="currentLocale === 'en' ? 'Scroll to top' : 'Nach oben scrollen'"
            @click="scrollToTop"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36" aria-hidden="true">
                <g transform="translate(0 36) rotate(-90)">
                    <circle cx="18" cy="18" r="18" fill="#fff"/>
                    <path d="M0,18.995,9.238,9.5,0,0" transform="translate(14.254 9.503)" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="3"/>
                </g>
            </svg>
        </button>
    </Transition>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useBrandingStore } from '../stores/branding';
import { useLocale } from '../composables/useLocale';

const brandingStore = useBrandingStore();
const { currentLocale } = useLocale();
const isVisible = ref(false);

const SCROLL_THRESHOLD = 300;

function handleScroll() {
    isVisible.value = window.scrollY > SCROLL_THRESHOLD;
}

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
