<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\Logbook;
use App\Models\LogbookItem;
use App\Models\User;

/**
 * Hierarki: manager -> supervisor -> employee; supervisor lain & karyawannya terpisah.
 *
 * @return array<string, User>
 */
function org(): array
{
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);
    $manager = User::factory()->create(['role' => UserRole::Manager]);
    $supervisor = User::factory()->create(['role' => UserRole::Supervisor, 'supervisor_id' => $manager->id]);
    $employee = User::factory()->create(['supervisor_id' => $supervisor->id]);
    $otherManager = User::factory()->create(['role' => UserRole::Manager]);
    $otherSupervisor = User::factory()->create(['role' => UserRole::Supervisor, 'supervisor_id' => $otherManager->id]);
    $otherEmployee = User::factory()->create(['supervisor_id' => $otherSupervisor->id]);

    return compact('admin', 'manager', 'supervisor', 'employee', 'otherManager', 'otherSupervisor', 'otherEmployee');
}

function logbookOf(User $owner, LogbookStatus $status = LogbookStatus::Submitted, bool $withItem = true): Logbook
{
    $logbook = Logbook::factory()->create(['user_id' => $owner->id, 'status' => $status]);
    if ($withItem) {
        LogbookItem::factory()->create(['logbook_id' => $logbook->id]);
    }

    return $logbook;
}

describe('view', function () {
    it('mengizinkan pemilik, atasan dalam hierarki, dan super admin', function () {
        $o = org();
        $logbook = logbookOf($o['employee']);

        expect($o['employee']->can('view', $logbook))->toBeTrue()
            ->and($o['supervisor']->can('view', $logbook))->toBeTrue()
            ->and($o['manager']->can('view', $logbook))->toBeTrue()
            ->and($o['admin']->can('view', $logbook))->toBeTrue();
    });

    it('menolak karyawan lain dan atasan dari hierarki lain', function () {
        $o = org();
        $logbook = logbookOf($o['employee']);

        expect($o['otherEmployee']->can('view', $logbook))->toBeFalse()
            ->and($o['otherSupervisor']->can('view', $logbook))->toBeFalse()
            ->and($o['otherManager']->can('view', $logbook))->toBeFalse();
    });

    it('menolak pengguna nonaktif', function () {
        $o = org();
        $o['employee']->update(['is_active' => false]);

        expect($o['employee']->can('view', logbookOf($o['employee'])))->toBeFalse();
    });
});

describe('update dan delete', function () {
    it('hanya untuk pemilik pada status yang dapat diedit', function (LogbookStatus $status, bool $allowed) {
        $o = org();
        $logbook = logbookOf($o['employee'], $status);

        expect($o['employee']->can('update', $logbook))->toBe($allowed)
            ->and($o['employee']->can('delete', $logbook))->toBe($allowed);
    })->with([
        [LogbookStatus::Draft, true],
        [LogbookStatus::RevisionRequested, true],
        [LogbookStatus::Submitted, false],
        [LogbookStatus::Approved, false],
        [LogbookStatus::Rejected, false],
    ]);

    it('menolak selain pemilik, termasuk atasan dan super admin', function () {
        $o = org();
        $logbook = logbookOf($o['employee'], LogbookStatus::Draft);

        foreach (['supervisor', 'manager', 'admin', 'otherEmployee'] as $key) {
            expect($o[$key]->can('update', $logbook))->toBeFalse()
                ->and($o[$key]->can('delete', $logbook))->toBeFalse();
        }
    });
});

describe('submit', function () {
    it('hanya untuk pemilik pada draft atau revision_requested', function (LogbookStatus $status, bool $allowed) {
        $o = org();

        expect($o['employee']->can('submit', logbookOf($o['employee'], $status)))->toBe($allowed);
    })->with([
        [LogbookStatus::Draft, true],
        [LogbookStatus::RevisionRequested, true],
        [LogbookStatus::Submitted, false],
        [LogbookStatus::Approved, false],
        [LogbookStatus::Rejected, false],
    ]);

    it('menolak logbook tanpa item', function () {
        $o = org();

        expect($o['employee']->can('submit', logbookOf($o['employee'], LogbookStatus::Draft, withItem: false)))->toBeFalse();
    });

    it('menolak bukan pemilik', function () {
        $o = org();

        expect($o['supervisor']->can('submit', logbookOf($o['employee'], LogbookStatus::Draft)))->toBeFalse();
    });
});

describe('review', function () {
    it('mengizinkan supervisor langsung, manager di atas hierarki, dan super admin', function () {
        $o = org();
        $logbook = logbookOf($o['employee']);

        expect($o['supervisor']->can('review', $logbook))->toBeTrue()
            ->and($o['manager']->can('review', $logbook))->toBeTrue()
            ->and($o['admin']->can('review', $logbook))->toBeTrue();
    });

    it('menolak supervisor yang mereview bawahan supervisor lain', function () {
        $o = org();
        $logbook = logbookOf($o['otherEmployee']);

        expect($o['supervisor']->can('review', $logbook))->toBeFalse()
            ->and($o['otherSupervisor']->can('review', $logbook))->toBeTrue();
    });

    it('menolak manager di luar hierarkinya', function () {
        $o = org();

        expect($o['otherManager']->can('review', logbookOf($o['employee'])))->toBeFalse();
    });

    it('menolak supervisor yang mereview bawahan tidak langsung', function () {
        $o = org();
        $deep = User::factory()->create(['supervisor_id' => $o['employee']->id]);

        expect($o['supervisor']->can('review', logbookOf($deep)))->toBeFalse()
            ->and($o['manager']->can('review', logbookOf($deep)))->toBeTrue();
    });

    it('menolak karyawan biasa', function () {
        $o = org();

        expect($o['otherEmployee']->can('review', logbookOf($o['employee'])))->toBeFalse();
    });

    it('menolak review logbook sendiri, juga oleh super admin dan manager', function () {
        $o = org();

        foreach (['supervisor', 'manager', 'admin'] as $key) {
            expect($o[$key]->can('review', logbookOf($o[$key])))->toBeFalse();
        }
    });

    it('hanya untuk status submitted', function (LogbookStatus $status, bool $allowed) {
        $o = org();

        expect($o['supervisor']->can('review', logbookOf($o['employee'], $status)))->toBe($allowed);
    })->with([
        [LogbookStatus::Draft, false],
        [LogbookStatus::Submitted, true],
        [LogbookStatus::RevisionRequested, false],
        [LogbookStatus::Approved, false],
        [LogbookStatus::Rejected, false],
    ]);
});

it('scope pendingReviewFor tidak memuat logbook milik peninjau sendiri', function () {
    $o = org();
    logbookOf($o['employee']);
    $own = logbookOf($o['admin']);

    $ids = Logbook::pendingReviewFor($o['admin'])->pluck('id');

    expect($ids)->not->toContain($own->id)->and($ids)->toHaveCount(1);
});
