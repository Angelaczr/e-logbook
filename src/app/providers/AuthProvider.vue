<script setup lang="ts">
import { computed, provide } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { AuthContextValue, AuthenticatedEmployee } from '@/shared/types/auth';
import { authContextKey } from '@/shared/hooks/useAuth';

const page = usePage<{ auth?: { user?: AuthenticatedEmployee | null } }>();
const user = computed(() => page.props.auth?.user ?? null);

const authContext: AuthContextValue = {
  get user() { return user.value; },
  get isAuthenticated() { return user.value !== null; },
};

provide(authContextKey, authContext);

</script>

<template><slot /></template>
