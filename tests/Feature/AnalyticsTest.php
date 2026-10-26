<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\Logbook;
use App\Models\LogbookItem;
use App\Models\LogbookStatusHistory;
use App\Models\Project;
use App\Models\User;
use App\Services\AnalyticsFilter;
use App\Services\AnalyticsService;
use App\Services\LogbookService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

const PERIOD_FROM = '2026-09-07'; // Senin
const PERIOD_TO = '2026-09-13';   // Minggu

beforeEach(function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Agregasi analitik memakai fitur khusus PostgreSQL.');
    }

    config()->set('inertia.testing.ensure_pages_exist', false);
    Cache::flush();

    $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $this->manager = User::factory()->create(['role' => UserRole::Manager]);
    $this->otherManager = User::factory()->create(['role' => UserRole::Manager]);

    $this->deptA = Department::factory()->create(['name' => 'Keuangan']);
    $this->divisionA = Department::factory()->create(['name' => 'Divisi Keuangan 1', 'parent_id' => $this->deptA->id]);
    $this->deptB = Department::factory()->create(['name' => 'Operasional']);

    $make = fn (array $attrs) => User::factory()->create($attrs);
    $this->a = $make(['supervisor_id' => $this->manager->id, 'department_id' => $this->divisionA->id]);
    $this->b = $make(['supervisor_id' => $this->manager->id, 'department_id' => $this->divisionA->id]);
    $this->e = $make(['supervisor_id' => $this->manager->id, 'department_id' => $this->divisionA->id]);
    $this->inactive = $make(['supervisor_id' => $this->manager->id, 'department_id' => $this->divisionA->id, 'is_active' => false]);
    $this->c = $make(['supervisor_id' => $this->otherManager->id, 'department_id' => $this->deptB->id]);

    $this->p1 = Project::factory()->create(['name' => 'Proyek Satu']);
    $this->p2 = Project::factory()->create(['name' => 'Proyek Dua']);

    Holiday::factory()->create(['date' => '2026-09-09', 'name' => 'Libur uji']);

    // A: 07 submitted, 08 approved, 09 (libur) approved, 10 draft, 11 kosong => 2 dari 4 hari kerja
    $this->a07 = analyticsLogbook($this->a, '2026-09-07', LogbookStatus::Submitted, [['08:00', '10:00', $this->p1]]);
    $this->a08 = analyticsLogbook($this->a, '2026-09-08', LogbookStatus::Approved);
    analyticsLogbook($this->a, '2026-09-09', LogbookStatus::Approved);
    analyticsLogbook($this->a, '2026-09-10', LogbookStatus::Draft, [['09:00', '09:30', null]]);

    // B: 4 hari kerja terisi (07 dua shift), plus akhir pekan yang diabaikan => 4 dari 4
    $this->b07 = analyticsLogbook($this->b, '2026-09-07', LogbookStatus::Approved, [['09:00', '10:00', $this->p1]]);
    analyticsLogbook($this->b, '2026-09-07', LogbookStatus::Submitted, [['22:00', '02:00', $this->p2]], 'night');
    analyticsLogbook($this->b, '2026-09-08', LogbookStatus::Approved, [['09:00', '10:00', null]]);
    analyticsLogbook($this->b, '2026-09-10', LogbookStatus::Approved);
    analyticsLogbook($this->b, '2026-09-11', LogbookStatus::Approved);
    analyticsLogbook($this->b, '2026-09-12', LogbookStatus::Approved);

    // E: satu logbook ditolak => tidak dihitung terkirim
    $this->e10 = analyticsLogbook($this->e, '2026-09-10', LogbookStatus::Rejected, [['09:00', '09:30', $this->p1]]);

    // C: hierarki manager lain, 4 dari 4
    foreach (['2026-09-07', '2026-09-08', '2026-09-10', '2026-09-11'] as $date) {
        analyticsLogbook($this->c, $date, LogbookStatus::Approved);
    }

    // Riwayat: approve 6 jam, revisi 2 jam, approve (setelah submit ulang) 4 jam, reject 2 jam
    analyticsHistory($this->a08, $this->manager, 'submitted', '2026-09-08 09:00');
    analyticsHistory($this->a08, $this->manager, 'approved', '2026-09-08 15:00');
    analyticsHistory($this->b07, $this->manager, 'submitted', '2026-09-07 10:00');
    analyticsHistory($this->b07, $this->manager, 'revision_requested', '2026-09-07 12:00');
    analyticsHistory($this->b07, $this->manager, 'submitted', '2026-09-07 13:00');
    analyticsHistory($this->b07, $this->manager, 'approved', '2026-09-07 17:00');
    analyticsHistory($this->e10, $this->manager, 'submitted', '2026-09-10 08:00');
    analyticsHistory($this->e10, $this->manager, 'rejected', '2026-09-10 10:00');
});

function analyticsLogbook(User $user, string $date, LogbookStatus $status, array $items = [], string $shift = 'regular'): Logbook
{
    $logbook = Logbook::factory()->create(['user_id' => $user->id, 'work_date' => $date, 'status' => $status, 'shift' => $shift]);
    foreach ($items as $index => [$start, $end, $project]) {
        LogbookItem::factory()->create([
            'logbook_id' => $logbook->id, 'project_id' => $project?->id,
            'start_time' => $start, 'end_time' => $end, 'sort_order' => $index,
        ]);
    }

    return $logbook;
}

function analyticsHistory(Logbook $logbook, User $actor, string $to, string $at): void
{
    LogbookStatusHistory::factory()->create([
        'logbook_id' => $logbook->id, 'actor_id' => $actor->id, 'to_status' => $to, 'created_at' => $at,
    ]);
}

function analyticsReport(User $viewer, ?int $departmentId = null, string $from = PERIOD_FROM, string $to = PERIOD_TO): array
{
    $filter = new AnalyticsFilter($viewer, CarbonImmutable::parse($from), CarbonImmutable::parse($to), $departmentId);

    return app(AnalyticsService::class)->dashboard($filter);
}

describe('compliance rate', function () {
    it('menghitung per karyawan: hari kerja tanpa akhir pekan dan hari libur', function () {
        $employees = collect(analyticsReport($this->manager)['compliance_by_employee'])->keyBy('id');

        expect($employees[$this->a->id])->toMatchArray(['submitted_days' => 2, 'workdays' => 4, 'rate' => 50.0, 'pattern' => 'SSD-'])
            ->and($employees[$this->b->id])->toMatchArray(['submitted_days' => 4, 'workdays' => 4, 'rate' => 100.0, 'pattern' => 'SSSS'])
            ->and($employees[$this->e->id])->toMatchArray(['submitted_days' => 0, 'rate' => 0.0, 'pattern' => '--D-'])
            ->and($employees->keys()->all())->not->toContain($this->inactive->id, $this->c->id);
    });

    it('menghitung per departemen induk (divisi digabung) dan keseluruhan', function () {
        $report = analyticsReport($this->manager);

        expect($report['days'])->toBe(['2026-09-07', '2026-09-08', '2026-09-10', '2026-09-11'])
            ->and($report['compliance_by_department'])->toHaveCount(1)
            ->and($report['compliance_by_department'][0])->toMatchArray([
                'id' => $this->deptA->id, 'name' => 'Keuangan', 'employees' => 3,
                'submitted_days' => 6, 'possible_days' => 12, 'rate' => 50.0,
            ])
            ->and($report['kpi'])->toMatchArray(['compliance_rate' => 50.0, 'workdays' => 4, 'employees' => 3]);
    });

    it('super admin melihat semua hierarki dan bisa memfilter departemen', function () {
        $all = analyticsReport($this->admin);
        expect($all['kpi']['employees'])->toBe(4)
            ->and($all['kpi']['compliance_rate'])->toBe(62.5)
            ->and($all['compliance_by_department'])->toHaveCount(2);

        $filtered = analyticsReport($this->admin, $this->deptB->id);
        expect($filtered['kpi']['employees'])->toBe(1)->and($filtered['kpi']['compliance_rate'])->toBe(100.0);

        expect(analyticsReport($this->admin, $this->deptA->id)['kpi']['employees'])->toBe(3);
    });

    it('manager tanpa bawahan mendapat laporan kosong, bukan error', function () {
        $report = analyticsReport(User::factory()->create(['role' => UserRole::Manager]));

        expect($report['compliance_by_employee'])->toBe([])->and($report['kpi']['compliance_rate'])->toBe(0.0);
    });

    it('mengecualikan hari yang ditambahkan ke tabel hari libur', function () {
        Holiday::factory()->create(['date' => '2026-09-10']);

        $a = collect(analyticsReport($this->manager)['compliance_by_employee'])->firstWhere('id', $this->a->id);

        expect($a)->toMatchArray(['submitted_days' => 2, 'workdays' => 3, 'rate' => 66.7, 'pattern' => 'SS-']);
    });
});

describe('jam dan approval', function () {
    it('menjumlahkan jam per proyek hanya dari logbook submitted/approved', function () {
        $report = analyticsReport($this->manager);
        $byName = collect($report['hours_by_project'])->pluck('minutes', 'name');

        expect($byName['Proyek Satu'])->toBe(180)
            ->and($byName['Proyek Dua'])->toBe(240)
            ->and($byName['Tanpa proyek'])->toBe(60)
            ->and($report['hours_by_project'][0]['name'])->toBe('Proyek Dua')
            ->and($report['hours_by_department'])->toBe([['id' => $this->deptA->id, 'name' => 'Keuangan', 'minutes' => 480]])
            ->and($report['kpi']['total_minutes'])->toBe(480);
    });

    it('menghitung statistik approval', function () {
        $kpi = analyticsReport($this->manager)['kpi'];

        expect($kpi)->toMatchArray([
            'approved_count' => 2, 'revision_count' => 1, 'rejected_count' => 1,
            'avg_review_hours' => 3.5, 'pending_count' => 2,
        ]);
    });

    it('rata-rata waktu review kosong bila belum ada keputusan', function () {
        expect(analyticsReport($this->admin, from: '2026-01-05', to: '2026-01-09')['kpi']['avg_review_hours'])->toBeNull();
    });
});

describe('cache', function () {
    it('menyimpan hasil dan menghapusnya saat ada transisi status', function () {
        $user = User::factory()->create(['supervisor_id' => $this->manager->id]);
        $draft = analyticsLogbook($user, '2026-10-05', LogbookStatus::Draft, [['09:00', '10:00', null]]);
        $rate = fn () => collect(analyticsReport($this->manager, from: '2026-10-05', to: '2026-10-05')['compliance_by_employee'])
            ->firstWhere('id', $user->id)['rate'];

        expect($rate())->toBe(0.0);

        DB::table('logbooks')->where('id', $draft->id)->update(['status' => 'approved']);
        expect($rate())->toBe(0.0); // masih dari cache

        AnalyticsService::flushCache();
        expect($rate())->toBe(100.0);

        DB::table('logbooks')->where('id', $draft->id)->update(['status' => 'draft']);
        expect($rate())->toBe(100.0); // cache baru
    });

    it('transisi lewat LogbookService membersihkan cache', function () {
        $user = User::factory()->create(['supervisor_id' => $this->manager->id]);
        $draft = analyticsLogbook($user, '2026-10-06', LogbookStatus::Draft, [['09:00', '10:00', null]]);
        $rate = fn () => collect(analyticsReport($this->manager, from: '2026-10-06', to: '2026-10-06')['compliance_by_employee'])
            ->firstWhere('id', $user->id)['rate'];

        expect($rate())->toBe(0.0);

        app(LogbookService::class)->submit($draft, $user);

        expect($rate())->toBe(100.0);
    });

    it('perubahan hari libur membersihkan cache', function () {
        expect(analyticsReport($this->manager)['kpi']['workdays'])->toBe(4);

        Holiday::factory()->create(['date' => '2026-09-11']);

        expect(analyticsReport($this->manager)['kpi']['workdays'])->toBe(3);
    });
});

describe('halaman', function () {
    it('menampilkan laporan untuk manager dan super admin', function () {
        $this->actingAs($this->manager)->get('/analytics?from='.PERIOD_FROM.'&to='.PERIOD_TO)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Analytics/Index')
                ->where('filters.from', PERIOD_FROM)
                ->where('report.kpi.compliance_rate', 50)
                ->has('report.compliance_by_employee', 3)
                ->has('departments', 1)
                ->where('departments.0.name', 'Keuangan'));

        $this->actingAs($this->admin)->get('/analytics?from='.PERIOD_FROM.'&to='.PERIOD_TO.'&department_id='.$this->deptB->id)
            ->assertInertia(fn (Assert $page) => $page->where('report.kpi.employees', 1)->has('departments', 2));
    });

    it('menolak supervisor, karyawan, dan tamu', function () {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $this->actingAs($supervisor)->get('/analytics')->assertForbidden();
        $this->actingAs($this->a)->get('/analytics')->assertForbidden();
        auth()->logout();
        $this->get('/analytics')->assertRedirect('/login');
    });

    it('memakai bulan berjalan bila periode tidak diberikan', function () {
        $this->actingAs($this->manager)->get('/analytics')
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.from', today()->startOfMonth()->toDateString())
                ->where('filters.to', today()->toDateString()));
    });

    it('memvalidasi periode dengan pesan Indonesia', function () {
        $this->actingAs($this->manager)->from('/analytics')->get('/analytics?from=2026-09-10&to=2026-09-01')
            ->assertSessionHasErrors(['to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        $this->actingAs($this->manager)->from('/analytics')->get('/analytics?from=2026-01-01&to=2026-12-31')
            ->assertSessionHasErrors(['to' => 'Periode maksimal 92 hari.']);
    });
});
