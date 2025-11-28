import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import { useBrandingStore } from './stores/branding';

function mountTileExplorer(el) {
    if (!el || el.dataset.mounted === '1') {
        return;
    }

    let props = {};

    if (el.dataset.props) {
        try {
            props = JSON.parse(el.dataset.props);
        } catch (error) {
            console.error('Failed to parse TileExplorer props:', error);
        }
    }

    const app = createApp(App, props);
    const pinia = createPinia();

    app.use(pinia);

    const branding = useBrandingStore(pinia);

    branding
        .fetch()
        .catch((error) => {
            console.error('Failed to fetch branding settings for TileAppBlock:', error);
        })
        .finally(() => {
            app.mount(el);
            el.dataset.mounted = '1';
        });
}

function mountIslands() {
    const elements = document.querySelectorAll('[data-vue-component]');

    elements.forEach((el) => {
        const componentName = el.dataset.vueComponent;

        if (componentName === 'TileExplorer') {
            mountTileExplorer(el);
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountIslands);
} else {
    mountIslands();
}




