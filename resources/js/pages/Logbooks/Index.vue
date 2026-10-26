<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import LogbookStatusBadge from '@/components/logbook/LogbookStatusBadge.vue';
import { formatDate, formatDuration } from '@/lib/logbookTime';
import { logbookRoutes } from '@/lib/routes';
import type { PaginatedLogbooks, StatusOption } from '@/types/logbook';

const ALL_STATUSES = 'all';

const props = defineProps<{
  logbooks: PaginatedLogbooks;
  filters: { month: string | null; status: string | null };
  statuses: StatusOption[];
}>();

const month = computed(() => props.filters.month ?? '');
const status = computed(() => props.filters.status ?? ALL_STATUSES);
const hasFilter = computed(() => props.filters.month !== null || props.filters.status !== null);

function applyFilters(changes: { month?: string; status?: string }): void {
  const nextMonth = changes.month ?? month.value;
  const nextStatus = changes.status ?? status.value;

  router.get(logbookRoutes.index, {
    month: nextMonth || undefined,
    status: nextStatus === ALL_STATUSES ? undefined : nextStatus,
  }, { preserveState: true, preserveScroll: true, replace: true });
}

function onStatus(value: unknown): void {
  if (typeof value === 'string') {
    applyFilters({ status: value });
  }
}

function goTo(url: string | null): void {
  if (url) {
    router.get(url, {}, { preserveState: true });
  }
}
</script>

<template>
  <Head title="Logbook Saya" />

  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">
        Logbook Saya
      </h1>
      <Button
        as-child
        class="min-h-11"
      >
        <Link :href="logbookRoutes.create">
          <Plus class="size-4" />
          Buat Logbook
        </Link>
      </Button>
    </div>

    <Card>
      <CardContent class="grid gap-4 pt-6 sm:grid-cols-2 lg:grid-cols-3">
        <div class="space-y-1.5">
          <Label for="filter-month">Bulan</Label>
          <Input
            id="filter-month"
            type="month"
            class="min-h-11"
            :model-value="month"
            @update:model-value="applyFilters({ month: String($event) })"
          />
        </div>
        <div class="space-y-1.5">
          <Label for="filter-status">Status</Label>
          <Select
            :model-value="status"
            @update:model-value="onStatus"
          >
            <SelectTrigger
              id="filter-status"
              class="min-h-11 w-full"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="ALL_STATUSES">
                Semua status
              </SelectItem>
              <SelectItem
                v-for="option in statuses"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div class="flex items-end">
          <Button
            v-if="hasFilter"
            variant="ghost"
            class="min-h-11"
            @click="applyFilters({ month: '', status: ALL_STATUSES })"
          >
            Reset filter
          </Button>
        </div>
      </CardContent>
    </Card>

    <Card v-if="logbooks.data.length === 0">
      <CardContent class="space-y-3 py-12 text-center">
        <p class="font-medium">
          {{ hasFilter ? 'Tidak ada logbook yang cocok dengan filter.' : 'Belum ada logbook.' }}
        </p>
        <p class="text-sm text-muted-foreground">
          {{ hasFilter ? 'Ubah atau reset filter untuk melihat data lain.' : 'Mulai dengan membuat logbook untuk hari kerja Anda.' }}
        </p>
      </CardContent>
    </Card>

    <template v-else>
      <Table class="hidden md:table">
        <caption class="sr-only">
          Daftar logbook saya
        </caption>
        <TableHeader>
          <TableRow>
            <TableHead>Tanggal</TableHead>
            <TableHead>Shift</TableHead>
            <TableHead>Status</TableHead>
            <TableHead class="text-right">
              Item
            </TableHead>
            <TableHead class="text-right">
              Total
            </TableHead>
            <TableHead class="text-right">
              <span class="sr-only">Aksi</span>
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="logbook in logbooks.data"
            :key="logbook.id"
          >
            <TableCell>{{ formatDate(logbook.work_date) }}</TableCell>
            <TableCell>{{ logbook.shift }}</TableCell>
            <TableCell>
              <LogbookStatusBadge
                :label="logbook.status_label"
                :color="logbook.status_color"
              />
            </TableCell>
            <TableCell class="text-right">
              {{ logbook.items_count }}
            </TableCell>
            <TableCell class="text-right">
              {{ formatDuration(logbook.total_minutes) }}
            </TableCell>
            <TableCell class="text-right">
              <Button
                as-child
                variant="outline"
                size="sm"
                class="min-h-11"
              >
                <Link :href="logbookRoutes.show(logbook.id)">
                  Lihat<span class="sr-only"> logbook {{ formatDate(logbook.work_date) }}</span>
                </Link>
              </Button>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <ul class="space-y-3 md:hidden">
        <li
          v-for="logbook in logbooks.data"
          :key="logbook.id"
        >
          <Link
            :href="logbookRoutes.show(logbook.id)"
            class="block min-h-11 rounded-lg border bg-card p-4"
          >
            <div class="flex items-start justify-between gap-2">
              <span class="font-medium">{{ formatDate(logbook.work_date) }}</span>
              <LogbookStatusBadge
                :label="logbook.status_label"
                :color="logbook.status_color"
              />
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
              Shift {{ logbook.shift }} · {{ logbook.items_count }} item · {{ formatDuration(logbook.total_minutes) }}
            </p>
          </Link>
        </li>
      </ul>

      <nav
        class="flex items-center justify-between gap-3"
        aria-label="Halaman"
      >
        <Button
          variant="outline"
          class="min-h-11"
          :disabled="!logbooks.links.prev"
          @click="goTo(logbooks.links.prev)"
        >
          Sebelumnya
        </Button>
        <p class="text-sm text-muted-foreground">
          Halaman {{ logbooks.meta.current_page }} dari {{ logbooks.meta.last_page }} · {{ logbooks.meta.total }} logbook
        </p>
        <Button
          variant="outline"
          class="min-h-11"
          :disabled="!logbooks.links.next"
          @click="goTo(logbooks.links.next)"
        >
          Berikutnya
        </Button>
      </nav>
    </template>
  </div>
</template>
