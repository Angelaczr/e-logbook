export type StatusColor = 'gray' | 'blue' | 'green' | 'amber' | 'red';

export type ProgressValue = 'in_progress' | 'completed' | 'pending';

export interface LogbookSummary {
  id: number;
  work_date: string;
  shift: string;
  status: string;
  status_label: string;
  status_color: StatusColor;
  total_minutes: number;
  revision_count: number;
  items_count: number;
}

export interface ProjectOption {
  id: number;
  code: string;
  name: string;
}

export interface LogbookItemData {
  id: number;
  project_id: number | null;
  project: ProjectOption | null;
  task_description: string;
  start_time: string;
  end_time: string;
  duration_minutes: number;
  status_progress: ProgressValue;
  status_progress_label: string;
  output_link_or_attachment: string | null;
}

export interface StatusHistoryEntry {
  id: number;
  actor: string | null;
  from_status_label: string | null;
  to_status: string;
  to_status_label: string;
  notes: string | null;
  created_at: string;
}

export interface LogbookDetail extends LogbookSummary {
  owner: { id: number; name: string };
  submitted_at: string | null;
  approved_at: string | null;
  approver: { id: number; name: string } | null;
  rejection_reason: string | null;
  items: LogbookItemData[];
  histories: StatusHistoryEntry[];
  can: { update: boolean; delete: boolean; submit: boolean; review: boolean };
}

export interface PaginatedLogbooks {
  data: LogbookSummary[];
  links: { prev: string | null; next: string | null };
  meta: { current_page: number; last_page: number; total: number };
}

export interface StatusOption {
  value: string;
  label: string;
}

/** Baris item pada form; `uid` hanya kunci render di klien dan tidak dikirim ke server. */
export type FormItem = {
  uid: string;
  id: number | null;
  project_id: number | null;
  task_description: string;
  start_time: string;
  end_time: string;
  status_progress: ProgressValue;
  output_link_or_attachment: string;
};

export const PROGRESS_OPTIONS: { value: ProgressValue; label: string }[] = [
  { value: 'in_progress', label: 'Sedang Dikerjakan' },
  { value: 'completed', label: 'Selesai' },
  { value: 'pending', label: 'Tertunda' },
];
