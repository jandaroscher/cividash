<template>
    <main role="main" aria-labelledby="not-found-title" class="py-12 md:py-16">
        <div class="container">
            <h1
                id="not-found-title"
                class="content-heading mb-6 hyphens-auto"
            >
                404 - {{ messages.title }}
            </h1>
            <p class="text-theme-base text-gray-600 mb-6">
                {{ messages.description }}
            </p>
            <RouterLink
                :to="locale === 'en' ? '/en' : '/'"
                class="inline-block font-semibold underline transition-colors duration-200"
                style="color: var(--link-color, var(--accent-color, #E30613));"
                @mouseenter="$event.target.style.color = 'var(--link-hover-color, var(--accent-color-dark, #891F00))'"
                @mouseleave="$event.target.style.color = 'var(--link-color, var(--accent-color, #E30613))'"
            >
                {{ messages.homeLink }}
            </RouterLink>
        </div>
    </main>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useLocale } from '../../composables/useLocale';

const route = useRoute();
const { currentLocale } = useLocale();

const locale = computed(() => route.meta?.locale || currentLocale.value);

const messages = computed(() => ({
    title: locale.value === 'en' ? 'Page Not Found' : 'Seite nicht gefunden',
    description: locale.value === 'en'
        ? 'The page you are looking for does not exist.'
        : 'Die gesuchte Seite existiert nicht.',
    homeLink: locale.value === 'en' ? 'Go to Home' : 'Zur Startseite',
}));
</script>
