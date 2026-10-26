<?php

namespace App\Services;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seluruh agregasi dikerjakan PostgreSQL. PHP hanya merakit hasil.
 *
 * Cakupan: karyawan aktif (role employee) dalam hierarki penonton. Hari kerja = Senin–Jumat
 * dikurangi tabel `holidays`. Logbook "terkirim" = berstatus submitted atau approved.
 */
class AnalyticsService
{
    private const CACHE_SECONDS = 600;

    private const VERSION_KEY = 'analytics.version';

    private const PROJECT_LIMIT = 15;

    /**
     * @return array<string, mixed>
     */
    public function dashboard(AnalyticsFilter $filter): array
    {
        $version = Cache::get(self::VERSION_KEY, 'v0');

        return Cache::remember(
            "analytics.{$version}.{$filter->cacheKey()}",
            self::CACHE_SECONDS,
            fn (): array => $this->compute($filter),
        );
    }

    /** Dipanggil setiap ada perubahan status logbook atau hari libur. */
    public static function flushCache(): void
    {
        Cache::forever(self::VERSION_KEY, (string) Str::uuid());
    }

    /**
     * Departemen induk yang memiliki karyawan dalam cakupan penonton.
     *
     * @return list<array{id: int, name: string}>
     */
    public function departmentOptions(AnalyticsFilter $filter): array
    {
        [$scopedSql, $bindings] = $this->scopedCte($filter, withDepartmentFilter: false);

        $rows = DB::select("WITH {$scopedSql} SELECT DISTINCT root_department_id AS id, department_name AS name
            FROM scoped WHERE root_department_id IS NOT NULL ORDER BY name", $bindings);

        return array_map(fn (object $row): array => ['id' => (int) $row->id, 'name' => $row->name], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(AnalyticsFilter $filter): array
    {
        $from = $filter->from->toDateString();
        $to = $filter->to->toDateString();

        [$scopedSql, $scopedBindings] = $this->scopedCte($filter);
        $workdays = $this->workdaysCte();
        $workdayBindings = [$from, $to];
        $withBoth = "WITH {$workdays}, {$scopedSql}";
        $bothBindings = [...$workdayBindings, ...$scopedBindings];

        $dayStatus = "day_status AS (
            SELECT l.user_id, l.work_date, BOOL_OR(l.status IN ('submitted', 'approved')) AS counted
            FROM logbooks l JOIN scoped s ON s.id = l.user_id
            WHERE l.deleted_at IS NULL AND l.work_date BETWEEN ? AND ?
            GROUP BY l.user_id, l.work_date
        )";
        $withDays = "{$withBoth}, {$dayStatus}";
        $daysBindings = [...$bothBindings, $from, $to];

        $days = array_map(
            fn (object $row): string => $row->day,
            DB::select("WITH {$workdays} SELECT TO_CHAR(day, 'YYYY-MM-DD') AS day FROM workdays ORDER BY day", $workdayBindings),
        );

        $employees = array_map(fn (object $row): array => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'department' => $row->department_name,
            'submitted_days' => (int) $row->submitted_days,
            'workdays' => (int) $row->workdays,
            'rate' => (float) $row->rate,
            'pattern' => (string) $row->pattern,
        ], DB::select("{$withDays}
            SELECT s.id, s.name, s.department_name,
                   COUNT(*) FILTER (WHERE ds.counted) AS submitted_days,
                   COUNT(*) AS workdays,
                   COALESCE(ROUND(100.0 * COUNT(*) FILTER (WHERE ds.counted) / NULLIF(COUNT(*), 0), 1), 0)::float AS rate,
                   STRING_AGG(CASE WHEN ds.counted THEN 'S' WHEN ds.counted IS NOT NULL THEN 'D' ELSE '-' END, '' ORDER BY w.day) AS pattern
            FROM scoped s CROSS JOIN workdays w
            LEFT JOIN day_status ds ON ds.user_id = s.id AND ds.work_date = w.day
            GROUP BY s.id, s.name, s.department_name
            ORDER BY s.name", $daysBindings));

        $departmentCompliance = array_map(fn (object $row): array => [
            'id' => $row->id === null ? null : (int) $row->id,
            'name' => $row->name ?? 'Tanpa departemen',
            'employees' => (int) $row->employees,
            'submitted_days' => (int) $row->submitted_days,
            'possible_days' => (int) $row->possible_days,
            'rate' => (float) $row->rate,
        ], DB::select("{$withDays}
            SELECT s.root_department_id AS id, s.department_name AS name,
                   COUNT(DISTINCT s.id) AS employees,
                   COUNT(*) FILTER (WHERE ds.counted) AS submitted_days,
                   COUNT(*) AS possible_days,
                   COALESCE(ROUND(100.0 * COUNT(*) FILTER (WHERE ds.counted) / NULLIF(COUNT(*), 0), 1), 0)::float AS rate
            FROM scoped s CROSS JOIN workdays w
            LEFT JOIN day_status ds ON ds.user_id = s.id AND ds.work_date = w.day
            GROUP BY s.root_department_id, s.department_name
            ORDER BY s.department_name", $daysBindings));

        $overall = DB::selectOne("{$withDays}
            SELECT COUNT(DISTINCT s.id) AS employees,
                   COUNT(*) FILTER (WHERE ds.counted) AS submitted_days,
                   COUNT(*) AS possible_days,
                   COALESCE(ROUND(100.0 * COUNT(*) FILTER (WHERE ds.counted) / NULLIF(COUNT(*), 0), 1), 0)::float AS rate
            FROM scoped s CROSS JOIN workdays w
            LEFT JOIN day_status ds ON ds.user_id = s.id AND ds.work_date = w.day", $daysBindings);

        $scopedOnly = "WITH {$scopedSql}";

        $hoursByProject = array_map(fn (object $row): array => [
            'id' => $row->id === null ? null : (int) $row->id,
            'code' => $row->code,
            'name' => $row->name,
            'minutes' => (int) $row->minutes,
        ], DB::select("{$scopedOnly}
            SELECT p.id, p.code, COALESCE(p.name, 'Tanpa proyek') AS name, SUM(li.duration_minutes) AS minutes
            FROM logbook_items li
            JOIN logbooks l ON l.id = li.logbook_id AND l.deleted_at IS NULL
            JOIN scoped s ON s.id = l.user_id
            LEFT JOIN projects p ON p.id = li.project_id
            WHERE l.work_date BETWEEN ? AND ? AND l.status IN ('submitted', 'approved')
            GROUP BY p.id, p.code, p.name
            ORDER BY minutes DESC, name
            LIMIT ".self::PROJECT_LIMIT, [...$scopedBindings, $from, $to]));

        $hoursByDepartment = array_map(fn (object $row): array => [
            'id' => $row->id === null ? null : (int) $row->id,
            'name' => $row->name ?? 'Tanpa departemen',
            'minutes' => (int) $row->minutes,
        ], DB::select("{$scopedOnly}
            SELECT s.root_department_id AS id, s.department_name AS name, SUM(l.total_minutes) AS minutes
            FROM logbooks l JOIN scoped s ON s.id = l.user_id
            WHERE l.deleted_at IS NULL AND l.work_date BETWEEN ? AND ? AND l.status IN ('submitted', 'approved')
            GROUP BY s.root_department_id, s.department_name
            ORDER BY minutes DESC", [...$scopedBindings, $from, $to]));

        $approval = DB::selectOne("{$scopedOnly}
            SELECT COUNT(*) FILTER (WHERE r.to_status = 'approved') AS approved,
                   COUNT(*) FILTER (WHERE r.to_status = 'revision_requested') AS revisions,
                   COUNT(*) FILTER (WHERE r.to_status = 'rejected') AS rejected,
                   ROUND(AVG(EXTRACT(EPOCH FROM (r.created_at - sub.at)) / 3600)::numeric, 1)::float AS avg_review_hours
            FROM logbook_status_histories r
            JOIN logbooks l ON l.id = r.logbook_id AND l.deleted_at IS NULL
            JOIN scoped s ON s.id = l.user_id
            JOIN LATERAL (
                SELECT MAX(h.created_at) AS at FROM logbook_status_histories h
                WHERE h.logbook_id = r.logbook_id AND h.to_status = 'submitted' AND h.created_at <= r.created_at
            ) sub ON sub.at IS NOT NULL
            WHERE r.to_status IN ('approved', 'revision_requested', 'rejected') AND l.work_date BETWEEN ? AND ?", [...$scopedBindings, $from, $to]);

        $pending = DB::selectOne("{$scopedOnly}
            SELECT COUNT(*) AS total FROM logbooks l JOIN scoped s ON s.id = l.user_id
            WHERE l.deleted_at IS NULL AND l.status = 'submitted' AND l.work_date BETWEEN ? AND ?", [...$scopedBindings, $from, $to]);

        $totalMinutes = array_sum(array_column($hoursByDepartment, 'minutes'));

        return [
            'period' => ['from' => $from, 'to' => $to],
            'days' => $days,
            'kpi' => [
                'employees' => (int) $overall->employees,
                'workdays' => count($days),
                'compliance_rate' => (float) $overall->rate,
                'submitted_days' => (int) $overall->submitted_days,
                'possible_days' => (int) $overall->possible_days,
                'total_minutes' => $totalMinutes,
                'avg_review_hours' => $approval->avg_review_hours === null ? null : (float) $approval->avg_review_hours,
                'approved_count' => (int) $approval->approved,
                'revision_count' => (int) $approval->revisions,
                'rejected_count' => (int) $approval->rejected,
                'pending_count' => (int) $pending->total,
            ],
            'compliance_by_department' => $departmentCompliance,
            'compliance_by_employee' => $employees,
            'hours_by_project' => $hoursByProject,
            'hours_by_department' => $hoursByDepartment,
        ];
    }

    private function workdaysCte(): string
    {
        return "workdays AS (
            SELECT g.d::date AS day
            FROM generate_series(?::date, ?::date, interval '1 day') AS g(d)
            WHERE EXTRACT(ISODOW FROM g.d) < 6
              AND NOT EXISTS (SELECT 1 FROM holidays h WHERE h.date = g.d::date)
        )";
    }

    /**
     * @return array{string, list<mixed>}
     */
    private function scopedCte(AnalyticsFilter $filter, bool $withDepartmentFilter = true): array
    {
        $conditions = ["u.role = 'employee'", 'u.is_active', 'u.deleted_at IS NULL'];
        $bindings = [];

        if ($filter->viewer->role !== UserRole::SuperAdmin) {
            $ids = $filter->viewer->subordinateIds();
            if ($ids === []) {
                $conditions[] = 'FALSE';
            } else {
                $conditions[] = 'u.id IN ('.implode(', ', array_fill(0, count($ids), '?')).')';
                array_push($bindings, ...$ids);
            }
        }

        if ($withDepartmentFilter && $filter->departmentId !== null) {
            $conditions[] = 'COALESCE(dep.parent_id, dep.id) = ?';
            $bindings[] = $filter->departmentId;
        }

        $where = implode(' AND ', $conditions);

        return ["scoped AS (
            SELECT u.id, u.name, COALESCE(dep.parent_id, dep.id) AS root_department_id, root.name AS department_name
            FROM users u
            LEFT JOIN departments dep ON dep.id = u.department_id
            LEFT JOIN departments root ON root.id = COALESCE(dep.parent_id, dep.id)
            WHERE {$where}
        )", $bindings];
    }
}
