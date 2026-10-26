<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ComplianceHeatmap from '@/components/analytics/ComplianceHeatmap.vue';
import KpiCard from '@/components/analytics/KpiCard.vue';
import ProjectHoursChart from '@/components/analytics/ProjectHoursChart.vue';
import { formatDuration } from '@/lib/logbookTime';
import type { AnalyticsFilters, AnalyticsReport } from '@/types/analytics';
import type { NamedOption } from '@/types/review';

const ALL = 'all';

const props = defineProps<{
  filters: AnalyticsFilters;
  departments: NamedOption[];
  report: AnalyticsReport;
}>();

function visit(changes: Partial<AnalyticsFilters>): void {
  const next = { ...props.filters, ...changes };
  router.get('/analytics', {
    from: next.from || undefined,
    to: next.to || undefined,
    department_id: next.department_id ?? undefined,
  }, { preserveState: true, preserveScroll: true, replace: true });
}

function onDepartment(value: unknown): void {
  if (typeof value === 'string') {
    visit({ department_id: value === ALL ? null : Number(value) });
  }
}

const reviewHours = (hours: number | null): string => (hours === null ? '–' : `${hours.toLocaleString('id-ID')} jam`);
</script>

<template>
  <Head title="Analitik" />

  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">
      Analitik Logbook
    </h1>

    <Card>
      <CardContent class="grid gap-4 pt-6 sm:grid-cols-2 lg:grid-cols-4">
        <div class="space-y-1.5">
          <Label for="filter-from">Dari tanggal</Label>
          <Input
            id="filter-from"
            type="date"
            class="min-h-11"
            :model-value="filters.from"
            :max="filters.to"
            @update:model-value="visit({ from: String($event) })"
          />
        </div>
        <div class="space-y-1.5">
          <Label for="filter-to">Sampai tanggal</Label>
          <Input
            id="filter-to"
            type="date"
            class="min-h-11"
            :model-value="filters.to"
            :min="filters.from"
            @update:model-value="visit({ to: String($event) })"
          />
        </div>
        <div class="space-y-1.5">
          <Label for="filter-department">Departemen</Label>
          <Select
            :model-value="filters.department_id === null ? ALL : String(filters.department_id)"
            @update:model-value="onDepartment"
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
        <div class="flex items-end">
          <Button
            variant="ghost"
            class="min-h-11"
            @click="router.get('/analytics')"
          >
            Reset ke bulan ini
          </Button>
        </div>
      </CardContent>
    </Card>

    <section
      aria-label="Ringkasan"
      class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
    >
      <KpiCard
        label="Kepatuhan pengiriman"
        :value="`${report.kpi.compliance_rate}%`"
        :hint="`${report.kpi.submitted_days} dari ${report.kpi.possible_days} hari-karyawan`"
      />
      <KpiCard
        label="Total jam kerja"
        :value="formatDuration(report.kpi.total_minutes)"
        :hint="`${report.kpi.employees} karyawan · ${report.kpi.workdays} hari kerja`"
      />
      <KpiCard
        label="Rata-rata waktu review"
        :value="reviewHours(report.kpi.avg_review_hours)"
        hint="Dari dikirim sampai diputuskan"
      />
      <KpiCard
        label="Menunggu review"
        :value="String(report.kpi.pending_count)"
      />
      <KpiCard
        label="Disetujui"
        :value="String(report.kpi.approved_count)"
      />
      <KpiCard
        label="Permintaan revisi"
        :value="String(report.kpi.revision_count)"
      />
      <KpiCard
        label="Ditolak"
        :value="String(report.kpi.rejected_count)"
      />
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle class="text-base">
            Kepatuhan per departemen
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p
            v-if="report.compliance_by_department.length === 0"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            Tidak ada data.
          </p>
          <ul
            v-else
            class="space-y-4"
          >
            <li
              v-for="department in report.compliance_by_department"
              :key="department.id ?? 'none'"
            >
              <div class="flex justify-between gap-2 text-sm">
                <span class="font-medium">{{ department.name }}</span>
                <span class="tabular-nums">{{ department.rate }}% · {{ department.employees }} karyawan</span>
              </div>
              <div
                class="mt-1 h-2 overflow-hidden rounded-full bg-muted"
                role="img"
                :aria-label="`Kepatuhan ${department.name} ${department.rate} persen`"
              >
                <div
                  class="h-full bg-primary"
                  :style="{ width: `${Math.min(100, department.rate)}%` }"
                />
              </div>
            </li>
          </ul>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle class="text-base">
            Total jam per departemen
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p
            v-if="report.hours_by_department.length === 0"
            class="py-6 text-center text-sm text-muted-foreground"
          >
            Belum ada jam kerja.
          </p>
          <ul
            v-else
            class="divide-y text-sm"
          >
            <li
              v-for="department in report.hours_by_department"
              :key="department.id ?? 'none'"
              class="flex justify-between py-2"
            >
              <span>{{ department.name }}</span>
              <span class="tabular-nums">{{ formatDuration(department.minutes) }}</span>
            </li>
          </ul>
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader>
        <CardTitle class="text-base">
          Jam per proyek
        </CardTitle>
      </CardHeader>
      <CardContent>
        <ProjectHoursChart :projects="report.hours_by_project" />
      </CardContent>
    </Card>

    <Card>
      <CardHeader>
        <CardTitle class="text-base">
          Matriks kepatuhan (karyawan × hari kerja)
        </CardTitle>
      </CardHeader>
      <CardContent>
        <ComplianceHeatmap
          :days="report.days"
          :employees="report.compliance_by_employee"
        />
      </CardContent>
    </Card>
  </div>
</template>
