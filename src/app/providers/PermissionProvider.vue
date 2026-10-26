<script setup lang="ts">
import { computed, provide } from 'vue';
import { useQuery } from '@tanstack/vue-query';
import { request } from '@/shared/services/httpClient';
import { useAuth } from '@/shared/hooks/useAuth';
import { permissionContextKey } from '@/shared/hooks/usePermission';
import type { PermissionResponse } from '@/shared/types/permission';

const auth = useAuth();
const permissionsQuery = useQuery({
  queryKey: ['me', 'permissions'],
  queryFn: () => request<PermissionResponse>('/me/permissions'),
  enabled: computed(() => auth.isAuthenticated),
  staleTime: 60_000,
});
const permissions = computed(() => new Set(permissionsQuery.data.value?.permissions ?? []));

provide(permissionContextKey, {
  get permissions() { return permissions.value; },
  get isLoading() { return permissionsQuery.isLoading.value; },
  hasPermission: (permission) => permissions.value.has(permission),
});
</script>

<template><slot /></template>
