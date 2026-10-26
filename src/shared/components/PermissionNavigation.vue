<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { Permission } from '@/shared/constants/permissions';
import { usePermission } from '@/shared/hooks/usePermission';

type NavigationItem = { label: string; href: string; permission: Permission };
const props = defineProps<{ items: readonly NavigationItem[] }>();
const access = usePermission();
const visibleItems = computed(() => props.items.filter((item) => access.hasPermission(item.permission)));
</script>

<template>
  <nav
    aria-label="Navigasi utama"
    class="flex flex-col gap-1"
  >
    <Link
      v-for="item in visibleItems"
      :key="item.href"
      :href="item.href"
      class="rounded-lg px-3 py-2 text-sm hover:bg-slate-100"
    >
      {{ item.label }}
    </Link>
  </nav>
</template>
