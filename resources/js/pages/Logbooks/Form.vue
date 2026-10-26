<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus, TriangleAlert } from '@lucide/vue';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import LogbookItemRow from '@/components/logbook/LogbookItemRow.vue';
import LogbookStatusBadge from '@/components/logbook/LogbookStatusBadge.vue';
import StatusTimeline from '@/components/logbook/StatusTimeline.vue';
import { toPayload, useLogbookItems } from '@/composables/useLogbookItems';
import { formatDate, formatDuration } from '@/lib/logbookTime';
import { logbookRoutes } from '@/lib/routes';
import type { LogbookDetail, ProjectOption } from '@/types/logbook';

const props = defineProps<{
  logbook: LogbookDetail | null;
  projects: ProjectOption[];
}>();

const MAX_ITEMS = 30;
const REVISION_STATUS = 'revision_requested';

const readonly = computed(() => props.logbook !== null && !props.logbook.can.update);
const title = computed(() => {
  if (props.logbook === null) {
    return 'Buat Logbook';
  }
  return readonly.value ? 'Detail Logbook' : 'Ubah Logbook';
});

function localToday(): string {
  const now = new Date();
  const pad = (value: number): string => String(value).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

const { items, overlapping, totalMinutes, add, update, remove, duplicate, move } = useLogbookItems(props.logbook?.items ?? []);

const form = useForm({
  work_date: props.logbook?.work_date ?? localToday(),
  shift: props.logbook?.shift ?? 'regular',
  items: items.value,
});
form.transform((data) => ({ ...data, items: toPayload(items.value) }));
watch(items, (value) => {
  form.items = value;
}, { deep: true });

const confirmingSubmit = ref(false);

function errorFor(index: number, field: string): string | undefined {
  return form.errors[`items.${index}.${field}` as keyof typeof form.errors];
}

function rowErrors(index: number) {
  return {
    id: errorFor(index, 'id'),
    project_id: errorFor(index, 'project_id'),
    task_description: errorFor(index, 'task_description'),
    start_time: errorFor(index, 'start_time'),
    end_time: errorFor(index, 'end_time'),
    status_progress: errorFor(index, 'status_progress'),
    output_link_or_attachment: errorFor(index, 'output_link_or_attachment'),
  };
}

function savedLogbookId(pageProps: unknown): number | null {
  if (typeof pageProps === 'object' && pageProps !== null && 'logbook' in pageProps) {
    const saved = pageProps.logbook;
    if (typeof saved === 'object' && saved !== null && 'id' in saved && typeof saved.id === 'number') {
      return saved.id;
    }
  }
  return null;
}

function send(afterSave?: (_savedId: number) => void): void {
  const options = {
    preserveScroll: true,
    onSuccess: (page: { props: unknown }) => {
      const id = savedLogbookId(page.props);
      if (afterSave && id !== null) {
        afterSave(id);
      }
    },
  };

  if (props.logbook === null) {
    form.post(logbookRoutes.store, options);
  } else {
    form.put(logbookRoutes.update(props.logbook.id), options);
  }
}

const saveDraft = (): void => send();

function saveAndSubmit(): void {
  confirmingSubmit.value = false;
  send((id) => router.post(logbookRoutes.submit(id)));
}
</script>

<template>
  <Head :title="title" />

  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold">
          {{ title }}
        </h1>
        <p
          v-if="logbook"
          class="text-sm text-muted-foreground"
        >
          {{ formatDate(logbook.work_date) }} · Pemilik: {{ logbook.owner.name }}
        </p>
      </div>
      <LogbookStatusBadge
        v-if="logbook"
        :label="logbook.status_label"
        :color="logbook.status_color"
      />
    </div>

    <Alert
      v-if="logbook?.status === REVISION_STATUS"
      class="border-amber-400 bg-amber-50 text-amber-950 dark:bg-amber-950 dark:text-amber-50"
    >
      <TriangleAlert class="size-4" />
      <AlertTitle>Atasan meminta revisi</AlertTitle>
      <AlertDescription class="whitespace-pre-line">
        {{ logbook.rejection_reason ?? 'Tidak ada catatan revisi.' }}
      </AlertDescription>
    </Alert>

    <Alert
      v-else-if="readonly"
    >
      <AlertDescription>Logbook ini tidak dapat diubah pada status saat ini.</AlertDescription>
    </Alert>

    <form
      class="space-y-6"
      novalidate
      @submit.prevent="saveDraft"
    >
      <Card>
        <CardHeader>
          <CardTitle class="text-base">
            Informasi umum
          </CardTitle>
        </CardHeader>
        <CardContent class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-1.5">
            <Label for="work_date">Tanggal kerja</Label>
            <Input
              id="work_date"
              v-model="form.work_date"
              type="date"
              class="min-h-11"
              :max="localToday()"
              :disabled="readonly"
              :aria-invalid="!!form.errors.work_date || undefined"
            />
            <p
              v-if="form.errors.work_date"
              class="text-sm text-destructive"
            >
              {{ form.errors.work_date }}
            </p>
          </div>
          <div class="space-y-1.5">
            <Label for="shift">Shift</Label>
            <Input
              id="shift"
              v-model="form.shift"
              class="min-h-11"
              maxlength="32"
              :disabled="readonly"
              :aria-invalid="!!form.errors.shift || undefined"
            />
            <p
              v-if="form.errors.shift"
              class="text-sm text-destructive"
            >
              {{ form.errors.shift }}
            </p>
          </div>
        </CardContent>
      </Card>

      <section
        class="space-y-4"
        aria-labelledby="items-heading"
      >
        <h2
          id="items-heading"
          class="text-lg font-semibold"
        >
          Aktivitas ({{ items.length }})
        </h2>

        <p
          v-if="form.errors.items"
          class="text-sm text-destructive"
          role="alert"
        >
          {{ form.errors.items }}
        </p>

        <LogbookItemRow
          v-for="(item, index) in items"
          :key="item.uid"
          :item="item"
          :index="index"
          :projects="projects"
          :errors="rowErrors(index)"
          :readonly="readonly"
          :overlapping="overlapping.has(index)"
          :can-move-up="index > 0"
          :can-move-down="index < items.length - 1"
          @update:item="update(index, $event)"
          @remove="remove(index)"
          @duplicate="duplicate(index)"
          @move="move(index, $event)"
        />

        <Button
          v-if="!readonly"
          type="button"
          variant="outline"
          class="min-h-11 w-full sm:w-auto"
          :disabled="items.length >= MAX_ITEMS"
          @click="add"
        >
          <Plus class="size-4" />
          Tambah item
        </Button>
        <p
          v-if="!readonly && items.length >= MAX_ITEMS"
          class="text-sm text-muted-foreground"
        >
          Maksimal {{ MAX_ITEMS }} item per logbook.
        </p>
      </section>

      <Card>
        <CardContent class="flex flex-wrap items-center justify-between gap-2 pt-6">
          <div>
            <p class="text-sm text-muted-foreground">
              Total durasi (perkiraan)
            </p>
            <p class="text-xl font-semibold">
              {{ formatDuration(readonly && logbook ? logbook.total_minutes : totalMinutes) }}
            </p>
          </div>
          <p
            v-if="overlapping.size > 0"
            class="flex items-center gap-1.5 text-sm text-amber-800 dark:text-amber-200"
            role="alert"
          >
            <TriangleAlert
              class="size-4"
              aria-hidden="true"
            />
            Ada jam yang tumpang tindih. Server akan menolak simpan sampai diperbaiki.
          </p>
        </CardContent>
      </Card>

      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <Button
          as-child
          variant="ghost"
          class="min-h-11"
        >
          <Link :href="logbook ? logbookRoutes.show(logbook.id) : logbookRoutes.index">
            {{ readonly ? 'Kembali' : 'Batal' }}
          </Link>
        </Button>
        <template v-if="!readonly">
          <Button
            type="submit"
            variant="outline"
            class="min-h-11"
            :disabled="form.processing"
          >
            Simpan Draft
          </Button>
          <Button
            type="button"
            class="min-h-11"
            :disabled="form.processing || items.length === 0"
            @click="confirmingSubmit = true"
          >
            Submit
          </Button>
        </template>
      </div>
    </form>

    <Card v-if="logbook">
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
            Perubahan akan disimpan, lalu logbook dikirim ke atasan. Setelah dikirim, logbook tidak dapat diubah
            sampai atasan meminta revisi.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Batal</AlertDialogCancel>
          <AlertDialogAction @click="saveAndSubmit">
            Ya, kirim
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
