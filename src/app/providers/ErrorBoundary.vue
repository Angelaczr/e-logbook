<script setup lang="ts">
import { onErrorCaptured, ref } from 'vue';
import ErrorState from '@/shared/components/ErrorState.vue';

const errorMessage = ref<string | null>(null);

onErrorCaptured((error) => {
  errorMessage.value = error instanceof Error ? error.message : 'Kesalahan aplikasi yang tidak diketahui.';
  return false;
});
</script>

<template>
  <ErrorState
    v-if="errorMessage"
    title="Aplikasi mengalami kendala"
    :message="errorMessage"
  />
  <slot v-else />
</template>
