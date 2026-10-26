<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { StatusColor } from '@/types/logbook';

defineProps<{
  label: string;
  color: StatusColor;
}>();

const colorClasses: Record<StatusColor, string> = {
  gray: 'border-neutral-300 bg-neutral-100 text-neutral-800 dark:border-neutral-600 dark:bg-neutral-800 dark:text-neutral-100',
  blue: 'border-blue-300 bg-blue-50 text-blue-900 dark:border-blue-700 dark:bg-blue-950 dark:text-blue-100',
  green: 'border-green-300 bg-green-50 text-green-900 dark:border-green-700 dark:bg-green-950 dark:text-green-100',
  amber: 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100',
  red: 'border-red-300 bg-red-50 text-red-900 dark:border-red-700 dark:bg-red-950 dark:text-red-100',
};
</script>

<template>
  <Badge
    variant="outline"
    :class="cn('font-medium', colorClasses[color])"
  >
    {{ label }}
  </Badge>
</template>
