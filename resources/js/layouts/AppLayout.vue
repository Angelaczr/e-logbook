<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import NotificationBell from '@/components/notification/NotificationBell.vue';
import type { SharedProps } from '@/types/inertia';

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
const canReview = computed(() => page.props.auth.can.review);
const canViewAnalytics = computed(() => page.props.auth.can.viewAnalytics);
const pendingCount = computed(() => page.props.pendingReviewCount);
const flash = computed(() => page.props.flash);

const menu = computed(() => [
  { label: 'Logbook Saya', href: '/logbooks', badge: 0 },
  ...(canReview.value ? [{ label: 'Review', href: '/reviews', badge: pendingCount.value }] : []),
  ...(canViewAnalytics.value ? [{ label: 'Analitik', href: '/analytics', badge: 0 }] : []),
]);

function isActive(href: string): boolean {
  return page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);
}
</script>

<template>
  <div class="min-h-screen bg-muted/30">
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2"
    >Lewati ke konten</a>

    <header class="border-b bg-background">
      <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
        <Link
          href="/logbooks"
          class="text-lg font-semibold"
        >
          E-Logbook
        </Link>
        <div
          v-if="user"
          class="flex items-center gap-3 text-sm"
        >
          <NotificationBell />
          <span class="text-muted-foreground">{{ user.name }} · {{ user.role_label }}</span>
          <Button
            as-child
            variant="outline"
            size="sm"
          >
            <Link
              href="/logout"
              method="post"
              as="button"
            >
              Keluar
            </Link>
          </Button>
        </div>
      </div>
    </header>

    <div class="mx-auto grid max-w-6xl gap-4 px-4 py-6 lg:grid-cols-[13rem_minmax(0,1fr)] lg:gap-8">
      <nav
        aria-label="Navigasi utama"
        class="flex gap-2 overflow-x-auto lg:flex-col lg:overflow-visible"
      >
        <Link
          v-for="item in menu"
          :key="item.href"
          :href="item.href"
          class="flex min-h-11 items-center justify-between gap-2 whitespace-nowrap rounded-md px-3 text-sm hover:bg-accent"
          :class="isActive(item.href) && 'bg-accent font-medium'"
          :aria-current="isActive(item.href) ? 'page' : undefined"
        >
          {{ item.label }}
          <span
            v-if="item.badge"
            class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground"
          >
            {{ item.badge }}
            <span class="sr-only">menunggu ditinjau</span>
          </span>
        </Link>
      </nav>

      <main
        id="main-content"
        class="min-w-0 space-y-4"
      >
        <Alert v-if="flash.success">
          <AlertDescription role="status">
            {{ flash.success }}
          </AlertDescription>
        </Alert>
        <Alert
          v-if="flash.error"
          variant="destructive"
        >
          <AlertDescription role="alert">
            {{ flash.error }}
          </AlertDescription>
        </Alert>
        <slot />
      </main>
    </div>
  </div>
</template>
