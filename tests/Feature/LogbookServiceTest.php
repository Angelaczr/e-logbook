<?php

use App\Enums\LogbookStatus;
use App\Enums\ProgressStatus;
use App\Enums\UserRole;
use App\Events\LogbookReviewed;
use App\Events\LogbookSubmitted;
use App\Exceptions\InvalidLogbookTransitionException;
use App\Models\Logbook;
use App\Models\LogbookItem;
use App\Models\LogbookStatusHistory;
use App\Models\User;
use App\Services\LogbookService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = app(LogbookService::class);
    $this->manager = User::factory()->create(['role' => UserRole::Manager]);
    $this->supervisor = User::factory()->create(['role' => UserRole::Supervisor, 'supervisor_id' => $this->manager->id]);
    $this->employee = User::factory()->create(['supervisor_id' => $this->supervisor->id]);
    $this->otherSupervisor = User::factory()->create(['role' => UserRole::Supervisor]);
});

function payloadItem(string $start, string $end, array $extra = []): array
{
    return [
        'task_description' => 'Pekerjaan uji',
        'start_time' => $start,
        'end_time' => $end,
        'status_progress' => ProgressStatus::Completed->value,
        ...$extra,
    ];
}

function payload(array $items, array $extra = []): array
{
    return ['work_date' => today()->subDay()->toDateString(), 'items' => $items, ...$extra];
}

function statusLogbook(User $owner, LogbookStatus $status, bool $withItem = true): Logbook
{
    $logbook = Logbook::factory()->create(['user_id' => $owner->id, 'status' => $status]);
    if ($withItem) {
        LogbookItem::factory()->create(['logbook_id' => $logbook->id]);
    }

    return $logbook;
}

describe('saveDraft', function () {
    it('membuat logbook draft beserta item dan total menit', function () {
        $logbook = $this->service->saveDraft($this->employee, payload([
            payloadItem('08:00', '10:00'),
            payloadItem('10:00', '10:30'),
        ]));

        expect($logbook->status)->toBe(LogbookStatus::Draft)
            ->and($logbook->shift)->toBe('regular')
            ->and($logbook->total_minutes)->toBe(150)
            ->and($logbook->items->pluck('sort_order')->all())->toBe([0, 1]);
    });

    it('menyinkronkan item: update berdasarkan id, tambah baru, hapus yang tidak dikirim', function () {
        $logbook = $this->service->saveDraft($this->employee, payload([
            payloadItem('08:00', '09:00'),
            payloadItem('09:00', '10:00'),
        ]));
        [$keep, $drop] = $logbook->items;

        $updated = $this->service->saveDraft($this->employee, payload([
            payloadItem('13:00', '14:00'),
            payloadItem('08:00', '08:30', ['id' => $keep->id, 'task_description' => 'Diubah']),
        ]), $logbook);

        expect($updated->items->pluck('id'))->not->toContain($drop->id)
            ->and(LogbookItem::find($drop->id))->toBeNull()
            ->and($updated->items)->toHaveCount(2)
            ->and($updated->items->first()->task_description)->toBe('Pekerjaan uji')
            ->and($keep->fresh()->task_description)->toBe('Diubah')
            ->and($keep->fresh()->sort_order)->toBe(1)
            ->and($updated->total_minutes)->toBe(90);
    });

    it('menolak item yang tumpang tindih dan tidak menyimpan apa pun', function () {
        expect(fn () => $this->service->saveDraft($this->employee, payload([
            payloadItem('08:00', '10:00'),
            payloadItem('09:30', '11:00'),
        ])))->toThrow(ValidationException::class);

        expect(Logbook::count())->toBe(0)->and(LogbookItem::count())->toBe(0);
    });

    it('mengizinkan item yang bersinggungan tepat di batas', function () {
        $logbook = $this->service->saveDraft($this->employee, payload([
            payloadItem('08:00', '09:00'),
            payloadItem('09:00', '10:00'),
        ]));

        expect($logbook->items)->toHaveCount(2);
    });

    it('mendeteksi overlap pada shift malam yang melewati tengah malam', function () {
        expect(fn () => $this->service->saveDraft($this->employee, payload([
            payloadItem('22:00', '06:00'),
            payloadItem('01:00', '02:00'),
        ])))->toThrow(ValidationException::class);

        $ok = $this->service->saveDraft($this->employee, payload([
            payloadItem('22:00', '02:00'),
            payloadItem('03:00', '06:00'),
        ]));

        expect($ok->total_minutes)->toBe(420);
    });

    it('menolak item dengan jam mulai sama dengan jam selesai', function () {
        expect(fn () => $this->service->saveDraft($this->employee, payload([payloadItem('08:00', '08:00')])))
            ->toThrow(ValidationException::class);
    });

    it('menolak tanggal kerja di masa depan', function () {
        expect(fn () => $this->service->saveDraft($this->employee, payload(
            [payloadItem('08:00', '09:00')],
            ['work_date' => today()->addDay()->toDateString()],
        )))->toThrow(ValidationException::class, 'masa depan');
    });

    it('menerima tanggal hari ini', function () {
        $logbook = $this->service->saveDraft($this->employee, payload(
            [payloadItem('08:00', '09:00')],
            ['work_date' => today()->toDateString()],
        ));

        expect($logbook->exists)->toBeTrue();
    });

    it('menolak duplikat tanggal dan shift', function () {
        $this->service->saveDraft($this->employee, payload([payloadItem('08:00', '09:00')]));

        expect(fn () => $this->service->saveDraft($this->employee, payload([payloadItem('08:00', '09:00')])))
            ->toThrow(ValidationException::class);
    });

    it('menolak id item milik logbook lain', function () {
        $logbook = $this->service->saveDraft($this->employee, payload([payloadItem('08:00', '09:00')]));
        $foreign = statusLogbook($this->employee, LogbookStatus::Draft)->items()->first();

        expect(fn () => $this->service->saveDraft($this->employee, payload([
            payloadItem('08:00', '09:00', ['id' => $foreign->id]),
        ]), $logbook))->toThrow(ValidationException::class);
    });

    it('menolak pengubahan oleh non-pemilik', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::Draft);

        expect(fn () => $this->service->saveDraft($this->supervisor, payload([payloadItem('08:00', '09:00')]), $logbook))
            ->toThrow(AuthorizationException::class);
    });

    it('menolak edit logbook yang tidak bisa diedit', function (LogbookStatus $status) {
        $logbook = statusLogbook($this->employee, $status);

        expect(fn () => $this->service->saveDraft($this->employee, payload([payloadItem('08:00', '09:00')]), $logbook))
            ->toThrow(InvalidLogbookTransitionException::class);
    })->with([LogbookStatus::Submitted, LogbookStatus::Approved, LogbookStatus::Rejected]);
});

describe('submit', function () {
    it('mengajukan draft, mencatat riwayat, dan mengirim event', function () {
        Event::fake([LogbookSubmitted::class]);
        $logbook = statusLogbook($this->employee, LogbookStatus::Draft);

        $result = $this->service->submit($logbook, $this->employee);

        expect($result->status)->toBe(LogbookStatus::Submitted)->and($result->submitted_at)->not->toBeNull();

        $history = LogbookStatusHistory::sole();
        expect($history->actor_id)->toBe($this->employee->id)
            ->and($history->from_status)->toBe(LogbookStatus::Draft)
            ->and($history->to_status)->toBe(LogbookStatus::Submitted)
            ->and($history->ip_address)->not->toBeNull();

        Event::assertDispatched(LogbookSubmitted::class, fn ($e) => $e->logbook->is($logbook) && $e->submitter->is($this->employee));
    });

    it('mengajukan ulang setelah revisi dan membersihkan alasan', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::RevisionRequested);
        $logbook->forceFill(['rejection_reason' => 'Perbaiki'])->save();

        $result = $this->service->submit($logbook, $this->employee);

        expect($result->status)->toBe(LogbookStatus::Submitted)->and($result->rejection_reason)->toBeNull();
    });

    it('menolak logbook tanpa item', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::Draft, withItem: false);

        expect(fn () => $this->service->submit($logbook, $this->employee))->toThrow(ValidationException::class);
        expect($logbook->fresh()->status)->toBe(LogbookStatus::Draft);
    });

    it('menolak transisi tidak valid', function (LogbookStatus $status) {
        $logbook = statusLogbook($this->employee, $status);

        expect(fn () => $this->service->submit($logbook, $this->employee))
            ->toThrow(InvalidLogbookTransitionException::class);
        expect(LogbookStatusHistory::count())->toBe(0);
    })->with([LogbookStatus::Submitted, LogbookStatus::Approved, LogbookStatus::Rejected]);

    it('menolak pengajuan oleh non-pemilik', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::Draft);

        expect(fn () => $this->service->submit($logbook, $this->supervisor))->toThrow(AuthorizationException::class);
    });
});

describe('approve', function () {
    it('menyetujui logbook, mencatat penyetuju, catatan, riwayat, dan event', function () {
        Event::fake([LogbookReviewed::class]);
        $logbook = statusLogbook($this->employee, LogbookStatus::Submitted);

        $result = $this->service->approve($logbook, $this->supervisor, 'Bagus');

        expect($result->status)->toBe(LogbookStatus::Approved)
            ->and($result->approved_by)->toBe($this->supervisor->id)
            ->and($result->approved_at)->not->toBeNull();

        $history = LogbookStatusHistory::sole();
        expect($history->actor_id)->toBe($this->supervisor->id)
            ->and($history->from_status)->toBe(LogbookStatus::Submitted)
            ->and($history->to_status)->toBe(LogbookStatus::Approved)
            ->and($history->notes)->toBe('Bagus');

        Event::assertDispatched(LogbookReviewed::class, fn ($e) => $e->decision === LogbookStatus::Approved
            && $e->reviewer->is($this->supervisor) && $e->notes === 'Bagus');
    });

    it('menolak transisi tidak valid dari draft dan dari status final', function (LogbookStatus $status) {
        $logbook = statusLogbook($this->employee, $status);

        expect(fn () => $this->service->approve($logbook, $this->supervisor))
            ->toThrow(InvalidLogbookTransitionException::class);
    })->with([LogbookStatus::Draft, LogbookStatus::Approved, LogbookStatus::Rejected, LogbookStatus::RevisionRequested]);

    it('menolak supervisor lain yang bukan atasan langsung', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::Submitted);

        expect(fn () => $this->service->approve($logbook, $this->otherSupervisor))
            ->toThrow(AuthorizationException::class);
        expect($logbook->fresh()->status)->toBe(LogbookStatus::Submitted);
    });

    it('menolak review logbook sendiri', function () {
        $logbook = statusLogbook($this->supervisor, LogbookStatus::Submitted);

        expect(fn () => $this->service->approve($logbook, $this->supervisor))->toThrow(AuthorizationException::class);
    });

    it('membaca status terbaru di bawah kunci sehingga atasan kedua ditolak', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::Submitted);
        $stale = Logbook::find($logbook->id);

        $this->service->approve($logbook, $this->supervisor);

        expect($stale->status)->toBe(LogbookStatus::Submitted)
            ->and(fn () => $this->service->reject($stale, $this->manager, 'Terlambat'))
            ->toThrow(InvalidLogbookTransitionException::class);
        expect($logbook->fresh()->status)->toBe(LogbookStatus::Approved)
            ->and(LogbookStatusHistory::count())->toBe(1);
    });
});

describe('requestRevision', function () {
    it('meminta revisi, menaikkan revision_count, dan mencatat alasan', function () {
        Event::fake([LogbookReviewed::class]);
        $logbook = statusLogbook($this->employee, LogbookStatus::Submitted);

        $result = $this->service->requestRevision($logbook, $this->supervisor, 'Lengkapi uraian');

        expect($result->status)->toBe(LogbookStatus::RevisionRequested)
            ->and($result->revision_count)->toBe(1)
            ->and($result->rejection_reason)->toBe('Lengkapi uraian')
            ->and(LogbookStatusHistory::sole()->notes)->toBe('Lengkapi uraian');

        Event::assertDispatched(LogbookReviewed::class, fn ($e) => $e->decision === LogbookStatus::RevisionRequested);
    });

    it('menaikkan revision_count pada setiap putaran revisi', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::Submitted);

        $this->service->requestRevision($logbook, $this->supervisor, 'Satu');
        $this->service->submit($logbook, $this->employee);
        $result = $this->service->requestRevision($logbook, $this->manager, 'Dua');

        expect($result->revision_count)->toBe(2)->and(LogbookStatusHistory::count())->toBe(3);
    });

    it('menolak dari status selain submitted', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::Draft);

        expect(fn () => $this->service->requestRevision($logbook, $this->supervisor, 'x'))
            ->toThrow(InvalidLogbookTransitionException::class);
    });

    it('menolak supervisor yang bukan atasan', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::Submitted);

        expect(fn () => $this->service->requestRevision($logbook, $this->otherSupervisor, 'x'))
            ->toThrow(AuthorizationException::class);
    });
});

describe('reject', function () {
    it('menolak logbook dan statusnya final', function () {
        Event::fake([LogbookReviewed::class]);
        $logbook = statusLogbook($this->employee, LogbookStatus::Submitted);

        $result = $this->service->reject($logbook, $this->manager, 'Tidak sesuai');

        expect($result->status)->toBe(LogbookStatus::Rejected)
            ->and($result->rejection_reason)->toBe('Tidak sesuai')
            ->and($result->approved_by)->toBeNull()
            ->and(LogbookStatusHistory::sole()->to_status)->toBe(LogbookStatus::Rejected);

        Event::assertDispatched(LogbookReviewed::class, fn ($e) => $e->decision === LogbookStatus::Rejected);

        expect(fn () => $this->service->submit($logbook, $this->employee))
            ->toThrow(InvalidLogbookTransitionException::class)
            ->and(fn () => $this->service->approve($logbook, $this->manager))
            ->toThrow(InvalidLogbookTransitionException::class);
    });

    it('menolak supervisor yang bukan atasan langsung', function () {
        $logbook = statusLogbook($this->employee, LogbookStatus::Submitted);

        expect(fn () => $this->service->reject($logbook, $this->otherSupervisor, 'x'))
            ->toThrow(AuthorizationException::class);
    });
});
