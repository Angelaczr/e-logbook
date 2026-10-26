<script setup lang="ts">
import type { EmployeeCompliance } from '@/types/analytics';

defineProps<{
  days: string[];
  employees: EmployeeCompliance[];
}>();

const cellInfo = {
  S: { symbol: '✓', label: 'Terkirim', classes: 'bg-green-100 text-green-900 dark:bg-green-900 dark:text-green-100' },
  D: { symbol: '•', label: 'Belum final', classes: 'bg-amber-100 text-amber-900 dark:bg-amber-900 dark:text-amber-100' },
  '-': { symbol: '–', label: 'Kosong', classes: 'bg-red-50 text-red-900 dark:bg-red-950 dark:text-red-100' },
} as const;

type CellKey = keyof typeof cellInfo;

function isCellKey(value: string): value is CellKey {
  return value in cellInfo;
}

function cell(pattern: string, index: number): (typeof cellInfo)[CellKey] {
  const key = pattern.charAt(index);
  return isCellKey(key) ? cellInfo[key] : cellInfo['-'];
}

function shortDate(iso: string): string {
  const [, month, day] = iso.split('-');
  return `${day}/${month}`;
}
</script>

<template>
  <div>
    <ul
      class="mb-3 flex flex-wrap gap-x-4 gap-y-1 text-sm"
      aria-label="Keterangan"
    >
      <li
        v-for="(info, key) in cellInfo"
        :key="key"
        class="flex items-center gap-1.5"
      >
        <span
          class="inline-flex size-6 items-center justify-center rounded"
          :class="info.classes"
          aria-hidden="true"
        >{{ info.symbol }}</span>
        {{ info.label }}
      </li>
    </ul>

    <p
      v-if="employees.length === 0"
      class="py-8 text-center text-sm text-muted-foreground"
    >
      Tidak ada karyawan pada cakupan dan filter ini.
    </p>

    <div
      v-else
      class="overflow-x-auto rounded-lg border"
    >
      <table class="min-w-full border-collapse text-sm">
        <caption class="sr-only">
          Matriks kepatuhan pengiriman logbook per karyawan per hari kerja
        </caption>
        <thead>
          <tr class="bg-muted/50">
            <th
              scope="col"
              class="sticky left-0 z-10 min-w-40 bg-muted px-3 py-2 text-left font-medium"
            >
              Karyawan
            </th>
            <th
              v-for="day in days"
              :key="day"
              scope="col"
              class="px-1 py-2 text-center text-xs font-medium"
            >
              {{ shortDate(day) }}
            </th>
            <th
              scope="col"
              class="px-3 py-2 text-right font-medium"
            >
              Kepatuhan
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="employee in employees"
            :key="employee.id"
            class="border-t"
          >
            <th
              scope="row"
              class="sticky left-0 z-10 bg-background px-3 py-2 text-left font-normal"
            >
              {{ employee.name }}
            </th>
            <td
              v-for="(day, index) in days"
              :key="day"
              class="p-0.5 text-center"
            >
              <span
                class="inline-flex size-8 items-center justify-center rounded"
                :class="cell(employee.pattern, index).classes"
                :title="`${shortDate(day)}: ${cell(employee.pattern, index).label}`"
              >
                <span aria-hidden="true">{{ cell(employee.pattern, index).symbol }}</span>
                <span class="sr-only">{{ cell(employee.pattern, index).label }}</span>
              </span>
            </td>
            <td class="px-3 py-2 text-right tabular-nums">
              {{ employee.rate }}% <span class="text-xs text-muted-foreground">({{ employee.submitted_days }}/{{ employee.workdays }})</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
