import '../css/app.css';
import { createApp, h } from 'vue';
import { createInertiaApp, router as inertiaRouter } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import { Ziggy } from './ziggy';
import { createMemoryHistory, createRouter } from 'vue-router';
import Toast from 'vue-toastification';
import 'vue-toastification/dist/index.css';

// Importar layout de Nuxt UI Dashboard
import DefaultLayout from './layouts/default.vue';

// Importar composables de compatibilidad con Nuxt
import * as nuxtCompat from './composables/nuxt-compat';

// Importar plugin de Nuxt UI
import NuxtUIPlugin from './plugins/nuxt-ui';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Nuxt UI consumes vue-router's current route internally. Keep this router in
// memory so it cannot compete with Inertia for the browser history.
const uiRouter = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { render: () => null } }],
});

if (typeof window !== 'undefined') {
    void uiRouter.replace(`${window.location.pathname}${window.location.search}${window.location.hash}`);
}

inertiaRouter.on('navigate', (event) => {
    const url = event.detail.page.url;
    if (uiRouter.currentRoute.value.fullPath !== url) {
        void uiRouter.replace(url);
    }
});

// Hacer disponibles los composables de Nuxt globalmente
if (typeof window !== 'undefined') {
    window.useToast = nuxtCompat.useToast;
    window.useTemplateRef = nuxtCompat.useTemplateRef;
    window.resolveComponent = nuxtCompat.resolveComponent;
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const page = resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue'));
        // Aplicar el layout de Nuxt UI a todas las páginas, excepto si la página define layout: null
        page.then((module) => {
            if (module.default.layout === undefined) {
                module.default.layout = DefaultLayout;
            }
        });
        return page;
    },
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(uiRouter)
            .use(Toast)
            .use(NuxtUIPlugin)
            .use(ZiggyVue, Ziggy)
            .mount(el);
    },
    progress: {
        color: '#10b981',
    },
});
