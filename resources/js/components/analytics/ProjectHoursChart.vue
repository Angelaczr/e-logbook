<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Bar } from 'vue-chartjs';
import { BarElement, CategoryScale, Chart as ChartJS, LinearScale, Tooltip } from 'chart.js';
import { formatDuration } from '@/lib/logbookTime';
import type { ProjectHours } from '@/types/analytics';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip);

const props = defineProps<{
  projects: ProjectHours[];
}>();

const barColor = ref('#2563eb');

onMounted(() => {
  const value = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
  if (value !== '') {
    barColor.value = value;
  }
});

const hours = (minutes: number): number => Math.round((minutes / 60) * 10) / 10;

const chartData = computed(() => ({
  labels: props.projects.map((project) => project.name),
  datasets: [{
    label: 'Jam',
    data: props.projects.map((project) => hours(project.minutes)),
    backgroundColor: barColor.value,
    borderRadius: 4,
  }],
}));

const chartOptions = {
  indexAxis: 'y' as const,
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { x: { beginAtZero: true, title: { display: true, text: 'Jam' } } },
};

const height = computed(() => `${Math.max(12, props.projects.length * 2.5 + 4)}rem`);
</script>

<template>
  <figure>
    <div
      v-if="projects.length > 0"
      :style="{ height }"
    >
      <Bar
        :data="chartData"
        :options="chartOptions"
        role="img"
        aria-label="Grafik batang total jam per proyek. Data lengkap ada pada tabel di bawahnya."
      />
    </div>
    <p
      v-else
      class="py-8 text-center text-sm text-muted-foreground"
    >
      Belum ada jam kerja pada periode ini.
    </p>

    <figcaption class="sr-only">
      Total jam per proyek
    </figcaption>
    <table
      v-if="projects.length > 0"
      class="mt-4 w-full text-sm"
    >
      <caption class="sr-only">
        Total jam per proyek
      </caption>
      <thead>
        <tr class="border-b text-left text-muted-foreground">
          <th
            scope="col"
            class="py-1 font-medium"
          >
            Proyek
          </th>
          <th
            scope="col"
            class="py-1 text-right font-medium"
          >
            Total
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="project in projects"
          :key="project.id ?? 'none'"
          class="border-b last:border-0"
        >
          <th
            scope="row"
            class="py-1 text-left font-normal"
          >
            {{ project.code ? `${project.code} · ` : '' }}{{ project.name }}
          </th>
          <td class="py-1 text-right tabular-nums">
            {{ formatDuration(project.minutes) }}
          </td>
        </tr>
      </tbody>
    </table>
  </figure>
</template>
