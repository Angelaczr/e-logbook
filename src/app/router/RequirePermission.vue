<script setup lang="ts">
import type { Permission } from '@/shared/constants/permissions';
import { usePermission } from '@/shared/hooks/usePermission';
import LoadingState from '@/shared/components/LoadingState.vue';
import ErrorState from '@/shared/components/ErrorState.vue';

const props = defineProps<{ permission: Permission }>();
const access = usePermission(props.permission);
</script>

<template>
  <LoadingState v-if="access.isLoading" />
  <ErrorState
    v-else-if="!access.allowed"
    title="Akses tidak tersedia"
    message="Akun Anda tidak memiliki izin untuk membuka halaman ini."
  />
  <slot v-else />
</template>
