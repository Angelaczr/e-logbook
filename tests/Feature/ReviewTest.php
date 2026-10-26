<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Logbook;
use App\Models\LogbookItem;
use App\Models\LogbookView;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $this->manager = User::factory()->create(['role' => UserRole::Manager]);
    $this->supervisor = User::factory()->create(['role' => UserRole::Supervisor, 'supervisor_id' => $this->manager->id]);
    $this->employee = User::factory()->create(['supervisor_id' => $this->supervisor->id]);
    $this->otherSupervisor = User::factory()->create(['role' => UserRole::Supervisor, 'supervisor_id' => $this->manager->id]);
    $this->otherEmployee = User::factory()->create(['supervisor_id' => $this->otherSupervisor->id]);
    $this->outsider = User::factory()->create();
});

function reviewLogbook(User $owner, LogbookStatus $status = LogbookStatus::Submitted, ?string $date = null): Logbook
{
    $logbook = Logbook::factory()->create([
        'user_id' => $owner->id,
        'status' => $status,
        'work_date' => $date ?? fake()->unique()->dateTimeBetween('-200 days', '-1 day')->format('Y-m-d'),
        'submitted_at' => now(),
    ]);
    LogbookItem::factory()->create(['logbook_id' => $logbook->id]);

    return $logbook;
}

describe('index', function () {
    it('menampilkan hanya logbook submitted milik bawahan langsung supervisor', function () {
        $mine = reviewLogbook($this->employee);
        reviewLogbook($this->otherEmployee);
        reviewLogbook($this->employee, LogbookStatus::Approved);
        reviewLogbook($this->employee, LogbookStatus::Draft);

        $this->actingAs($this->supervisor)->get('/reviews')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reviews/Index')
                ->has('logbooks.data', 1)
                ->where('logbooks.data.0.id', $mine->id)
                ->where('logbooks.data.0.employee.name', $this->employee->name)
                ->where('filters.status', 'submitted')
                ->where('departments', null)
                ->has('employees', 1));
    });

    it('manager melihat seluruh hierarki di bawahnya, super admin melihat semua', function () {
        reviewLogbook($this->employee);
        reviewLogbook($this->otherEmployee);
        reviewLogbook($this->outsider);

        $this->actingAs($this->manager)->get('/reviews')->assertInertia(fn (Assert $page) => $page->has('logbooks.data', 2));
        $this->actingAs($this->admin)->get('/reviews')->assertInertia(fn (Assert $page) => $page->has('logbooks.data', 3));
    });

    it('memfilter bawahan, rentang tanggal, dan status', function () {
        reviewLogbook($this->employee, date: '2026-09-01');
        reviewLogbook($this->employee, date: '2026-09-20');
        reviewLogbook($this->otherEmployee, date: '2026-09-10');
        reviewLogbook($this->employee, LogbookStatus::Approved, '2026-09-15');

        $as = fn () => $this->actingAs($this->manager);

        $as()->get("/reviews?user_id={$this->otherEmployee->id}")->assertInertia(fn (Assert $page) => $page->has('logbooks.data', 1));
        $as()->get('/reviews?from=2026-09-05&to=2026-09-25')->assertInertia(fn (Assert $page) => $page->has('logbooks.data', 2));
        $as()->get('/reviews?status=approved')->assertInertia(fn (Assert $page) => $page
            ->has('logbooks.data', 1)->where('filters.status', 'approved'));
    });

    it('memfilter departemen hanya untuk manager dan super admin', function () {
        $department = Department::factory()->create();
        $division = Department::factory()->create(['parent_id' => $department->id]);
        $this->employee->update(['department_id' => $division->id]);
        reviewLogbook($this->employee);
        reviewLogbook($this->otherEmployee);

        $this->actingAs($this->manager)->get("/reviews?department_id={$department->id}")
            ->assertInertia(fn (Assert $page) => $page->has('logbooks.data', 1)->has('departments', 1));

        $this->actingAs($this->supervisor)->get("/reviews?department_id={$department->id}")
            ->assertInertia(fn (Assert $page) => $page->has('logbooks.data', 1)->where('filters.department_id', null));
    });

    it('memberi pesan Indonesia untuk filter tidak valid', function () {
        $this->actingAs($this->supervisor)->from('/reviews')->get('/reviews?from=2026-09-10&to=2026-09-01&status=draft')
            ->assertSessionHasErrors(['to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.', 'status']);
    });

    it('tidak melakukan N+1 query', function () {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->manager)->get('/reviews')->assertOk();

            return count(DB::getQueryLog());
        };

        reviewLogbook($this->employee);
        $count(); // pemanasan: hasil subordinateIds() di-cache per instance
        $few = $count();
        foreach (range(1, 8) as $_) {
            reviewLogbook([$this->employee, $this->otherEmployee][array_rand([0, 1])]);
        }

        expect($count())->toBe($few);
    });

    it('membuka detail lewat ?review dan menolak logbook di luar wewenang', function () {
        $own = reviewLogbook($this->employee);
        $foreign = reviewLogbook($this->otherEmployee);

        $this->actingAs($this->supervisor)->get("/reviews?review={$own->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('selected.id', $own->id)
                ->where('selected.can.review', true)
                ->has('selected.items', 1)
                ->has('selected.histories'));

        $this->actingAs($this->supervisor)->get("/reviews?review={$foreign->id}")->assertForbidden();
    });

    it('menolak karyawan biasa dan tamu', function () {
        $this->actingAs($this->employee)->get('/reviews')->assertForbidden();
        auth()->logout();
        $this->get('/reviews')->assertRedirect('/login');
    });
});

describe('show', function () {
    it('menampilkan detail untuk atasan dan menolak supervisor lain', function () {
        $logbook = reviewLogbook($this->employee);

        $this->actingAs($this->supervisor)->get("/reviews/{$logbook->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Reviews/Show')->where('logbook.id', $logbook->id));
        $this->actingAs($this->otherSupervisor)->get("/reviews/{$logbook->id}")->assertForbidden();
    });
});

describe('approve', function () {
    it('menyetujui dengan catatan opsional dan mencatat riwayat', function () {
        $logbook = reviewLogbook($this->employee);

        $this->actingAs($this->supervisor)->post("/reviews/{$logbook->id}/approve", ['note' => 'Kerja bagus'])
            ->assertRedirect()->assertSessionHas('success');

        expect($logbook->fresh()->status)->toBe(LogbookStatus::Approved)
            ->and($logbook->fresh()->approved_by)->toBe($this->supervisor->id)
            ->and($logbook->histories()->sole()->notes)->toBe('Kerja bagus');
    });

    it('menolak supervisor yang bukan atasan logbook tersebut dengan 403', function () {
        $logbook = reviewLogbook($this->employee);

        $this->actingAs($this->otherSupervisor)->post("/reviews/{$logbook->id}/approve")->assertForbidden();

        expect($logbook->fresh()->status)->toBe(LogbookStatus::Submitted);
    });

    it('menolak karyawan biasa, review logbook sendiri, dan status yang sudah final', function () {
        $logbook = reviewLogbook($this->employee);
        $this->actingAs($this->employee)->post("/reviews/{$logbook->id}/approve")->assertForbidden();

        $own = reviewLogbook($this->supervisor);
        $this->actingAs($this->supervisor)->post("/reviews/{$own->id}/approve")->assertForbidden();

        $done = reviewLogbook($this->employee, LogbookStatus::Approved);
        $this->actingAs($this->supervisor)->post("/reviews/{$done->id}/approve")->assertStatus(409);
    });

    it('manager dan super admin dapat menyetujui bawahan tidak langsung', function () {
        $first = reviewLogbook($this->employee);
        $second = reviewLogbook($this->outsider);

        $this->actingAs($this->manager)->post("/reviews/{$first->id}/approve")->assertRedirect();
        $this->actingAs($this->admin)->post("/reviews/{$second->id}/approve")->assertRedirect();

        expect($first->fresh()->status)->toBe(LogbookStatus::Approved)
            ->and($second->fresh()->status)->toBe(LogbookStatus::Approved);
    });
});

describe('request revision dan reject', function () {
    it('meminta revisi dengan alasan dan menaikkan revision_count', function () {
        $logbook = reviewLogbook($this->employee);

        $this->actingAs($this->supervisor)->post("/reviews/{$logbook->id}/request-revision", ['reason' => 'Lengkapi uraian pekerjaan'])
            ->assertRedirect();

        $logbook->refresh();
        expect($logbook->status)->toBe(LogbookStatus::RevisionRequested)
            ->and($logbook->revision_count)->toBe(1)
            ->and($logbook->rejection_reason)->toBe('Lengkapi uraian pekerjaan');
    });

    it('menolak logbook dengan alasan', function () {
        $logbook = reviewLogbook($this->employee);

        $this->actingAs($this->supervisor)->post("/reviews/{$logbook->id}/reject", ['reason' => 'Tidak sesuai lingkup kerja'])
            ->assertRedirect();

        expect($logbook->fresh()->status)->toBe(LogbookStatus::Rejected);
    });

    it('mewajibkan alasan minimal 10 karakter untuk revisi dan reject', function (string $action) {
        $logbook = reviewLogbook($this->employee);
        $as = fn () => $this->actingAs($this->supervisor)->from('/reviews');

        $as()->post("/reviews/{$logbook->id}/{$action}", [])
            ->assertSessionHasErrors(['reason' => 'Alasan wajib diisi.']);
        $as()->post("/reviews/{$logbook->id}/{$action}", ['reason' => 'terlalu'])
            ->assertSessionHasErrors(['reason' => 'Alasan minimal 10 karakter.']);

        expect($logbook->fresh()->status)->toBe(LogbookStatus::Submitted);
    })->with(['request-revision', 'reject']);

    it('menolak supervisor yang bukan atasan', function (string $action) {
        $logbook = reviewLogbook($this->employee);

        $this->actingAs($this->otherSupervisor)->post("/reviews/{$logbook->id}/{$action}", ['reason' => 'Alasan yang cukup panjang'])
            ->assertForbidden();

        expect($logbook->fresh()->status)->toBe(LogbookStatus::Submitted);
    })->with(['request-revision', 'reject']);
});

describe('bulk approve', function () {
    it('menyetujui yang berhak dan melewati sisanya', function () {
        $a = reviewLogbook($this->employee);
        $b = reviewLogbook($this->employee);
        $foreign = reviewLogbook($this->otherEmployee);
        $done = reviewLogbook($this->employee, LogbookStatus::Approved);

        $this->actingAs($this->supervisor)->post('/reviews/bulk-approve', ['ids' => [$a->id, $b->id, $foreign->id, $done->id, 999999]])
            ->assertRedirect()
            ->assertSessionHas('success', '2 logbook disetujui.')
            ->assertSessionHas('error', '3 logbook dilewati karena sudah diproses atau bukan wewenang Anda.');

        expect($a->fresh()->status)->toBe(LogbookStatus::Approved)
            ->and($b->fresh()->status)->toBe(LogbookStatus::Approved)
            ->and($foreign->fresh()->status)->toBe(LogbookStatus::Submitted);
    });

    it('memvalidasi daftar id', function () {
        $as = fn () => $this->actingAs($this->supervisor)->from('/reviews');

        $as()->post('/reviews/bulk-approve', [])->assertSessionHasErrors(['ids' => 'Pilih minimal satu logbook.']);
        $as()->post('/reviews/bulk-approve', ['ids' => range(1, 51)])->assertSessionHasErrors('ids');
    });

    it('menolak karyawan biasa', function () {
        $this->actingAs($this->employee)->post('/reviews/bulk-approve', ['ids' => [1]])->assertForbidden();
    });
});

describe('urutan terakhir dibuka', function () {
    it('mencatat pembukaan detail per peninjau dan tidak untuk yang dilarang', function () {
        $logbook = reviewLogbook($this->employee);

        $this->actingAs($this->supervisor)->get("/reviews?review={$logbook->id}")->assertOk();
        $this->actingAs($this->manager)->get("/reviews/{$logbook->id}")->assertOk();
        $this->actingAs($this->otherSupervisor)->get("/reviews/{$logbook->id}")->assertForbidden();

        $views = LogbookView::where('logbook_id', $logbook->id)->pluck('user_id')->all();
        expect($views)->toEqualCanonicalizing([$this->supervisor->id, $this->manager->id]);
    });

    it('membatasi penulisan satu kali per menit', function () {
        $logbook = reviewLogbook($this->employee);
        $open = fn () => $this->actingAs($this->supervisor)->get("/reviews?review={$logbook->id}");

        $open();
        $first = LogbookView::sole()->last_viewed_at;

        $this->travel(30)->seconds();
        $open();
        expect(LogbookView::sole()->last_viewed_at->equalTo($first))->toBeTrue();

        $this->travel(61)->seconds();
        $open();
        expect(LogbookView::sole()->last_viewed_at->greaterThan($first))->toBeTrue();
    });

    it('tidak mencatat pemilik yang membuka logbook sendiri', function () {
        $own = reviewLogbook($this->supervisor);

        $this->actingAs($this->supervisor)->get("/reviews/{$own->id}")->assertOk();

        expect(LogbookView::count())->toBe(0);
    });

    it('mengurutkan berdasarkan terakhir dibuka milik peninjau sendiri, bawaan tetap tanggal kerja', function () {
        $old = reviewLogbook($this->employee, date: '2026-09-01');
        $mid = reviewLogbook($this->employee, date: '2026-09-02');
        $new = reviewLogbook($this->employee, date: '2026-09-03');

        $this->actingAs($this->supervisor)->get("/reviews?review={$new->id}");
        $this->travel(2)->minutes();
        $this->actingAs($this->supervisor)->get("/reviews?review={$mid->id}");
        // dibuka peninjau lain: tidak boleh memengaruhi urutan supervisor
        $this->actingAs($this->manager)->get("/reviews?review={$old->id}");

        $ids = fn (string $query) => $this->actingAs($this->supervisor)->get("/reviews{$query}")
            ->viewData('page')['props']['logbooks']['data'];

        expect(collect($ids(''))->pluck('id')->all())->toBe([$old->id, $mid->id, $new->id])
            ->and(collect($ids('?sort=viewed'))->pluck('id')->all())->toBe([$mid->id, $new->id, $old->id]);

        $this->actingAs($this->supervisor)->get('/reviews?sort=viewed')
            ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'viewed'));
        $this->actingAs($this->supervisor)->get('/reviews')
            ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'date'));
    });

    it('menolak nilai urutan yang tidak dikenal', function () {
        $this->actingAs($this->supervisor)->from('/reviews')->get('/reviews?sort=acak')
            ->assertSessionHasErrors(['sort' => 'Urutan yang dipilih tidak valid.']);
    });
});
