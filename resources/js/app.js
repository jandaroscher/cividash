import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { useBrandingStore } from './stores/branding';
import { vIntersectionObserver } from '@vueuse/components';
// Import dotlottie-player to register the Web Component
import '@johanaarstein/dotlottie-player';

const app = createApp(App);
const pinia = createPinia();
app.use(pinia);
app.directive('intersection-observer', vIntersectionObserver);

// Fetch branding before mounting
const branding = useBrandingStore();
branding.fetch()
    .then(() => {
        app.mount('#app');
    })
    .catch((error) => {
        console.error('Failed to fetch branding settings, mounting app anyway:', error);
        app.mount('#app');
    });
