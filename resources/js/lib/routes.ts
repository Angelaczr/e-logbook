export const logbookRoutes = {
  index: '/logbooks',
  create: '/logbooks/create',
  store: '/logbooks',
  show: (id: number): string => `/logbooks/${id}`,
  edit: (id: number): string => `/logbooks/${id}/edit`,
  update: (id: number): string => `/logbooks/${id}`,
  submit: (id: number): string => `/logbooks/${id}/submit`,
  destroy: (id: number): string => `/logbooks/${id}`,
} as const;

export const reviewRoutes = {
  index: '/reviews',
  show: (id: number): string => `/reviews/${id}`,
  approve: (id: number): string => `/reviews/${id}/approve`,
  requestRevision: (id: number): string => `/reviews/${id}/request-revision`,
  reject: (id: number): string => `/reviews/${id}/reject`,
  bulkApprove: '/reviews/bulk-approve',
} as const;
