<template>
    <header 
        class="h-30 md:h-[192px] shadow-header"
        :style="{ backgroundColor: brandingStore.headerBackgroundColor || '#FFFFFF' }"
    >
        <div class="container h-full flex items-center space-x-2">
            <!-- Logo -->
            <RouterLink 
                v-if="brandingStore.logoUrl" 
                :to="homePath" 
                class="flex-1"
                :aria-label="locale === 'en' ? 'Home' : 'Startseite'"
            >
                <img
                    :src="brandingStore.logoUrl"
                    :alt="siteName"
                    class="logo md:w-[190px]"
                    width="140"
                    height="68"
                />
            </RouterLink>
            <RouterLink 
                v-else 
                :to="homePath"
                class="flex-1 text-2xl md:text-3xl font-bold"
                :style="{ color: brandingStore.primaryColor || '#1976d2' }"
            >
                {{ siteName }}
            </RouterLink>

            <!-- Mobile Menu Toggle -->
            <button
                v-if="hasVisibleNavigation"
                ref="mobileMenuToggle"
                class="mobile-menu-toggle md:hidden ml-auto"
                @click="openMobileMenu"
                :aria-label="locale === 'en' ? 'Open menu' : 'Menü öffnen'"
                style="color: var(--nav-text-color);"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>

            <!-- Desktop Navigation -->
            <nav
                v-if="hasVisibleNavigation"
                class="desktop-nav ml-auto mt-10 md:mt-0 md:mb-12 flex space-x-2 sm:space-x-6 md:space-x-8 items-end"
            >
                <template v-for="(item, index) in headerStore.navigationItems" :key="index">
                    <div 
                        v-if="headerStore.dropdownEnabled && item.children && item.children.length > 0"
                        class="relative nav-item has-dropdown"
                    >
                        <RouterLink 
                            :to="item.url" 
                            class="text-sm sm:text-base md:text-2xl md:font-bold transition-colors duration-200 nav-link" 
                            style="color: var(--nav-text-color);"
                            :aria-expanded="dropdownExpanded[index] || false"
                            @focus="dropdownExpanded[index] = true"
                            @blur="handleDropdownBlur(index, $event)"
                        >
                            {{ item.label }}
                        </RouterLink>
                        <div class="dropdown-menu absolute top-full left-0 mt-2 bg-white shadow-lg rounded-md py-2 min-w-[200px] hidden">
                            <RouterLink
                                v-for="(child, childIndex) in item.children"
                                :key="childIndex"
                                :to="child.url"
                                class="block px-4 py-2 text-base transition-colors duration-200 dropdown-link"
                                style="color: var(--link-color, #E30613);"
                            >
                                {{ child.label }}
                            </RouterLink>
                        </div>
                    </div>
                    <div v-else class="relative nav-item">
                        <RouterLink 
                            :to="item.url" 
                            class="text-sm sm:text-base md:text-2xl md:font-bold transition-colors duration-200 nav-link" 
                            style="color: var(--nav-text-color);"
                        >
                            {{ item.label }}
                        </RouterLink>
                    </div>
                </template>

                <!-- Language Switcher -->
                <template v-if="headerStore.englishTranslationActive">
                    <select 
                        class="language-switcher-mobile cursor-pointer text-sm sm:text-base md:text-2xl md:font-bold relative bg-transparent" 
                        style="color: var(--nav-text-color); border: none; outline: none;"
                        :value="currentPath"
                        @change="handleLanguageChange($event)"
                    >
                        <option :value="deUrl">DE</option>
                        <option :value="enUrl">EN</option>
                    </select>
                    <div class="language-switcher-desktop text-base sm:text-base md:text-2xl md:font-bold relative flex items-center">
                        <button
                            type="button"
                            @click="switchLocale('de')"
                            class="transition-colors duration-200 lang-switch-btn"
                            :style="{ color: locale === 'de' ? 'var(--nav-text-color)' : 'var(--nav-text-color-inactive)' }"
                        >
                            DE
                        </button>
                        <span style="color: var(--nav-text-color-inactive);">&nbsp;/&nbsp;</span>
                        <button
                            type="button"
                            @click="switchLocale('en')"
                            class="transition-colors duration-200 lang-switch-btn"
                            :style="{ color: locale === 'en' ? 'var(--nav-text-color)' : 'var(--nav-text-color-inactive)' }"
                        >
                            EN
                        </button>
                    </div>
                </template>
            </nav>

            <!-- Mobile Menu -->
            <div
                v-if="hasVisibleNavigation"
                id="mobile-menu"
                :class="['mobile-menu', 'fixed', 'top-0', 'left-0', 'w-full', 'h-full', 'bg-white', 'z-50', 'pt-20', 'px-4', { 'open': mobileMenuOpen }]"
                @keydown="handleMenuKeydown"
                tabindex="-1"
                :aria-hidden="!mobileMenuOpen"
            >
                <button 
                    ref="mobileMenuClose"
                    class="absolute top-4 right-4" 
                    @click="closeMobileMenu"
                    :aria-label="locale === 'en' ? 'Close menu' : 'Menü schließen'"
                    style="color: var(--nav-text-color);"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                <nav class="flex flex-col space-y-4">
                    <template v-for="(item, index) in headerStore.navigationItems" :key="index">
                        <div>
                            <RouterLink 
                                :to="item.url" 
                                class="text-lg font-bold transition-colors duration-200 block py-2" 
                                style="color: var(--nav-text-color);"
                                @click="closeMobileMenu"
                            >
                                {{ item.label }}
                            </RouterLink>
                            <div 
                                v-if="headerStore.dropdownEnabled && item.children && item.children.length > 0"
                                class="pl-4 mt-2 space-y-2"
                            >
                                <RouterLink
                                    v-for="(child, childIndex) in item.children"
                                    :key="childIndex"
                                    :to="child.url"
                                    class="text-base transition-colors duration-200 block py-1"
                                    style="color: var(--nav-text-color);"
                                    @click="closeMobileMenu"
                                >
                                    {{ child.label }}
                                </RouterLink>
                            </div>
                        </div>
                    </template>
                    <div
                        v-if="headerStore.englishTranslationActive"
                        class="pt-4 border-t"
                        :style="{ borderColor: 'var(--divider-color)' }"
                    >
                        <div class="flex items-center space-x-2">
                            <span class="text-base font-bold" style="color: var(--nav-text-color);">
                                {{ locale === 'en' ? 'Language' : 'Sprache' }}
                            </span>
                            <select 
                                class="text-base font-bold bg-transparent border-none outline-none" 
                                style="color: var(--nav-text-color);"
                                :value="currentPath"
                                @change="handleLanguageChange($event)"
                            >
                                <option :value="deUrl">DE</option>
                                <option :value="enUrl">EN</option>
                            </select>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useHeaderStore } from '../../stores/header';
import { useBrandingStore } from '../../stores/branding';
import { useLocale } from '../../composables/useLocale';
import { usePagesStore } from '../../stores/pages';

const route = useRoute();
const router = useRouter();
const headerStore = useHeaderStore();
const brandingStore = useBrandingStore();
const pagesStore = usePagesStore();
const { currentLocale, setLocale, getTranslatedSlug } = useLocale();

const locale = currentLocale;

const hasVisibleNavigation = computed(() => {
    if (headerStore.navigationItems.length > 0) return true;
    return headerStore.englishTranslationActive;
});
const mobileMenuOpen = ref(false);
const mobileMenuToggle = ref(null);
const mobileMenuClose = ref(null);
const dropdownExpanded = ref({});

const siteName = computed(() => headerStore.siteName);

// Locale-aware home path
const homePath = computed(() => locale.value === 'en' ? '/en' : '/');

const currentPath = computed(() => route.path);

// URLs for language switcher (with translated slugs)
const deUrl = ref('/');
const enUrl = ref('/en');

// Update URLs when route changes
async function updateLocaleUrls() {
    if (route.path === '/' || route.path === '/en') {
        deUrl.value = '/';
        enUrl.value = '/en';
        return;
    }
    
    const translatedDe = await getTranslatedSlug('de');
    const translatedEn = await getTranslatedSlug('en');
    
    deUrl.value = translatedDe || route.path.replace(/^\/en/, '') || '/';
    enUrl.value = translatedEn || (route.path.startsWith('/en') ? route.path : `/en${route.path}`);
}

// Watch route changes and update URLs
watch(
    () => route.path,
    () => {
        updateLocaleUrls();
    },
    { immediate: true }
);


// Handle language change from select
async function handleLanguageChange(event) {
    const selectedUrl = event.target.value;
    const newLocale = selectedUrl.startsWith('/en') ? 'en' : 'de';
    await switchLocale(newLocale);
}

// Switch locale
async function switchLocale(newLocale) {
    await setLocale(newLocale, true);
    closeMobileMenu();
}

// Focus trap and keyboard handling for mobile menu
function openMobileMenu() {
    mobileMenuOpen.value = true;
    nextTick(() => {
        mobileMenuClose.value?.focus();
    });
}

function closeMobileMenu() {
    mobileMenuOpen.value = false;
    // Return focus to toggle button
    nextTick(() => {
        mobileMenuToggle.value?.focus();
    });
}

function handleMenuKeydown(event) {
    if (event.key === 'Escape') {
        closeMobileMenu();
    }
}

// Handle dropdown blur - only collapse if focus is not moving to a child element
function handleDropdownBlur(index, event) {
    // Use requestAnimationFrame to check focus after browser processes focus change
    requestAnimationFrame(() => {
        const dropdownElement = event.currentTarget?.parentElement;
        if (dropdownElement && !dropdownElement.contains(document.activeElement)) {
            dropdownExpanded.value[index] = false;
        }
    });
}

// Watch locale changes and refetch header data
watch(
    locale,
    (newLocale) => {
        headerStore.fetchConfig(newLocale);
    },
    { immediate: true }
);
</script>

<style scoped>
@supports (color: color-mix(in srgb, red, red)) {
    .shadow-header {
        box-shadow: 0 3px 15px color-mix(in srgb, var(--shadow-color, #000) 16%, transparent);
    }
}

@supports not (color: color-mix(in srgb, red, red)) {
    .shadow-header {
        box-shadow: 0 3px 15px rgba(0, 0, 0, 0.16);
    }
}

/* Dropdown menu styles */
.nav-item.has-dropdown:hover .dropdown-menu,
.nav-item.has-dropdown:focus-within .dropdown-menu {
    display: block;
}

.nav-item.has-dropdown .dropdown-menu {
    z-index: 1000;
    margin-top: 0.5rem;
}

/* Add gap between nav item and dropdown to prevent closing */
.nav-item.has-dropdown::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    height: 0.5rem;
}

/* Mobile menu styles */
.mobile-menu {
    display: none;
}

.mobile-menu.open {
    display: block !important;
}

@media (max-width: 767px) {
    .desktop-nav {
        display: none !important;
    }
    .mobile-menu-toggle {
        display: block;
    }
}

@media (min-width: 768px) {
    .mobile-menu-toggle {
        display: none !important;
    }
    .mobile-menu:not(.open) {
        display: none !important;
    }
    .desktop-nav {
        display: flex !important;
    }
    .language-switcher-desktop {
        display: flex !important;
    }
    .language-switcher-mobile {
        display: none !important;
    }
}

@media (max-width: 767px) {
    .language-switcher-desktop {
        display: none !important;
    }
    .language-switcher-mobile {
        display: block !important;
    }
}

/* Navigation hover styles */
.nav-item .nav-link:hover {
    color: var(--nav-hover-color) !important;
}

.lang-switch-btn:hover {
    color: var(--nav-hover-color) !important;
}

.dropdown-menu .dropdown-link:hover {
    background-color: var(--card-background-color, #F5F5F5) !important;
    color: var(--link-hover-color, #891F00) !important;
}
</style>
