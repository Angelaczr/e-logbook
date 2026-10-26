import type { Permission } from '@/shared/constants/permissions';

export type AppRoute = {
  name: string;
  path: string;
  permission?: Permission;
  authenticated: boolean;
};

// Route bisnis ditambahkan setelah FEATURE-MAP tersedia.
export const appRoutes: readonly AppRoute[] = [];
