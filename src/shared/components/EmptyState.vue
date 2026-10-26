<template>
  <div class="rounded-xl border border-dashed border-[var(--color-border)] p-8 text-center">
    <h2 class="font-semibold text-[var(--color-text)]">
      {{ title }}
    </h2>
    <p class="mt-2 text-sm text-[var(--color-text-muted)]">
      {{ description }}
    </p>
    <slot />
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ title?: string; description?: string }>(), {
  title: 'Belum ada data',
  description: 'Data akan muncul di sini setelah tersedia.',
});
</script>
