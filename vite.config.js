// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

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
                // DDEV subdomains (e.g. demo-city.open-source-dashboard.ddev.site)
                /^https?:\/\/[\w-]+(?:\.[\w-]+)*\.ddev\.site$/,
            ],
        },
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin/dotlottie.js',
                'resources/css/admin-fonts.css',
                'resources/css/admin-contrast.css',
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
