<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import LogbookStatusBadge from '@/components/logbook/LogbookStatusBadge.vue';
import StatusTimeline from '@/components/logbook/StatusTimeline.vue';
import ReviewReasonDialog from '@/components/review/ReviewReasonDialog.vue';
import { formatDate, formatDuration } from '@/lib/logbookTime';
import { reviewRoutes } from '@/lib/routes';
import type { LogbookDetail } from '@/types/logbook';

defineProps<{
  logbook: LogbookDetail;
}>();

const emit = defineEmits<{
  done: [];
}>();

type Action = 'approve' | 'revision' | 'reject';
const action = ref<Action | null>(null);

function setOpen(target: Action, open: boolean): void {
  action.value = open ? target : null;
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div>
        <h2 class="text-lg font-semibold">
          {{ logbook.owner.name }}
        </h2>
        <p class="text-sm text-muted-foreground">
          {{ formatDate(logbook.work_date) }} · Shift {{ logbook.shift }} · Total {{ formatDuration(logbook.total_minutes) }}
        </p>
      </div>
      <LogbookStatusBadge
        :label="logbook.status_label"
        :color="logbook.status_color"
      />
    </div>

    <section aria-labelledby="review-items-heading">
      <h3
        id="review-items-heading"
        class="mb-2 text-sm font-semibold"
      >
        Aktivitas ({{ logbook.items.length }})
      </h3>
      <ol class="space-y-3">
        <li
          v-for="item in logbook.items"
          :key="item.id"
          class="rounded-lg border p-3 text-sm"
        >
          <div class="flex flex-wrap justify-between gap-1 font-medium">
            <span>{{ item.start_time }} – {{ item.end_time }} · {{ formatDuration(item.duration_minutes) }}</span>
            <span class="text-muted-foreground">{{ item.status_progress_label }}</span>
          </div>
          <p
            v-if="item.project"
            class="text-muted-foreground"
          >
            {{ item.project.code }} · {{ item.project.name }}
          </p>
          <p class="mt-1 whitespace-pre-line">
            {{ item.task_description }}
          </p>
          <a
            v-if="item.output_link_or_attachment"
            :href="item.output_link_or_attachment"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-1 inline-block break-all text-primary underline"
          >{{ item.output_link_or_attachment }}</a>
        </li>
      </ol>
    </section>

    <section aria-labelledby="review-history-heading">
      <h3
        id="review-history-heading"
        class="mb-2 text-sm font-semibold"
      >
        Riwayat status
      </h3>
      <StatusTimeline :histories="logbook.histories" />
    </section>

    <div
      v-if="logbook.can.review"
      class="flex flex-col gap-2 border-t pt-4 sm:flex-row sm:justify-end"
    >
      <Button
        variant="destructive"
        class="min-h-11"
        @click="action = 'reject'"
      >
        Reject
      </Button>
      <Button
        variant="outline"
        class="min-h-11"
        @click="action = 'revision'"
      >
        Minta Revisi
      </Button>
      <Button
        class="min-h-11"
        @click="action = 'approve'"
      >
        Approve
      </Button>
    </div>

    <ReviewReasonDialog
      :open="action === 'approve'"
      :url="reviewRoutes.approve(logbook.id)"
      field="note"
      title="Setujui logbook?"
      description="Logbook yang disetujui bersifat final dan tidak dapat diubah lagi."
      label="Catatan"
      confirm-label="Setujui"
      @update:open="setOpen('approve', $event)"
      @done="emit('done')"
    />
    <ReviewReasonDialog
      :open="action === 'revision'"
      :url="reviewRoutes.requestRevision(logbook.id)"
      field="reason"
      title="Minta revisi"
      description="Karyawan akan menerima alasan ini dan dapat mengubah logbook lalu mengirim ulang."
      label="Alasan revisi"
      confirm-label="Kirim permintaan revisi"
      @update:open="setOpen('revision', $event)"
      @done="emit('done')"
    />
    <ReviewReasonDialog
      :open="action === 'reject'"
      :url="reviewRoutes.reject(logbook.id)"
      field="reason"
      title="Tolak logbook"
      description="Penolakan bersifat final. Karyawan akan menerima alasan ini."
      label="Alasan penolakan"
      confirm-label="Tolak logbook"
      destructive
      @update:open="setOpen('reject', $event)"
      @done="emit('done')"
    />
  </div>
</template>
