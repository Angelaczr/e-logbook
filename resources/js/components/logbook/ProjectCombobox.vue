<script setup lang="ts">
import { computed, ref } from 'vue';
import { Check, ChevronsUpDown } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { ProjectOption } from '@/types/logbook';

const props = defineProps<{
  modelValue: number | null;
  projects: ProjectOption[];
  disabled?: boolean;
  invalid?: boolean;
  id?: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: number | null];
}>();

const open = ref(false);
const selected = computed(() => props.projects.find((project) => project.id === props.modelValue) ?? null);

function choose(id: number | null): void {
  emit('update:modelValue', id);
  open.value = false;
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button
        :id="id"
        type="button"
        variant="outline"
        role="combobox"
        :aria-expanded="open"
        :aria-invalid="invalid || undefined"
        :disabled="disabled"
        :class="cn('min-h-11 w-full justify-between font-normal', invalid && 'border-destructive')"
      >
        <span class="truncate">{{ selected ? `${selected.code} · ${selected.name}` : 'Pilih proyek (opsional)' }}</span>
        <ChevronsUpDown
          class="size-4 shrink-0 opacity-50"
          aria-hidden="true"
        />
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-[--reka-popover-trigger-width] min-w-64 p-0">
      <Command>
        <CommandInput placeholder="Cari kode atau nama proyek..." />
        <CommandList>
          <CommandEmpty>Proyek tidak ditemukan.</CommandEmpty>
          <CommandGroup>
            <CommandItem
              value="tanpa-proyek"
              @select="choose(null)"
            >
              <Check :class="cn('size-4', modelValue === null ? 'opacity-100' : 'opacity-0')" />
              Tanpa proyek
            </CommandItem>
            <CommandItem
              v-for="project in projects"
              :key="project.id"
              :value="`${project.code} ${project.name}`"
              @select="choose(project.id)"
            >
              <Check :class="cn('size-4', modelValue === project.id ? 'opacity-100' : 'opacity-0')" />
              {{ project.code }} · {{ project.name }}
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>
