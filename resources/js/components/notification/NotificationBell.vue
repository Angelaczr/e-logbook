<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { formatDateTime } from '@/lib/logbookTime';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types/inertia';

const MAX_BADGE = 99;

const page = usePage<SharedProps>();
const unread = computed(() => page.props.notifications.unread_count);
const items = computed(() => page.props.notifications.items);
const badge = computed(() => (unread.value > MAX_BADGE ? `${MAX_BADGE}+` : String(unread.value)));

function markAllRead(): void {
  router.post('/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
  <Popover>
    <PopoverTrigger as-child>
      <Button
        variant="ghost"
        size="icon"
        class="relative size-11"
        :aria-label="unread > 0 ? `Notifikasi, ${unread} belum dibaca` : 'Notifikasi'"
      >
        <Bell class="size-5" />
        <span
          v-if="unread > 0"
          class="absolute right-1 top-1 min-w-5 rounded-full bg-destructive px-1 text-center text-xs font-medium leading-5 text-destructive-foreground"
          aria-hidden="true"
        >{{ badge }}</span>
      </Button>
    </PopoverTrigger>
    <PopoverContent
      align="end"
      class="w-[min(22rem,calc(100vw-2rem))] p-0"
    >
      <div class="flex items-center justify-between border-b px-4 py-2">
        <h2 class="text-sm font-semibold">
          Notifikasi
        </h2>
        <Button
          variant="ghost"
          size="sm"
          class="min-h-11"
          :disabled="unread === 0"
          @click="markAllRead"
        >
          Tandai semua dibaca
        </Button>
      </div>

      <p
        v-if="items.length === 0"
        class="px-4 py-8 text-center text-sm text-muted-foreground"
      >
        Belum ada notifikasi.
      </p>
      <ul
        v-else
        class="max-h-96 divide-y overflow-y-auto"
      >
        <li
          v-for="item in items"
          :key="item.id"
        >
          <Link
            :href="`/notifications/${item.id}/read`"
            method="post"
            as="button"
            type="button"
            :class="cn('block min-h-11 w-full px-4 py-3 text-left hover:bg-accent', !item.read && 'bg-accent/50')"
          >
            <span class="flex items-start justify-between gap-2">
              <span class="text-sm font-medium">{{ item.title }}</span>
              <span
                v-if="!item.read"
                class="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
              >
                <span class="sr-only">Belum dibaca</span>
              </span>
            </span>
            <span class="mt-0.5 block text-sm text-muted-foreground">{{ item.message }}</span>
            <time
              :datetime="item.created_at"
              class="mt-1 block text-xs text-muted-foreground"
            >{{ formatDateTime(item.created_at) }}</time>
          </Link>
        </li>
      </ul>
    </PopoverContent>
  </Popover>
</template>
