<template>
    <Teleport to="body">
        <aside
            ref="sidebar"
            @keydown.esc="closeOverlay"
            @click.self="closeOverlay"
            tabindex="0"
            id="default-sidebar"
            class="fixed z-40 w-full top-0 left-0 h-dvh bg-[rgba(0,0,0,.4)] transition-all"
            :class="{ 'opacity-0 !-z-[1]': !overlayStore.open }"
            aria-label="Sidebar"
        >
            <div
                ref="sidebarContainer"
                @scroll.passive="onScroll"
                class="h-full overflow-y-auto bg-white w-full lg:w-[62%] duration-150 lg:max-w-[1192px] transition-transform top-0 right-0 absolute overscroll-contain"
                :class="[!overlayStore.open ? 'translate-x-full' : 'translate-x-0']"
            >
                <div v-if="overlayStore.tile && overlayStore.open" class="h-full">
                    <OverlayHeader :tile="overlayStore.tile" @close="closeOverlay" />
                    <OverlayContent :tile="overlayStore.tile" :data="overlayStore.data" />
                </div>
            </div>
        </aside>

        <button
            v-show="showScrollTop"
            @click="scrollToTop()"
            type="button"
            aria-label="Scroll to top"
            class="to-top z-50 cursor-pointer fixed bottom-5 sm:bottom-10 right-5 sm:right-10 rounded-full shadow-arrow hover:shadow-info transition-shadow duration-200 bg-transparent border-none p-0"
        >
            <img
                alt=""
                class="w-9 h-9"
                :src="arrowTopUrl"
                width="36"
                height="36"
                loading="lazy"
            />
        </button>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { useOverlayStore } from '../../stores/overlay.js';
import OverlayHeader from './parts/Header.vue';
import OverlayContent from './parts/Content.vue';

const overlayStore = useOverlayStore();

const sidebar = ref(null);
const sidebarContainer = ref(null);
const showScrollTop = ref(false);

const arrowTopUrl = computed(() => new URL('/assets/images/arrow-top.svg', window.location.origin).href);

function closeOverlay() {
    if (overlayStore.open) {
        overlayStore.closeOverlay();
    }
}

function scrollToTop() {
    if (sidebarContainer.value) {
        sidebarContainer.value.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }
}

function onScroll(e) {
    const offset = 100;
    const scrollTop = e.target.scrollTop;
    showScrollTop.value = scrollTop >= offset;
}

// Focus handling
let previousActiveElement = null;

let anchorClickHandler = null;

onMounted(() => {
    if (overlayStore.open && sidebar.value) {
        previousActiveElement = document.activeElement;
        sidebar.value.focus();
    }
    
    // Setup smooth scroll for anchor links
    setupSmoothScroll();
});

onBeforeUnmount(() => {
    if (anchorClickHandler && sidebarContainer.value) {
        sidebarContainer.value.removeEventListener('click', anchorClickHandler);
    }
});

function setupSmoothScroll() {
    // Handle anchor link clicks for smooth scrolling
    anchorClickHandler = (e) => {
        const anchor = e.target.closest('a[href^="#"]');
        if (anchor && anchor.getAttribute('href').startsWith('#')) {
            const targetId = anchor.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            if (targetElement && sidebarContainer.value) {
                e.preventDefault();
                const containerRect = sidebarContainer.value.getBoundingClientRect();
                const targetRect = targetElement.getBoundingClientRect();
                const scrollTop = sidebarContainer.value.scrollTop + (targetRect.top - containerRect.top) - 20; // 20px offset
                sidebarContainer.value.scrollTo({
                    top: scrollTop,
                    behavior: 'smooth',
                });
            }
        }
    };
    
    // Use event delegation on the sidebar container
    nextTick(() => {
        if (sidebarContainer.value) {
            sidebarContainer.value.addEventListener('click', anchorClickHandler);
        }
    });
}

onBeforeUnmount(() => {
    if (previousActiveElement) {
        previousActiveElement.focus();
    }
});

watch(
    () => overlayStore.open,
    (isOpen) => {
        if (isOpen) {
            previousActiveElement = document.activeElement;
            setTimeout(() => {
                if (sidebar.value) {
                    sidebar.value.focus();
                }
            }, 100);
        } else if (previousActiveElement) {
            previousActiveElement.focus();
            previousActiveElement = null;
        }
    }
);
</script>

<style scoped>
.shadow-arrow {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.shadow-info {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.shadow-header {
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.shadow-info-close {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}
</style>

