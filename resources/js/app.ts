import '../css/app.css';
import { createApp, h } from 'vue';
import type { DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import AppLayout from '@/layouts/AppLayout.vue';

void createInertiaApp({
  title: (title) => (title ? `${title} - E-Logbook` : 'E-Logbook'),
  resolve: async (name) => {
    const page = await resolvePageComponent<DefineComponent>(
      `./pages/${name}.vue`,
      import.meta.glob<DefineComponent>('./pages/**/*.vue'),
    );
    if (page.default.layout === undefined) {
      page.default.layout = AppLayout;
    }
    return page;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) }).use(plugin).mount(el);
  },
  progress: { color: '#1d4ed8' },
});
