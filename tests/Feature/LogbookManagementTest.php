<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\Logbook;
use App\Models\LogbookItem;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('inertia.testing.ensure_pages_exist', false);

    $this->supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
    $this->employee = User::factory()->create(['supervisor_id' => $this->supervisor->id]);
    $this->stranger = User::factory()->create();
    $this->project = Project::factory()->create();
});

function formItem(array $extra = []): array
{
    return [
        'project_id' => null,
        'task_description' => 'Menyusun laporan mingguan',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'status_progress' => 'completed',
        'output_link_or_attachment' => 'https://docs.example.test/laporan',
        ...$extra,
    ];
}

function formData(array $items = [], array $extra = []): array
{
    return ['work_date' => today()->subDay()->toDateString(), 'items' => $items ?: [formItem()], ...$extra];
}

function draftOf(User $owner, LogbookStatus $status = LogbookStatus::Draft): Logbook
{
    $logbook = Logbook::factory()->create(['user_id' => $owner->id, 'status' => $status]);
    LogbookItem::factory()->create(['logbook_id' => $logbook->id]);

    return $logbook;
}

describe('create', function () {
    it('menyimpan draft dan mengarahkan ke halaman detail', function () {
        $response = $this->actingAs($this->employee)->post('/logbooks', formData([
            formItem(['project_id' => $this->project->id]),
            formItem(['start_time' => '10:00', 'end_time' => '11:30']),
        ]));

        $logbook = Logbook::sole();
        $response->assertRedirect("/logbooks/{$logbook->id}")->assertSessionHas('success');

        expect($logbook->user_id)->toBe($this->employee->id)
            ->and($logbook->status)->toBe(LogbookStatus::Draft)
            ->and($logbook->total_minutes)->toBe(210)
            ->and($logbook->items)->toHaveCount(2);
    });

    it('menampilkan form dengan daftar proyek aktif saja', function () {
        Project::factory()->inactive()->create();

        $this->actingAs($this->employee)->get('/logbooks/create')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Logbooks/Form')->where('logbook', null)
                ->has('projects', 1)
                ->where('projects.0.id', $this->project->id));
    });

    it('menolak tamu', function () {
        $this->post('/logbooks', formData())->assertRedirect('/login');
    });
});

describe('validasi', function () {
    it('memberi pesan Bahasa Indonesia untuk input salah', function () {
        $inactive = Project::factory()->inactive()->create();

        $response = $this->actingAs($this->employee)->from('/logbooks/create')->post('/logbooks', [
            'work_date' => today()->addDay()->toDateString(),
            'items' => [formItem([
                'project_id' => $inactive->id,
                'task_description' => 'pendek',
                'start_time' => '8.00',
                'end_time' => '10:00',
                'status_progress' => 'selesai',
                'output_link_or_attachment' => 'bukan-url',
            ])],
        ]);

        $response->assertRedirect('/logbooks/create')->assertSessionHasErrors([
            'work_date' => 'Tanggal kerja tidak boleh di masa depan.',
            'items.0.project_id' => 'Proyek yang dipilih tidak valid atau sudah tidak aktif.',
            'items.0.task_description' => 'Uraian pekerjaan minimal 10 karakter.',
            'items.0.start_time' => 'Jam mulai harus berformat jam:menit (contoh 08:30).',
            'items.0.status_progress' => 'Status progres yang dipilih tidak valid.',
            'items.0.output_link_or_attachment' => 'Tautan hasil kerja harus berupa URL yang valid.',
        ]);
    });

    it('mewajibkan minimal 1 dan maksimal 30 item', function () {
        $this->actingAs($this->employee)->post('/logbooks', ['work_date' => today()->toDateString(), 'items' => []])
            ->assertSessionHasErrors('items');

        $thirtyOne = array_fill(0, 31, formItem());
        $this->actingAs($this->employee)->post('/logbooks', formData($thirtyOne))
            ->assertSessionHasErrors(['items' => 'Maksimal 30 item aktivitas dalam satu logbook.']);
    });

    it('melaporkan jam yang tumpang tindih dari aturan bisnis', function () {
        $this->actingAs($this->employee)->post('/logbooks', formData([
            formItem(),
            formItem(['start_time' => '09:00', 'end_time' => '11:00']),
        ]))->assertSessionHasErrors('items.1.start_time');

        expect(Logbook::count())->toBe(0);
    });

    it('melarang id item saat membuat dan menolak duplikat tanggal-shift', function () {
        $this->actingAs($this->employee)->post('/logbooks', formData([formItem(['id' => 1])]))
            ->assertSessionHasErrors('items.0.id');

        $this->actingAs($this->employee)->post('/logbooks', formData())->assertSessionDoesntHaveErrors();
        $this->actingAs($this->employee)->post('/logbooks', formData())
            ->assertSessionHasErrors(['work_date' => 'Logbook untuk tanggal dan shift ini sudah ada.']);
    });

    it('menolak id item milik logbook lain saat update', function () {
        $logbook = draftOf($this->employee);
        $foreign = draftOf($this->stranger)->items()->first();

        $this->actingAs($this->employee)->put("/logbooks/{$logbook->id}", formData([formItem(['id' => $foreign->id])]))
            ->assertSessionHasErrors('items.0.id');
    });
});

describe('update', function () {
    it('memperbarui draft: ubah, tambah, dan hapus item', function () {
        $logbook = draftOf($this->employee);
        $existing = $logbook->items()->first();

        $this->actingAs($this->employee)->put("/logbooks/{$logbook->id}", formData([
            formItem(['id' => $existing->id, 'task_description' => 'Uraian yang sudah diperbarui']),
            formItem(['start_time' => '13:00', 'end_time' => '14:00']),
        ], ['work_date' => $logbook->work_date->toDateString()]))
            ->assertRedirect("/logbooks/{$logbook->id}");

        expect($logbook->items()->count())->toBe(2)
            ->and($existing->fresh()->task_description)->toBe('Uraian yang sudah diperbarui');
    });

    it('mengizinkan edit saat revision_requested', function () {
        $logbook = draftOf($this->employee, LogbookStatus::RevisionRequested);

        $this->actingAs($this->employee)->get("/logbooks/{$logbook->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->component('Logbooks/Form')->where('logbook.can.update', true));
    });

    it('menolak edit dan update saat status submitted', function () {
        $logbook = draftOf($this->employee, LogbookStatus::Submitted);

        $this->actingAs($this->employee)->get("/logbooks/{$logbook->id}/edit")->assertForbidden();
        $this->actingAs($this->employee)->put("/logbooks/{$logbook->id}", formData([formItem(['task_description' => 'Dicoba diubah lagi'])]))
            ->assertForbidden();

        expect($logbook->items()->first()->task_description)->not->toBe('Dicoba diubah lagi');
    });
});

describe('submit', function () {
    it('mengajukan draft milik sendiri', function () {
        $logbook = draftOf($this->employee);

        $this->actingAs($this->employee)->post("/logbooks/{$logbook->id}/submit")
            ->assertRedirect("/logbooks/{$logbook->id}")->assertSessionHas('success');

        expect($logbook->fresh()->status)->toBe(LogbookStatus::Submitted)
            ->and($logbook->histories()->count())->toBe(1);
    });

    it('menolak pengajuan ulang dan logbook tanpa item', function () {
        $submitted = draftOf($this->employee, LogbookStatus::Submitted);
        $empty = Logbook::factory()->create(['user_id' => $this->employee->id, 'work_date' => '2026-01-05']);

        $this->actingAs($this->employee)->post("/logbooks/{$submitted->id}/submit")->assertForbidden();
        $this->actingAs($this->employee)->post("/logbooks/{$empty->id}/submit")->assertForbidden();
    });
});

describe('akses logbook orang lain', function () {
    it('ditolak untuk karyawan lain pada semua aksi', function () {
        $logbook = draftOf($this->employee);
        $id = $logbook->id;
        $as = fn () => $this->actingAs($this->stranger);

        $as()->get("/logbooks/{$id}")->assertForbidden();
        $as()->get("/logbooks/{$id}/edit")->assertForbidden();
        $as()->put("/logbooks/{$id}", formData())->assertForbidden();
        $as()->post("/logbooks/{$id}/submit")->assertForbidden();
        $as()->delete("/logbooks/{$id}")->assertForbidden();

        expect($logbook->fresh()->trashed())->toBeFalse();
    });

    it('atasan langsung boleh melihat tetapi tidak mengubah', function () {
        $logbook = draftOf($this->employee);

        $this->actingAs($this->supervisor)->get("/logbooks/{$logbook->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('logbook.can.update', false));
        $this->actingAs($this->supervisor)->put("/logbooks/{$logbook->id}", formData())->assertForbidden();
    });
});

describe('index', function () {
    it('hanya menampilkan logbook milik sendiri dengan props ringkas', function () {
        draftOf($this->employee);
        draftOf($this->stranger);

        $this->actingAs($this->employee)->get('/logbooks')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Logbooks/Index')
                ->has('logbooks.data', 1)
                ->has('logbooks.data.0', fn (Assert $row) => $row
                    ->hasAll(['id', 'work_date', 'shift', 'status', 'status_label', 'status_color', 'total_minutes', 'revision_count', 'items_count'])
                    ->etc())
                ->missing('logbooks.data.0.user_id')
                ->missing('logbooks.data.0.rejection_reason'));
    });

    it('memfilter berdasarkan bulan dan status', function () {
        $make = fn (string $date, LogbookStatus $status) => Logbook::factory()->create([
            'user_id' => $this->employee->id, 'work_date' => $date, 'status' => $status,
        ]);
        $make('2026-09-10', LogbookStatus::Approved);
        $make('2026-09-30', LogbookStatus::Draft);
        $make('2026-10-01', LogbookStatus::Approved);

        $this->actingAs($this->employee)->get('/logbooks?month=2026-09')
            ->assertInertia(fn (Assert $page) => $page->has('logbooks.data', 2)->where('filters.month', '2026-09'));

        $this->actingAs($this->employee)->get('/logbooks?month=2026-09&status=approved')
            ->assertInertia(fn (Assert $page) => $page->has('logbooks.data', 1)->where('filters.status', 'approved'));
    });

    it('memberi pesan Indonesia untuk filter tidak valid', function () {
        $this->actingAs($this->employee)->from('/logbooks')->get('/logbooks?month=bulan-ini&status=zzz')
            ->assertSessionHasErrors(['month' => 'Bulan harus berformat TTTT-BB (contoh 2026-10).', 'status']);
    });

    it('membagi halaman per 15 data', function () {
        foreach (range(1, 16) as $i) {
            Logbook::factory()->create(['user_id' => $this->employee->id, 'work_date' => today()->subDays($i)->toDateString()]);
        }

        $this->actingAs($this->employee)->get('/logbooks')
            ->assertInertia(fn (Assert $page) => $page->has('logbooks.data', 15));
    });
});

describe('show', function () {
    it('mengirim detail tanpa field sensitif model', function () {
        $logbook = draftOf($this->employee);

        $this->actingAs($this->employee)->get("/logbooks/{$logbook->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Logbooks/Show')
                ->where('logbook.owner.name', $this->employee->name)
                ->where('logbook.can', ['update' => true, 'delete' => true, 'submit' => true, 'review' => false])
                ->has('logbook.items', 1)
                ->missing('logbook.user')
                ->missing('logbook.user_id')
                ->missing('logbook.items.0.logbook_id'));
    });
});
