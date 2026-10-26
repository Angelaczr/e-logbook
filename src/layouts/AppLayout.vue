<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import PermissionNavigation from '@/shared/components/PermissionNavigation.vue';
import { appRoutes } from '@/app/router/routes';
import { useAuth } from '@/shared/hooks/useAuth';
import type { AppRoute } from '@/app/router/routes';

const auth = useAuth();
const page = usePage();
function hasPermission(route: AppRoute): route is AppRoute & { permission: NonNullable<AppRoute['permission']> } {
  return route.permission !== undefined;
}

const navigationItems = computed(() => appRoutes.filter(hasPermission).map((route) => ({
  label: route.name,
  href: route.path,
  permission: route.permission,
})));
</script>

<template>
  <div class="min-h-screen bg-[var(--color-background)]">
    <header class="border-b border-[var(--color-border)] bg-[var(--color-surface)]">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a
          href="/"
          class="font-semibold text-[var(--color-text)]"
        >E-Logbook</a>
        <span
          v-if="auth.user"
          class="text-sm text-[var(--color-text-muted)]"
        >{{ auth.user.name }}</span>
      </div>
    </header>
    <div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 sm:px-6 lg:grid-cols-[220px_1fr] lg:px-8">
      <aside
        v-if="navigationItems.length"
        class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-3"
      >
        <PermissionNavigation :items="navigationItems" />
      </aside>
      <main
        id="main-content"
        class="min-w-0"
        :aria-label="String(page.component)"
      >
        <slot />
      </main>
    </div>
  </div>
</template>
