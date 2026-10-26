export const PERMISSIONS = {
  dashboardRead: 'dashboard:read',
  logbookRead: 'logbook:read',
  logbookCreate: 'logbook:create',
  logbookReview: 'logbook:review',
  organizationRead: 'organization:read',
  organizationManage: 'organization:manage',
  reportRead: 'report:read',
  reportExport: 'report:export',
  auditRead: 'audit:read',
} as const;

export type Permission = (typeof PERMISSIONS)[keyof typeof PERMISSIONS];
