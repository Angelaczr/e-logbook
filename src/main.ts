import { createApp, defineComponent, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { VueQueryPlugin } from '@tanstack/vue-query';
import { queryClient } from '@/app/providers/QueryProvider';
import ErrorBoundary from '@/app/providers/ErrorBoundary.vue';
import AuthProvider from '@/app/providers/AuthProvider.vue';
import PermissionProvider from '@/app/providers/PermissionProvider.vue';
import { isMockApiEnabled } from '@/app/config/runtime';
import { resolvePage } from '@/app/router/pageResolver';
import '@/styles/app.css';

async function start(): Promise<void> {
  if (isMockApiEnabled) {
    const { worker } = await import('@/mocks/browser');
    await worker.start();
  }

  await createInertiaApp({
    resolve: resolvePage,
    setup({ el, App, props, plugin }) {
      const Root = defineComponent({
        setup() {
          return () => h(ErrorBoundary, null, {
            default: () => h(AuthProvider, null, {
              default: () => h(PermissionProvider, null, {
                default: () => h(App, props),
              }),
            }),
          });
        },
      });

      createApp(Root).use(plugin).use(VueQueryPlugin, { queryClient }).mount(el);
    },
    progress: { color: '#1d4ed8' },
  });
}

void start();
