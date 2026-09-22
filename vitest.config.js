import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        vue({
            template: {
                compilerOptions: {
                    isCustomElement: (tag) => ['dotlottie-wc'].includes(tag),
                },
            },
        }),
    ],
    test: {
        environment: 'happy-dom',
        globals: true,
        setupFiles: ['./tests/js/setup.js'],
        include: ['tests/js/**/*.test.js'],
        exclude: ['tests/e2e/**'],
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
});
