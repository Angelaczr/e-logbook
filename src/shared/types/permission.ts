import type { Permission } from '@/shared/constants/permissions';

export type PermissionContextValue = {
  permissions: ReadonlySet<Permission>;
  isLoading: boolean;
  hasPermission: (_permission: Permission) => boolean;
};

export type PermissionResponse = { permissions: Permission[] };
