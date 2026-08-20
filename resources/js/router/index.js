import { createRouter, createWebHistory } from 'vue-router';
import { supportedLocales, defaultLocale } from '../utils/locale';
import { useHeaderStore } from '../stores/header';

// Lazy-load page components for better performance
const HomePage = () => import('../components/pages/HomePage.vue');
const DynamicPage = () => import('../components/pages/DynamicPage.vue');
const NotFound = () => import('../components/pages/NotFound.vue');
const TilesPage = () => import('../components/pages/TilesPage.vue');
const TileDetailPage = () => import('../components/pages/TileDetailPage.vue');
const ThemingPocPage = () => import('../components/pages/ThemingPocPage.vue');

const routes = [
    {
        path: '/',
        name: 'home',
        component: HomePage,
    },
    {
        path: '/tiles',
        name: 'tiles',
        component: TilesPage,
    },
    {
        path: '/en/tiles',
        name: 'tiles-en',
        component: TilesPage,
        meta: { locale: 'en' },
    },
    {
        path: '/tiles/:slug',
        name: 'tile-detail',
        component: TileDetailPage,
    },
    {
        path: '/en',
        name: 'home-en',
        component: HomePage,
        meta: { locale: 'en' },
    },
    {
        path: '/en/tiles/:slug',
        name: 'tile-detail-en',
        component: TileDetailPage,
        meta: { locale: 'en' },
    },
    {
        path: '/en/:slug+',
        name: 'page-en',
        component: DynamicPage,
        meta: { locale: 'en' },
    },
    // Dev-only demo route for the theming-poc comparison, gated
    // below in beforeEach so it never resolves in production builds. Must
    // come before the `/:slug+` catch-all below, which would otherwise
    // swallow this path as a German page slug first.
    {
        path: '/theming-poc',
        name: 'theming-poc',
        component: ThemingPocPage,
    },
    {
        path: '/:slug+',
        name: 'page',
        component: DynamicPage,
        meta: { locale: 'de' },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: NotFound,
    },
];

const router = createRouter({
    history: createWebHistory('/'),
    routes,
});

// Router guard for locale handling
router.beforeEach((to, from, next) => {
    // Dev-only demo route: never reachable in a production build.
    if (to.name === 'theming-poc' && !import.meta.env.DEV) {
        return next('/');
    }

    // Redirect /en/ routes to German equivalent when English translation is disabled
    if (/^\/en(\/|$)/.test(to.path)) {
        const headerStore = useHeaderStore();
        if (!headerStore.englishTranslationActive) {
            const dePath = to.path.replace(/^\/en\/?/, '/') || '/';
            return next({ path: dePath, query: to.query, hash: to.hash });
        }
    }

    // Extract locale from route meta or path
    let locale = to.meta?.locale;

    if (!locale) {
        // Try to extract from path
        if (/^\/en(\/|$)/.test(to.path)) {
            locale = 'en';
        } else {
            locale = 'de';
        }
    }

    // Validate locale
    if (!supportedLocales.includes(locale)) {
        // Try localStorage as fallback
        if (typeof window !== 'undefined') {
            const stored = localStorage.getItem('locale');
            if (stored && supportedLocales.includes(stored)) {
                locale = stored;
            } else {
                locale = defaultLocale;
            }
        } else {
            locale = defaultLocale;
        }
    }

    // Store locale in route meta for components to access
    to.meta = { ...to.meta, locale };

    // Persist locale in localStorage
    if (typeof window !== 'undefined') {
        localStorage.setItem('locale', locale);
    }

    next();
});

export default router;
