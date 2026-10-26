import { http, HttpResponse } from 'msw';
import { PERMISSIONS, type Permission } from '@/shared/constants/permissions';

const defaultPermissions = [PERMISSIONS.dashboardRead, PERMISSIONS.logbookRead];
const validPermissions: ReadonlySet<string> = new Set(Object.values(PERMISSIONS));

export const handlers = [
  http.get('/me/permissions', () => {
    const persistedPermissions = localStorage.getItem('e-logbook.mock-permissions')
      ?.split(',')
      .map((permission) => permission.trim())
      .filter(Boolean);
    const configuredPermissions = window.__MOCK_PERMISSIONS__ ?? persistedPermissions ?? defaultPermissions;
    const permissions = configuredPermissions.filter((permission): permission is Permission => validPermissions.has(permission));

    return HttpResponse.json({ permissions });
  }),
];
