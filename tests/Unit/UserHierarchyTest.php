<?php

use App\Enums\UserRole;
use App\Models\User;

function chain(): array
{
    $top = User::factory()->create(['role' => UserRole::Manager]);
    $mid = User::factory()->create(['role' => UserRole::Supervisor, 'supervisor_id' => $top->id]);
    $low = User::factory()->create(['supervisor_id' => $mid->id]);
    $other = User::factory()->create(['supervisor_id' => $top->id]);
    $outsider = User::factory()->create();

    return compact('top', 'mid', 'low', 'other', 'outsider');
}

it('mengambil bawahan langsung dan tidak langsung', function () {
    ['top' => $top, 'mid' => $mid, 'low' => $low, 'other' => $other, 'outsider' => $outsider] = chain();

    expect($top->subordinateIds())->toBe([$mid->id, $low->id, $other->id])
        ->and($mid->subordinateIds())->toBe([$low->id])
        ->and($low->subordinateIds())->toBe([])
        ->and($top->subordinateIds())->not->toContain($outsider->id);
});

it('menyimpan hasil subordinateIds per instance', function () {
    ['top' => $top, 'mid' => $mid] = chain();
    $top->subordinateIds();

    $newcomer = User::factory()->create(['supervisor_id' => $mid->id]);

    expect($top->subordinateIds())->not->toContain($newcomer->id)
        ->and($top->fresh()->subordinateIds())->toContain($newcomer->id);
});

it('mengenali atasan langsung dan posisi di atas dalam hierarki', function () {
    ['top' => $top, 'mid' => $mid, 'low' => $low, 'outsider' => $outsider] = chain();

    expect($mid->isDirectSupervisorOf($low))->toBeTrue()
        ->and($top->isDirectSupervisorOf($low))->toBeFalse()
        ->and($top->isAboveInHierarchy($low))->toBeTrue()
        ->and($low->isAboveInHierarchy($top))->toBeFalse()
        ->and($top->isAboveInHierarchy($outsider))->toBeFalse()
        ->and($top->hasRole(UserRole::Manager, UserRole::SuperAdmin))->toBeTrue()
        ->and($low->hasRole(UserRole::Manager))->toBeFalse();
});

it('menolak atasan berupa diri sendiri', function () {
    $user = User::factory()->create();

    $user->supervisor_id = $user->id;
    $user->save();
})->throws(DomainException::class);

it('menolak relasi melingkar', function () {
    ['top' => $top, 'low' => $low] = chain();

    $top->supervisor_id = $low->id;
    $top->save();
})->throws(DomainException::class);

it('tetap berhenti jika data sudah memuat siklus', function () {
    $a = User::factory()->create();
    $b = User::factory()->create(['supervisor_id' => $a->id]);
    User::query()->whereKey($a->id)->update(['supervisor_id' => $b->id]);

    expect($a->fresh()->subordinateIds())->toBe([$b->id]);
});
