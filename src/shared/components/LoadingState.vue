<template>
  <div
    class="flex items-center gap-3 p-6 text-[var(--color-text-muted)]"
    role="status"
    aria-live="polite"
  >
    <span
      class="size-5 animate-spin rounded-full border-2 border-current border-r-transparent"
      aria-hidden="true"
    />
    <span>{{ label }}</span>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ label?: string }>(), { label: 'Memuat data…' });
</script>
