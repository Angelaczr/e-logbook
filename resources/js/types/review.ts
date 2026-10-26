import type { LogbookSummary } from '@/types/logbook';

export type ReviewSort = 'date' | 'viewed';

export interface ReviewRow extends LogbookSummary {
  employee: { id: number; name: string };
  submitted_at: string | null;
}

export interface PaginatedReviews {
  data: ReviewRow[];
  links: { prev: string | null; next: string | null };
  meta: { current_page: number; last_page: number; total: number };
}

export interface ReviewFilters {
  user_id: number | null;
  department_id: number | null;
  from: string | null;
  to: string | null;
  status: string;
  review: number | null;
  sort: ReviewSort;
}

export interface NamedOption {
  id: number;
  name: string;
}
