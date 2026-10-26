<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LogbookItemRow from '@/components/logbook/LogbookItemRow.vue';
import LogbookStatusBadge from '@/components/logbook/LogbookStatusBadge.vue';
import StatusTimeline from '@/components/logbook/StatusTimeline.vue';
import { formatDate, formatDuration } from '@/lib/logbookTime';
import { logbookRoutes } from '@/lib/routes';
import type { FormItem, LogbookDetail, ProjectOption } from '@/types/logbook';

const props = defineProps<{
  logbook: LogbookDetail;
}>();

const confirmingSubmit = ref(false);
const submitting = ref(false);

const rows = computed<FormItem[]>(() => props.logbook.items.map((item) => ({
  uid: `item-${item.id}`,
  id: item.id,
  project_id: item.project_id,
  task_description: item.task_description,
  start_time: item.start_time,
  end_time: item.end_time,
  status_progress: item.status_progress,
  output_link_or_attachment: item.output_link_or_attachment ?? '',
})));
const projects = computed<ProjectOption[]>(() => props.logbook.items.flatMap((item) => (item.project ? [item.project] : [])));
const noErrors = {};

function submitLogbook(): void {
  confirmingSubmit.value = false;
  router.post(logbookRoutes.submit(props.logbook.id), {}, {
    onStart: () => { submitting.value = true; },
    onFinish: () => { submitting.value = false; },
  });
}
</script>

<template>
  <Head :title="`Logbook ${logbook.work_date}`" />

  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold">
          {{ formatDate(logbook.work_date) }}
        </h1>
        <p class="text-sm text-muted-foreground">
          Shift {{ logbook.shift }} · {{ logbook.owner.name }} · Total {{ formatDuration(logbook.total_minutes) }}
        </p>
      </div>
      <LogbookStatusBadge
        :label="logbook.status_label"
        :color="logbook.status_color"
      />
    </div>

    <Alert
      v-if="logbook.rejection_reason"
      variant="destructive"
    >
      <AlertTitle>Catatan atasan</AlertTitle>
      <AlertDescription class="whitespace-pre-line">
        {{ logbook.rejection_reason }}
      </AlertDescription>
    </Alert>

    <div class="flex flex-col gap-3 sm:flex-row">
      <Button
        v-if="logbook.can.update"
        as-child
        variant="outline"
        class="min-h-11"
      >
        <Link :href="logbookRoutes.edit(logbook.id)">
          Ubah
        </Link>
      </Button>
      <Button
        v-if="logbook.can.submit"
        class="min-h-11"
        :disabled="submitting"
        @click="confirmingSubmit = true"
      >
        Submit
      </Button>
      <Button
        as-child
        variant="ghost"
        class="min-h-11"
      >
        <Link :href="logbookRoutes.index">
          Kembali ke daftar
        </Link>
      </Button>
    </div>

    <section
      class="space-y-4"
      aria-labelledby="items-heading"
    >
      <h2
        id="items-heading"
        class="text-lg font-semibold"
      >
        Aktivitas ({{ rows.length }})
      </h2>
      <LogbookItemRow
        v-for="(item, index) in rows"
        :key="item.uid"
        :item="item"
        :index="index"
        :projects="projects"
        :errors="noErrors"
        readonly
        :overlapping="false"
        :can-move-up="false"
        :can-move-down="false"
      />
    </section>

    <Card>
      <CardHeader>
        <CardTitle class="text-base">
          Riwayat status
        </CardTitle>
      </CardHeader>
      <CardContent>
        <StatusTimeline :histories="logbook.histories" />
      </CardContent>
    </Card>

    <AlertDialog v-model:open="confirmingSubmit">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Kirim logbook untuk ditinjau?</AlertDialogTitle>
          <AlertDialogDescription>
            Setelah dikirim, logbook tidak dapat diubah sampai atasan meminta revisi.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Batal</AlertDialogCancel>
          <AlertDialogAction @click="submitLogbook">
            Ya, kirim
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
