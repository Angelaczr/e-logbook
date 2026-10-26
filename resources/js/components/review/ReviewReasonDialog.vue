<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

const MIN_REASON_LENGTH = 10;

const props = defineProps<{
  open: boolean;
  url: string;
  title: string;
  description: string;
  /** Nama field yang dikirim ke server: `reason` (wajib) atau `note` (opsional). */
  field: 'reason' | 'note';
  label: string;
  confirmLabel: string;
  destructive?: boolean;
}>();

const emit = defineEmits<{
  'update:open': [open: boolean];
  done: [];
}>();

const form = useForm({ text: '' });
form.transform((data) => ({ [props.field]: data.text }));

const required = computed(() => props.field === 'reason');
const tooShort = computed(() => required.value && form.text.trim().length < MIN_REASON_LENGTH);
const serverError = computed(() => Object.entries(form.errors).find(([key]) => key === props.field)?.[1]);
const textId = computed(() => `review-${props.field}-text`);

watch(() => props.open, (open) => {
  if (open) {
    form.reset();
    form.clearErrors();
  }
});

function confirm(): void {
  form.post(props.url, {
    preserveScroll: true,
    onSuccess: () => {
      emit('update:open', false);
      emit('done');
    },
  });
}
</script>

<template>
  <Dialog
    :open="open"
    @update:open="emit('update:open', $event)"
  >
    <DialogContent>
      <form
        class="space-y-4"
        novalidate
        @submit.prevent="confirm"
      >
        <DialogHeader>
          <DialogTitle>{{ title }}</DialogTitle>
          <DialogDescription>{{ description }}</DialogDescription>
        </DialogHeader>

        <div class="space-y-1.5">
          <Label :for="textId">{{ label }}{{ required ? '' : ' (opsional)' }}</Label>
          <Textarea
            :id="textId"
            v-model="form.text"
            rows="4"
            :aria-invalid="!!serverError || undefined"
            :aria-describedby="`${textId}-hint`"
          />
          <p
            :id="`${textId}-hint`"
            class="text-sm"
            :class="serverError ? 'text-destructive' : 'text-muted-foreground'"
          >
            {{ serverError ?? (required ? `Wajib diisi, minimal ${MIN_REASON_LENGTH} karakter.` : 'Catatan akan tersimpan di riwayat.') }}
          </p>
        </div>

        <DialogFooter class="gap-2 sm:gap-0">
          <Button
            type="button"
            variant="ghost"
            class="min-h-11"
            @click="emit('update:open', false)"
          >
            Batal
          </Button>
          <Button
            type="submit"
            class="min-h-11"
            :variant="destructive ? 'destructive' : 'default'"
            :disabled="form.processing || tooShort"
          >
            {{ confirmLabel }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>
