<script setup lang="ts">
import { watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useAuth } from '@/shared/hooks/useAuth';
import LoadingState from '@/shared/components/LoadingState.vue';

const auth = useAuth();
watch(() => auth.isAuthenticated, (authenticated) => {
  if (!authenticated) router.visit('/login');
}, { immediate: true });
</script>

<template>
  <slot v-if="auth.isAuthenticated" />
  <LoadingState
    v-else
    label="Mengarahkan ke halaman masuk…"
  />
</template>
