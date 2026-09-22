import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    server: {
        cors: {
            origin: [
                // Vite default: localhost, 127.0.0.1, [::1] with any port
                /^https?:\/\/(?:(?:[^:]+\.)?localhost|127\.0\.0\.1|\[::1\])(?::\d+)?$/,
                // DDEV subdomains (e.g. regensburg.open-source-dashboard.ddev.site)
                /^https?:\/\/[\w-]+(?:\.[\w-]+)*\.ddev\.site$/,
            ],
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: (tag) => ['dotlottie-wc'].includes(tag),
                },
            },
        }),
        tailwindcss(),
    ],
});
