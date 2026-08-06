import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from './Layouts/AppLayout.vue';

// Pages live either in the app shell (resources/js/Pages) or inside a module
// (Modules/{Module}/resources/js/Pages). A name like "Web::HomePage" resolves
// to the module file; a plain name like "Auth/Login" resolves to the shell.
const shellPages = import.meta.glob('./Pages/**/*.vue');
const modulePages = import.meta.glob('../../Modules/*/resources/js/Pages/**/*.vue');

function loaderFor(name) {
    const marker = name.indexOf('::');
    if (marker !== -1) {
        const module = name.slice(0, marker);
        const page = name.slice(marker + 2);
        return modulePages[`../../Modules/${module}/resources/js/Pages/${page}.vue`];
    }
    return shellPages[`./Pages/${name}.vue`];
}

createInertiaApp({
    title: (title) => (title ? `${title} · BC EQA` : 'BC EQA'),
    resolve: async (name) => {
        const loader = loaderFor(name);
        if (!loader) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        const module = await loader();
        const component = module.default ?? module;
        // Give every page the shared shell unless it is an auth screen or
        // already declares its own layout.
        if (component.layout === undefined && !name.startsWith('Auth/')) {
            component.layout = AppLayout;
        }
        return component;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: { color: '#fcba19' },
});
