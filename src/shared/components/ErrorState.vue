<template>
  <section
    class="rounded-xl border border-[var(--color-danger-border)] bg-[var(--color-danger-soft)] p-5"
    role="alert"
  >
    <h2 class="font-semibold text-[var(--color-danger)]">
      {{ title }}
    </h2>
    <p class="mt-1 text-sm text-[var(--color-text)]">
      {{ message }}
    </p>
    <button
      v-if="onRetry"
      class="mt-4 rounded-lg bg-[var(--color-surface)] px-4 py-2 text-sm font-medium"
      type="button"
      @click="onRetry"
    >
      Coba lagi
    </button>
  </section>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ title?: string; message?: string; onRetry?: () => void }>(), {
  title: 'Terjadi kendala',
  message: 'Data belum dapat ditampilkan. Silakan coba lagi.',
  onRetry: undefined,
});
</script>
