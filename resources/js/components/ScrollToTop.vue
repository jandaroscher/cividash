<template>
    <Transition name="fade">
        <button
            v-show="isVisible"
            type="button"
            class="fixed bottom-6 right-6 z-40 w-12 h-12 rounded-full shadow-lg flex items-center justify-center text-white transition-opacity duration-300 hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2"
            :style="{ backgroundColor: brandingStore.primaryColor }"
            :aria-label="currentLocale === 'en' ? 'Scroll to top' : 'Nach oben scrollen'"
            @click="scrollToTop"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
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
