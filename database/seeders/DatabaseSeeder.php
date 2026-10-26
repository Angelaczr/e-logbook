<?php

namespace Database\Seeders;

use App\Enums\LogbookStatus;
use App\Enums\ProgressStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Logbook;
use App\Models\LogbookItem;
use App\Models\LogbookStatusHistory;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    private const WORKDAYS = 30;

    private const EMPTY_DAY_RATE = 10;

    /** @var list<string> */
    private const TASKS = [
        'Menyusun laporan mingguan progres pekerjaan',
        'Rapat koordinasi dengan tim terkait',
        'Verifikasi dokumen pendukung transaksi',
        'Rekonsiliasi data bulanan',
        'Menindaklanjuti permintaan dari unit lain',
        'Pembaruan prosedur kerja dan dokumentasi',
        'Pengecekan kualitas hasil pekerjaan',
        'Menyiapkan bahan presentasi manajemen',
        'Input dan validasi data ke sistem',
        'Pendampingan rekan kerja baru',
        'Analisis kendala operasional harian',
        'Menjawab pertanyaan pelanggan internal',
        'Kunjungan lapangan dan pencatatan temuan',
        'Perbaikan dan pengujian fitur sistem internal',
        'Penyusunan jadwal kerja minggu berikutnya',
    ];

    /** @var list<string> */
    private const REVISION_NOTES = [
        'Mohon lengkapi uraian pekerjaan dan lampirkan bukti output.',
        'Durasi beberapa item tidak sesuai, mohon dicek ulang.',
        'Pilih proyek yang sesuai untuk setiap aktivitas.',
    ];

    /** @var list<string> */
    private const REJECT_NOTES = [
        'Uraian pekerjaan tidak dapat diverifikasi.',
        'Aktivitas tidak termasuk lingkup pekerjaan.',
    ];

    private CarbonImmutable $now;

    public function run(): void
    {
        mt_srand(20261024);
        fake()->seed(20261024);
        $this->now = CarbonImmutable::now();

        DB::transaction(function (): void {
            $projects = $this->seedProjects();
            $employeesBySupervisor = $this->seedOrganization();
            $this->seedLogbooks($employeesBySupervisor, $projects);
        });

        $this->printAccounts();
    }

    /**
     * @return list<Project>
     */
    private function seedProjects(): array
    {
        $projects = [];
        foreach (['Implementasi Sistem ERP', 'Migrasi Data Pelanggan', 'Pengembangan Portal Mitra', 'Audit Proses Internal'] as $name) {
            $projects[] = Project::factory()->create(['name' => $name]);
        }
        foreach (['Operasional Harian', 'Pelatihan & Pengembangan'] as $name) {
            $projects[] = Project::factory()->costCenter()->create(['name' => $name]);
        }

        return $projects;
    }

    /**
     * Membuat departemen, divisi, dan pengguna.
     *
     * @return array<int, list<User>> karyawan per id supervisor
     */
    private function seedOrganization(): array
    {
        $user = fn (string $email, string $name, UserRole $role, ?int $departmentId, ?int $supervisorId, string $title): User => User::factory()->create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'department_id' => $departmentId,
            'supervisor_id' => $supervisorId,
            'job_title' => $title,
            'employee_id_number' => 'EMP'.str_pad((string) (User::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT),
        ]);

        $id = fake('id_ID');
        $user('admin@elogbook.test', $id->name(), UserRole::SuperAdmin, null, null, 'Administrator Sistem');

        $divisionCounts = [2, 2, 1];
        $employeeTotals = [10, 10, 10];
        $employeesBySupervisor = [];
        $employeeNumber = 1;

        foreach (['Keuangan', 'Operasional', 'Teknologi Informasi'] as $index => $departmentName) {
            $department = Department::factory()->create(['code' => 'DEP-0'.($index + 1), 'name' => $departmentName]);
            $manager = $user('manager'.($index + 1).'@elogbook.test', $id->name(), UserRole::Manager, $department->id, null, 'Kepala '.$departmentName);
            $department->update(['head_id' => $manager->id]);

            $divisions = [];
            for ($d = 1; $d <= $divisionCounts[$index]; $d++) {
                $divisions[] = Department::factory()->create([
                    'code' => $department->code.'-0'.$d,
                    'name' => 'Divisi '.$departmentName.' '.$d,
                    'parent_id' => $department->id,
                ]);
            }

            foreach ([1, 2] as $s) {
                $division = $divisions[($s - 1) % count($divisions)];
                $supervisor = $user('supervisor'.($index * 2 + $s).'@elogbook.test', $id->name(), UserRole::Supervisor, $division->id, $manager->id, 'Supervisor '.$division->name);
                $division->head_id ??= $supervisor->id;
                $division->save();

                $count = intdiv($employeeTotals[$index], 2);
                for ($e = 0; $e < $count; $e++) {
                    $employeesBySupervisor[$supervisor->id][] = $user(
                        'employee'.str_pad((string) $employeeNumber++, 2, '0', STR_PAD_LEFT).'@elogbook.test',
                        $id->name(),
                        UserRole::Employee,
                        $division->id,
                        $supervisor->id,
                        'Staf '.$departmentName,
                    );
                }
            }
        }

        return $employeesBySupervisor;
    }

    /**
     * @param  array<int, list<User>>  $employeesBySupervisor
     * @param  list<Project>  $projects
     */
    private function seedLogbooks(array $employeesBySupervisor, array $projects): void
    {
        $days = $this->recentWorkdays();

        foreach ($employeesBySupervisor as $supervisorId => $employees) {
            $supervisor = User::findOrFail($supervisorId);

            foreach ($employees as $employee) {
                foreach ($days as $age => $date) {
                    if (mt_rand(1, 100) <= self::EMPTY_DAY_RATE) {
                        continue;
                    }

                    $reviewer = mt_rand(1, 100) <= 85 ? $supervisor : User::findOrFail($supervisor->supervisor_id);
                    $this->seedLogbook($employee, $reviewer, $date, $age, $projects);
                }
            }
        }
    }

    /**
     * @return array<int, CarbonImmutable> hari kerja terbaru lebih dulu, indeks = umur dalam hari kerja
     */
    private function recentWorkdays(): array
    {
        $days = [];
        $cursor = $this->now->startOfDay();

        while (count($days) < self::WORKDAYS) {
            if ($cursor->isWeekday()) {
                $days[] = $cursor;
            }
            $cursor = $cursor->subDay();
        }

        return $days;
    }

    /**
     * @param  list<Project>  $projects
     */
    private function seedLogbook(User $employee, User $reviewer, CarbonImmutable $date, int $age, array $projects): void
    {
        $path = $this->pickPath($age);
        $final = $path[array_key_last($path)];

        $submittedAt = $this->clamp($date->setTime(17, 30)->addMinutes(mt_rand(0, 40)));
        $reviewedAt = $this->clamp($submittedAt->addHours(mt_rand(3, 28)));
        $revisions = count(array_filter($path, fn (LogbookStatus $s) => $s === LogbookStatus::RevisionRequested))
            - ($final === LogbookStatus::RevisionRequested ? 1 : 0);
        $note = match ($final) {
            LogbookStatus::RevisionRequested => self::REVISION_NOTES[array_rand(self::REVISION_NOTES)],
            LogbookStatus::Rejected => self::REJECT_NOTES[array_rand(self::REJECT_NOTES)],
            default => null,
        };

        $logbook = Logbook::factory()->create([
            'user_id' => $employee->id,
            'work_date' => $date->toDateString(),
            'status' => $final,
            'submitted_at' => $final === LogbookStatus::Draft ? null : $submittedAt,
            'approved_by' => $final === LogbookStatus::Approved ? $reviewer->id : null,
            'approved_at' => $final === LogbookStatus::Approved ? $reviewedAt : null,
            'rejection_reason' => $note,
            'revision_count' => max(0, $revisions),
        ]);

        $this->seedItems($logbook, $projects, $final);
        $this->seedHistories($logbook, $path, $employee, $reviewer, $submittedAt, $reviewedAt, $note);
    }

    /**
     * @param  list<Project>  $projects
     */
    private function seedItems(Logbook $logbook, array $projects, LogbookStatus $final): void
    {
        $count = mt_rand(3, 6);
        $cursor = 8 * 60;

        for ($i = 0; $i < $count; $i++) {
            if ($cursor >= 12 * 60 && $cursor < 13 * 60) {
                $cursor = 13 * 60;
            }

            $length = mt_rand(2, 5) * 15;
            if ($cursor + $length > 17 * 60 || ($cursor < 12 * 60 && $cursor + $length > 12 * 60)) {
                $length = min(17 * 60, $cursor < 12 * 60 ? 12 * 60 : 17 * 60) - $cursor;
            }
            if ($length <= 0) {
                break;
            }

            $progress = $final === LogbookStatus::Draft && mt_rand(0, 1) === 0
                ? [ProgressStatus::InProgress, ProgressStatus::Pending][mt_rand(0, 1)]
                : (mt_rand(1, 100) <= 85 ? ProgressStatus::Completed : ProgressStatus::InProgress);

            LogbookItem::factory()->create([
                'logbook_id' => $logbook->id,
                'project_id' => mt_rand(1, 100) <= 85 ? $projects[array_rand($projects)]->id : null,
                'task_description' => self::TASKS[array_rand(self::TASKS)],
                'start_time' => $this->clock($cursor),
                'end_time' => $this->clock($cursor + $length),
                'status_progress' => $progress,
                'output_link_or_attachment' => mt_rand(1, 100) <= 35
                    ? 'https://docs.example.test/logbook/'.$logbook->id.'-'.($i + 1)
                    : null,
                'sort_order' => $i,
            ]);

            $cursor += $length;
        }
    }

    /**
     * @param  list<LogbookStatus>  $path
     */
    private function seedHistories(Logbook $logbook, array $path, User $employee, User $reviewer, CarbonImmutable $submittedAt, CarbonImmutable $reviewedAt, ?string $note): void
    {
        $at = $submittedAt;

        for ($i = 1; $i < count($path); $i++) {
            $to = $path[$i];
            $byEmployee = $to === LogbookStatus::Submitted;
            $at = $i === 1 ? $submittedAt : $this->clamp($at->addHours(mt_rand(1, 20)));
            $isFinalStep = $i === count($path) - 1;

            LogbookStatusHistory::factory()->create([
                'logbook_id' => $logbook->id,
                'actor_id' => $byEmployee ? $employee->id : $reviewer->id,
                'from_status' => $path[$i - 1],
                'to_status' => $to,
                'notes' => match (true) {
                    $to === LogbookStatus::Approved || $byEmployee => null,
                    $isFinalStep => $note,
                    default => self::REVISION_NOTES[array_rand(self::REVISION_NOTES)],
                },
                'created_at' => ! $byEmployee && $isFinalStep && $to === LogbookStatus::Approved ? $reviewedAt : $at,
            ]);
        }
    }

    /**
     * Jalur status; makin lama, makin besar peluang sudah selesai ditinjau.
     *
     * @return list<LogbookStatus>
     */
    private function pickPath(int $age): array
    {
        $draft = LogbookStatus::Draft;
        $sub = LogbookStatus::Submitted;
        $rev = LogbookStatus::RevisionRequested;
        $ok = LogbookStatus::Approved;
        $no = LogbookStatus::Rejected;

        $table = match (true) {
            $age === 0 => [[50, [$draft]], [50, [$draft, $sub]]],
            $age <= 3 => [[10, [$draft]], [45, [$draft, $sub]], [30, [$draft, $sub, $ok]], [15, [$draft, $sub, $rev]]],
            default => [[78, [$draft, $sub, $ok]], [9, [$draft, $sub, $rev, $sub, $ok]], [5, [$draft, $sub, $rev]], [4, [$draft, $sub, $no]], [4, [$draft, $sub]]],
        };

        $roll = mt_rand(1, 100);
        foreach ($table as [$weight, $path]) {
            if (($roll -= $weight) <= 0) {
                return $path;
            }
        }

        return $table[array_key_last($table)][1];
    }

    private function clamp(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->greaterThan($this->now) ? $this->now : $moment;
    }

    private function clock(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function printAccounts(): void
    {
        $rows = User::query()
            ->with('department')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (User $user) => $user->role->value)
            ->flatMap(fn ($users) => $users->take($users->first()->role === UserRole::Employee ? 3 : 99))
            ->map(fn (User $user) => [$user->role->label(), $user->email, $user->name, $user->department?->name ?? '-'])
            ->all();

        $this->command->newLine();
        $this->command->info('Akun demo (password semua akun: "password"; karyawan hanya 3 contoh)');
        $this->command->table(['Role', 'Email', 'Nama', 'Departemen/Divisi'], $rows);
    }
}
