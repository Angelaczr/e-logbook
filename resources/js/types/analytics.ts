export interface AnalyticsFilters {
  from: string;
  to: string;
  department_id: number | null;
}

export interface EmployeeCompliance {
  id: number;
  name: string;
  department: string | null;
  submitted_days: number;
  workdays: number;
  rate: number;
  /** Satu karakter per hari kerja: S = terkirim/disetujui, D = ada tetapi belum final, - = kosong. */
  pattern: string;
}

export interface DepartmentCompliance {
  id: number | null;
  name: string;
  employees: number;
  submitted_days: number;
  possible_days: number;
  rate: number;
}

export interface ProjectHours {
  id: number | null;
  code: string | null;
  name: string;
  minutes: number;
}

export interface DepartmentHours {
  id: number | null;
  name: string;
  minutes: number;
}

export interface AnalyticsKpi {
  employees: number;
  workdays: number;
  compliance_rate: number;
  submitted_days: number;
  possible_days: number;
  total_minutes: number;
  avg_review_hours: number | null;
  approved_count: number;
  revision_count: number;
  rejected_count: number;
  pending_count: number;
}

export interface AnalyticsReport {
  period: { from: string; to: string };
  days: string[];
  kpi: AnalyticsKpi;
  compliance_by_department: DepartmentCompliance[];
  compliance_by_employee: EmployeeCompliance[];
  hours_by_project: ProjectHours[];
  hours_by_department: DepartmentHours[];
}
