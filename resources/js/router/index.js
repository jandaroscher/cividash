import { createRouter, createWebHistory } from 'vue-router';
import { supportedLocales, defaultLocale } from '../utils/locale';

// Lazy-load page components for better performance
const HomePage = () => import('../components/pages/HomePage.vue');
const DynamicPage = () => import('../components/pages/DynamicPage.vue');
const NotFound = () => import('../components/pages/NotFound.vue');
const TilesPage = () => import('../components/pages/TilesPage.vue');
const TileDetailPage = () => import('../components/pages/TileDetailPage.vue');

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
        path: '/en/:slug',
        name: 'page-en',
        component: DynamicPage,
        meta: { locale: 'en' },
    },
    {
        path: '/:slug',
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
    // Extract locale from route meta or path
    let locale = to.meta?.locale;
    
    if (!locale) {
        // Try to extract from path
        if (to.path.startsWith('/en')) {
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
