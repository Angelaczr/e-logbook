import { inject, type InjectionKey } from 'vue';
import type { AuthContextValue } from '@/shared/types/auth';

export const authContextKey: InjectionKey<AuthContextValue> = Symbol('e-logbook.auth-context');

export function useAuth(): AuthContextValue {
  const context = inject<AuthContextValue>(authContextKey);
  if (!context) throw new Error('useAuth harus digunakan di dalam AuthProvider.');
  return context;
}
