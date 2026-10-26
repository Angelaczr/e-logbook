<script setup lang="ts">
import { computed } from 'vue';
import { ArrowDown, ArrowUp, Copy, Trash2, TriangleAlert } from '@lucide/vue';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import ProjectCombobox from '@/components/logbook/ProjectCombobox.vue';
import { durationMinutes, formatDuration } from '@/lib/logbookTime';
import { PROGRESS_OPTIONS } from '@/types/logbook';
import type { FormItem, ProgressValue, ProjectOption } from '@/types/logbook';

type ItemField = 'project_id' | 'task_description' | 'start_time' | 'end_time' | 'status_progress' | 'output_link_or_attachment';

const props = defineProps<{
  item: FormItem;
  index: number;
  projects: ProjectOption[];
  errors: Partial<Record<ItemField | 'id', string>>;
  readonly: boolean;
  overlapping: boolean;
  canMoveUp: boolean;
  canMoveDown: boolean;
}>();

const emit = defineEmits<{
  'update:item': [item: FormItem];
  remove: [];
  duplicate: [];
  move: [direction: -1 | 1];
}>();

const number = computed(() => props.index + 1);
const fieldId = (name: string): string => `item-${props.item.uid}-${name}`;
const duration = computed(() => {
  const minutes = durationMinutes(props.item.start_time, props.item.end_time);
  return minutes === null || minutes === 0 ? '–' : formatDuration(minutes);
});

function patch(changes: Partial<FormItem>): void {
  emit('update:item', { ...props.item, ...changes });
}

function isProgress(value: unknown): value is ProgressValue {
  return PROGRESS_OPTIONS.some((option) => option.value === value);
}

function onProgress(value: unknown): void {
  if (isProgress(value)) {
    patch({ status_progress: value });
  }
}

function onInput(field: 'task_description' | 'start_time' | 'end_time' | 'output_link_or_attachment', value: string | number): void {
  patch({ [field]: String(value) });
}
</script>

<template>
  <fieldset
    class="space-y-4 rounded-lg border bg-card p-4"
    :class="overlapping && 'border-amber-500'"
    :disabled="readonly"
  >
    <legend class="px-1 text-sm font-semibold">
      Item {{ number }}
    </legend>

    <div class="flex flex-wrap items-center justify-between gap-2">
      <p
        v-if="overlapping"
        class="flex items-center gap-1.5 text-sm text-amber-800 dark:text-amber-200"
        role="alert"
      >
        <TriangleAlert
          class="size-4"
          aria-hidden="true"
        />
        Jam tumpang tindih dengan item lain.
      </p>
      <span v-else />

      <div
        v-if="!readonly"
        class="flex gap-1"
      >
        <Button
          type="button"
          variant="ghost"
          size="icon"
          class="size-11"
          :disabled="!canMoveUp"
          :aria-label="`Geser item ${number} ke atas`"
          @click="emit('move', -1)"
        >
          <ArrowUp class="size-4" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          class="size-11"
          :disabled="!canMoveDown"
          :aria-label="`Geser item ${number} ke bawah`"
          @click="emit('move', 1)"
        >
          <ArrowDown class="size-4" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          class="size-11"
          :aria-label="`Duplikat item ${number}`"
          @click="emit('duplicate')"
        >
          <Copy class="size-4" />
        </Button>

        <AlertDialog>
          <AlertDialogTrigger as-child>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              class="size-11 text-destructive"
              :aria-label="`Hapus item ${number}`"
            >
              <Trash2 class="size-4" />
            </Button>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Hapus item {{ number }}?</AlertDialogTitle>
              <AlertDialogDescription>
                Item ini akan dihapus dari form. Perubahan baru tersimpan setelah Anda menyimpan draft.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Batal</AlertDialogCancel>
              <AlertDialogAction @click="emit('remove')">
                Hapus
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
      <div class="space-y-1.5">
        <Label :for="fieldId('project')">Proyek / cost center</Label>
        <ProjectCombobox
          :id="fieldId('project')"
          :model-value="item.project_id"
          :projects="projects"
          :disabled="readonly"
          :invalid="!!errors.project_id"
          @update:model-value="patch({ project_id: $event })"
        />
        <p
          v-if="errors.project_id"
          class="text-sm text-destructive"
        >
          {{ errors.project_id }}
        </p>
      </div>

      <div class="space-y-1.5">
        <Label :for="fieldId('progress')">Status progres</Label>
        <Select
          :model-value="item.status_progress"
          :disabled="readonly"
          @update:model-value="onProgress"
        >
          <SelectTrigger
            :id="fieldId('progress')"
            class="min-h-11 w-full"
            :aria-invalid="!!errors.status_progress || undefined"
          >
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="option in PROGRESS_OPTIONS"
              :key="option.value"
              :value="option.value"
            >
              {{ option.label }}
            </SelectItem>
          </SelectContent>
        </Select>
        <p
          v-if="errors.status_progress"
          class="text-sm text-destructive"
        >
          {{ errors.status_progress }}
        </p>
      </div>
    </div>

    <div class="space-y-1.5">
      <Label :for="fieldId('task')">Uraian pekerjaan</Label>
      <Textarea
        :id="fieldId('task')"
        :model-value="item.task_description"
        rows="3"
        :aria-invalid="!!errors.task_description || undefined"
        :aria-describedby="errors.task_description ? fieldId('task-error') : undefined"
        @update:model-value="onInput('task_description', $event)"
      />
      <p
        v-if="errors.task_description"
        :id="fieldId('task-error')"
        class="text-sm text-destructive"
      >
        {{ errors.task_description }}
      </p>
    </div>

    <div class="grid grid-cols-2 gap-4 md:grid-cols-3">
      <div class="space-y-1.5">
        <Label :for="fieldId('start')">Jam mulai</Label>
        <Input
          :id="fieldId('start')"
          type="time"
          class="min-h-11"
          :model-value="item.start_time"
          :aria-invalid="!!errors.start_time || undefined"
          @update:model-value="onInput('start_time', $event)"
        />
        <p
          v-if="errors.start_time"
          class="text-sm text-destructive"
        >
          {{ errors.start_time }}
        </p>
      </div>
      <div class="space-y-1.5">
        <Label :for="fieldId('end')">Jam selesai</Label>
        <Input
          :id="fieldId('end')"
          type="time"
          class="min-h-11"
          :model-value="item.end_time"
          :aria-invalid="!!errors.end_time || undefined"
          @update:model-value="onInput('end_time', $event)"
        />
        <p
          v-if="errors.end_time"
          class="text-sm text-destructive"
        >
          {{ errors.end_time }}
        </p>
      </div>
      <div class="col-span-2 space-y-1.5 md:col-span-1">
        <span class="text-sm font-medium leading-none">Durasi</span>
        <p
          class="flex min-h-11 items-center rounded-md bg-muted px-3 text-sm"
          aria-live="polite"
        >
          {{ duration }}
        </p>
      </div>
    </div>

    <div class="space-y-1.5">
      <Label :for="fieldId('link')">Tautan hasil kerja (opsional)</Label>
      <Input
        :id="fieldId('link')"
        type="url"
        class="min-h-11"
        placeholder="https://"
        :model-value="item.output_link_or_attachment"
        :aria-invalid="!!errors.output_link_or_attachment || undefined"
        @update:model-value="onInput('output_link_or_attachment', $event)"
      />
      <p
        v-if="errors.output_link_or_attachment"
        class="text-sm text-destructive"
      >
        {{ errors.output_link_or_attachment }}
      </p>
    </div>
  </fieldset>
</template>
