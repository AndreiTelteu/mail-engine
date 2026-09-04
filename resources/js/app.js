import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { accentColor, initializeTheme } from './theme';

initializeTheme();

const appName = document.querySelector('meta[name="application-name"]')?.content ?? 'Mail Engine';
const pages = import.meta.glob('./Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    progress: { color: accentColor(), delay: 200, showSpinner: false },
    resolve: (name) => pages[`./Pages/${name}.vue`](),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
