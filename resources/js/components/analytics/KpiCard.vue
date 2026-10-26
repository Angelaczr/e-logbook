<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
  label: string;
  value: string;
  hint?: string;
}>();
</script>

<template>
  <Card>
    <CardContent class="space-y-1 pt-6">
      <p class="text-sm text-muted-foreground">
        {{ label }}
      </p>
      <p class="text-2xl font-semibold tabular-nums">
        {{ value }}
      </p>
      <p
        v-if="hint"
        class="text-xs text-muted-foreground"
      >
        {{ hint }}
      </p>
    </CardContent>
  </Card>
</template>
