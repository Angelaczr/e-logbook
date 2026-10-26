import { inject, type InjectionKey } from 'vue';
import type { Permission } from '@/shared/constants/permissions';
import type { PermissionContextValue } from '@/shared/types/permission';

export const permissionContextKey: InjectionKey<PermissionContextValue> = Symbol('e-logbook.permission-context');

export function usePermission(permission?: Permission): PermissionContextValue & { allowed: boolean } {
  const context = inject<PermissionContextValue>(permissionContextKey);
  if (!context) throw new Error('usePermission harus digunakan di dalam PermissionProvider.');

  return {
    get permissions() { return context.permissions; },
    get isLoading() { return context.isLoading; },
    hasPermission: context.hasPermission,
    get allowed() { return permission ? context.hasPermission(permission) : false; },
  };
}
