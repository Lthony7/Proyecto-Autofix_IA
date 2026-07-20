import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import ui from '@nuxt/ui/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        ui(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    build: {
        chunkSizeWarningLimit: 600,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    const moduleId = id.replaceAll('\\', '/');

                    if (moduleId.includes('/node_modules/@nuxt/ui/') || moduleId.includes('/node_modules/reka-ui/')) return 'ui';
                    if (moduleId.includes('/node_modules/@internationalized/')) return 'internationalized';
                    if (moduleId.includes('/node_modules/@vueuse/')) return 'vueuse';
                    if (moduleId.includes('/node_modules/@iconify/')) return 'iconify';
                    if (moduleId.includes('/node_modules/@inertiajs/')) return 'inertia';
                    if (moduleId.includes('/node_modules/vue-toastification/')) return 'toast';
                    if (moduleId.includes('/node_modules/ziggy-js/')) return 'ziggy';
                    if (moduleId.includes('/node_modules/vue-router/')) return 'vue-router';
                    if (moduleId.includes('/node_modules/vue/') || moduleId.includes('/node_modules/@vue/')) return 'vue';
                },
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
