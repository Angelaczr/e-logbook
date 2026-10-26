<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { SharedProps } from '@/types/inertia';

const page = usePage<SharedProps>();
const flash = computed(() => page.props.flash);
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-muted/30 px-4 py-10">
    <main
      id="main-content"
      class="w-full max-w-sm space-y-4"
    >
      <p class="text-center text-2xl font-semibold">
        E-Logbook
      </p>
      <Alert v-if="flash.success">
        <AlertDescription role="status">
          {{ flash.success }}
        </AlertDescription>
      </Alert>
      <slot />
    </main>
  </div>
</template>
