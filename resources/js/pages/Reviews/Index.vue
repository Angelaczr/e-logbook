<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import LogbookStatusBadge from '@/components/logbook/LogbookStatusBadge.vue';
import ReviewDetail from '@/components/review/ReviewDetail.vue';
import { formatDate, formatDateTime, formatDuration } from '@/lib/logbookTime';
import { reviewRoutes } from '@/lib/routes';
import type { LogbookDetail, StatusOption } from '@/types/logbook';
import type { NamedOption, PaginatedReviews, ReviewFilters, ReviewSort } from '@/types/review';

const ALL = 'all';
const DEFAULT_STATUS = 'submitted';
const DEFAULT_SORT: ReviewSort = 'date';
const SORT_OPTIONS: { value: ReviewSort; label: string }[] = [
  { value: 'date', label: 'Tanggal kerja (terlama)' },
  { value: 'viewed', label: 'Terakhir dibuka' },
];

const props = defineProps<{
  logbooks: PaginatedReviews;
  filters: ReviewFilters;
  selected: LogbookDetail | null;
  employees: NamedOption[];
  departments: NamedOption[] | null;
  statuses: StatusOption[];
}>();

const checked = ref<Set<number>>(new Set());
const confirmingBulk = ref(false);

const selectableIds = computed(() => props.logbooks.data.filter((row) => row.status === DEFAULT_STATUS).map((row) => row.id));
const allChecked = computed<boolean | 'indeterminate'>(() => {
  if (checked.value.size === 0) {
    return false;
  }
  return checked.value.size === selectableIds.value.length ? true : 'indeterminate';
});
const hasFilter = computed(() => props.filters.user_id !== null || props.filters.department_id !== null
  || props.filters.from !== null || props.filters.to !== null || props.filters.status !== DEFAULT_STATUS);
const sheetOpen = computed(() => props.filters.review !== null && props.selected !== null);

watch(() => props.logbooks.data, () => {
  checked.value = new Set([...checked.value].filter((id) => selectableIds.value.includes(id)));
});

/** Filter hidup di query string supaya bisa di-bookmark; nilai bawaan tidak ditulis. */
function visit(changes: Partial<ReviewFilters>, only?: string[]): void {
  const next = { ...props.filters, ...changes };
  router.get(reviewRoutes.index, {
    user_id: next.user_id ?? undefined,
    department_id: next.department_id ?? undefined,
    from: next.from || undefined,
    to: next.to || undefined,
    status: next.status === DEFAULT_STATUS ? undefined : next.status,
    review: next.review ?? undefined,
    sort: next.sort === DEFAULT_SORT ? undefined : next.sort,
  }, { preserveState: true, preserveScroll: true, replace: true, ...(only ? { only } : {}) });
}

function onSelectFilter(key: 'user_id' | 'department_id', value: unknown): void {
  if (typeof value === 'string') {
    visit({ [key]: value === ALL ? null : Number(value) });
  }
}

function onStatus(value: unknown): void {
  if (typeof value === 'string') {
    visit({ status: value });
  }
}

function onSort(value: unknown): void {
  if (value === 'date' || value === 'viewed') {
    visit({ sort: value });
  }
}

function resetFilters(): void {
  visit({ user_id: null, department_id: null, from: null, to: null, status: DEFAULT_STATUS });
}

function toggle(id: number, value: boolean | 'indeterminate'): void {
  const next = new Set(checked.value);
  if (value === true) {
    next.add(id);
  } else {
    next.delete(id);
  }
  checked.value = next;
}

function toggleAll(value: boolean | 'indeterminate'): void {
  checked.value = value === true ? new Set(selectableIds.value) : new Set();
}

function bulkApprove(): void {
  confirmingBulk.value = false;
  router.post(reviewRoutes.bulkApprove, { ids: [...checked.value] }, {
    preserveScroll: true,
    onSuccess: () => { checked.value = new Set(); },
  });
}

function pageTo(url: string | null): void {
  if (url) {
    router.get(url, {}, { preserveState: true });
  }
}
</script>

<template>
  <Head title="Review Logbook" />

  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-semibold">
        Review Logbook
      </h1>
      <Button
        class="min-h-11"
        :disabled="checked.size === 0"
        @click="confirmingBulk = true"
      >
        Setujui terpilih ({{ checked.size }})
      </Button>
    </div>

    <Card>
      <CardContent class="grid gap-4 pt-6 sm:grid-cols-2 lg:grid-cols-3">
        <div class="space-y-1.5">
          <Label for="filter-employee">Karyawan</Label>
          <Select
            :model-value="filters.user_id === null ? ALL : String(filters.user_id)"
            @update:model-value="onSelectFilter('user_id', $event)"
          >
            <SelectTrigger
              id="filter-employee"
              class="min-h-11 w-full"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="ALL">
                Semua karyawan
              </SelectItem>
              <SelectItem
                v-for="employee in employees"
                :key="employee.id"
                :value="String(employee.id)"
              >
                {{ employee.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div
          v-if="departments"
          class="space-y-1.5"
        >
          <Label for="filter-department">Departemen</Label>
          <Select
            :model-value="filters.department_id === null ? ALL : String(filters.department_id)"
            @update:model-value="onSelectFilter('department_id', $event)"
          >
            <SelectTrigger
              id="filter-department"
              class="min-h-11 w-full"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="ALL">
                Semua departemen
              </SelectItem>
              <SelectItem
                v-for="department in departments"
                :key="department.id"
                :value="String(department.id)"
              >
                {{ department.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-1.5">
          <Label for="sort-by">Urutkan</Label>
          <Select
            :model-value="filters.sort"
            @update:model-value="onSort"
          >
            <SelectTrigger
              id="sort-by"
              class="min-h-11 w-full"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem
                v-for="option in SORT_OPTIONS"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="space-y-1.5">
          <Label for="filter-status">Status</Label>
          <Select
            :model-value="filters.status"
            @update:model-value="onStatus"
          >
            <SelectTrigger
              id="filter-status"
              class="min-h-11 w-full"
            >
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
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

        <fieldset class="grid grid-cols-2 gap-2">
          <legend class="mb-1.5 text-sm font-medium">
            Rentang tanggal
          </legend>
          <Input
            type="date"
            class="min-h-11"
            aria-label="Dari tanggal"
            :model-value="filters.from ?? ''"
            :max="filters.to ?? undefined"
            @update:model-value="visit({ from: String($event) || null })"
          />
          <Input
            type="date"
            class="min-h-11"
            aria-label="Sampai tanggal"
            :model-value="filters.to ?? ''"
            :min="filters.from ?? undefined"
            @update:model-value="visit({ to: String($event) || null })"
          />
        </fieldset>

        <div
          v-if="hasFilter"
          class="sm:col-span-2 lg:col-span-3"
        >
          <Button
            variant="ghost"
            class="min-h-11"
            @click="resetFilters"
          >
            Reset filter
          </Button>
        </div>
      </CardContent>
    </Card>

    <Card v-if="logbooks.data.length === 0">
      <CardContent class="space-y-2 py-12 text-center">
        <p class="font-medium">
          {{ hasFilter ? 'Tidak ada logbook yang cocok dengan filter.' : 'Tidak ada logbook yang menunggu review.' }}
        </p>
        <p class="text-sm text-muted-foreground">
          {{ hasFilter ? 'Ubah atau reset filter untuk melihat data lain.' : 'Logbook bawahan Anda yang dikirim akan muncul di sini.' }}
        </p>
      </CardContent>
    </Card>

    <template v-else>
      <Table>
        <caption class="sr-only">
          Daftar logbook untuk direview
        </caption>
        <TableHeader>
          <TableRow>
            <TableHead class="w-12">
              <Checkbox
                aria-label="Pilih semua logbook yang menunggu review"
                :model-value="allChecked"
                :disabled="selectableIds.length === 0"
                @update:model-value="toggleAll"
              />
            </TableHead>
            <TableHead>Tanggal</TableHead>
            <TableHead>Karyawan</TableHead>
            <TableHead class="text-right">
              Total
            </TableHead>
            <TableHead class="text-right">
              Item
            </TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Dikirim</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow
            v-for="row in logbooks.data"
            :key="row.id"
            class="cursor-pointer"
            @click="visit({ review: row.id }, ['selected', 'filters'])"
          >
            <TableCell @click.stop>
              <Checkbox
                :aria-label="`Pilih logbook ${row.employee.name} ${row.work_date}`"
                :model-value="checked.has(row.id)"
                :disabled="row.status !== DEFAULT_STATUS"
                @update:model-value="toggle(row.id, $event)"
              />
            </TableCell>
            <TableCell>
              <button
                type="button"
                class="min-h-11 text-left font-medium underline-offset-2 hover:underline"
                @click.stop="visit({ review: row.id }, ['selected', 'filters'])"
              >
                {{ formatDate(row.work_date) }}
              </button>
            </TableCell>
            <TableCell>{{ row.employee.name }}</TableCell>
            <TableCell class="text-right">
              {{ formatDuration(row.total_minutes) }}
            </TableCell>
            <TableCell class="text-right">
              {{ row.items_count }}
            </TableCell>
            <TableCell>
              <LogbookStatusBadge
                :label="row.status_label"
                :color="row.status_color"
              />
            </TableCell>
            <TableCell>{{ row.submitted_at ? formatDateTime(row.submitted_at) : '–' }}</TableCell>
          </TableRow>
        </TableBody>
      </Table>

      <nav
        class="flex items-center justify-between gap-3"
        aria-label="Halaman"
      >
        <Button
          variant="outline"
          class="min-h-11"
          :disabled="!logbooks.links.prev"
          @click="pageTo(logbooks.links.prev)"
        >
          Sebelumnya
        </Button>
        <p class="text-center text-sm text-muted-foreground">
          Halaman {{ logbooks.meta.current_page }} dari {{ logbooks.meta.last_page }} · {{ logbooks.meta.total }} logbook
        </p>
        <Button
          variant="outline"
          class="min-h-11"
          :disabled="!logbooks.links.next"
          @click="pageTo(logbooks.links.next)"
        >
          Berikutnya
        </Button>
      </nav>
    </template>

    <Sheet
      :open="sheetOpen"
      @update:open="(open: boolean) => { if (!open) visit({ review: null }, ['selected', 'filters']); }"
    >
      <SheetContent class="w-full overflow-y-auto sm:max-w-xl">
        <SheetHeader>
          <SheetTitle>Detail logbook</SheetTitle>
          <SheetDescription>Periksa aktivitas dan riwayat sebelum memutuskan.</SheetDescription>
        </SheetHeader>
        <div
          v-if="selected"
          class="px-4 pb-6"
        >
          <ReviewDetail
            :logbook="selected"
            @done="visit({ review: null })"
          />
        </div>
      </SheetContent>
    </Sheet>

    <AlertDialog v-model:open="confirmingBulk">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Setujui {{ checked.size }} logbook?</AlertDialogTitle>
          <AlertDialogDescription>
            Logbook yang disetujui bersifat final. Setiap logbook tetap diperiksa wewenangnya; yang tidak berhak akan dilewati.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Batal</AlertDialogCancel>
          <AlertDialogAction @click="bulkApprove">
            Ya, setujui
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
