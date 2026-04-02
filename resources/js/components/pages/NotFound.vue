<template>
    <main role="main" aria-labelledby="not-found-title" class="py-12 md:py-16">
        <div class="container">
            <h1
                id="not-found-title"
                class="text-theme-h1 text-black font-bold mb-6 hyphens-auto"
            >
                404 - {{ messages.title }}
            </h1>
            <p class="text-theme-base text-gray-600 mb-6">
                {{ messages.description }}
            </p>
            <RouterLink
                to="/"
                class="inline-block text-accent hover:text-accent-dark transition-colors duration-200 font-semibold"
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
