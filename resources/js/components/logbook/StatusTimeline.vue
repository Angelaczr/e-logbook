<script setup lang="ts">
import { formatDateTime } from '@/lib/logbookTime';
import type { StatusHistoryEntry } from '@/types/logbook';

defineProps<{
  histories: StatusHistoryEntry[];
}>();
</script>

<template>
  <p
    v-if="histories.length === 0"
    class="text-sm text-muted-foreground"
  >
    Belum ada riwayat status.
  </p>
  <ol
    v-else
    class="relative space-y-4 border-l pl-5"
    aria-label="Riwayat status logbook"
  >
    <li
      v-for="entry in histories"
      :key="entry.id"
      class="relative"
    >
      <span
        class="absolute -left-[1.6rem] top-1.5 size-2.5 rounded-full border-2 border-background bg-primary"
        aria-hidden="true"
      />
      <p class="text-sm font-medium">
        <template v-if="entry.from_status_label">
          {{ entry.from_status_label }} → {{ entry.to_status_label }}
        </template>
        <template v-else>
          {{ entry.to_status_label }}
        </template>
      </p>
      <p class="text-xs text-muted-foreground">
        {{ entry.actor ?? 'Sistem' }} · <time :datetime="entry.created_at">{{ formatDateTime(entry.created_at) }}</time>
      </p>
      <p
        v-if="entry.notes"
        class="mt-1 whitespace-pre-line rounded-md bg-muted px-3 py-2 text-sm"
      >
        {{ entry.notes }}
      </p>
    </li>
  </ol>
</template>
