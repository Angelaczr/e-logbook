<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

it('middleware role menerima beberapa role', function () {
    Route::middleware(['web', 'auth', 'role:manager,super_admin'])->get('/_uji-role', fn () => 'ok');

    $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))->get('/_uji-role')->assertOk();
    $this->actingAs(User::factory()->create(['role' => UserRole::SuperAdmin]))->get('/_uji-role')->assertOk();
    $this->actingAs(User::factory()->create(['role' => UserRole::Supervisor]))->get('/_uji-role')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/_uji-role')->assertForbidden();
});

it('middleware role menolak pengguna nonaktif dan tamu', function () {
    Route::middleware(['web', 'role:manager'])->get('/_uji-role-2', fn () => 'ok');

    $this->get('/_uji-role-2')->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'is_active' => false]))
        ->get('/_uji-role-2')->assertForbidden();
});

it('route reviews hanya untuk peninjau', function () {
    $this->actingAs(User::factory()->create())->get('/reviews')->assertForbidden();
});

it('gate analytics dan export mengikuti canViewAnalytics', function (UserRole $role, bool $allowed) {
    $user = User::factory()->create(['role' => $role]);

    expect(Gate::forUser($user)->allows('view-analytics'))->toBe($allowed)
        ->and(Gate::forUser($user)->allows('export-reports'))->toBe($allowed);
})->with([
    [UserRole::SuperAdmin, true],
    [UserRole::Manager, true],
    [UserRole::Supervisor, false],
    [UserRole::Employee, false],
]);

function shared(?User $user): array
{
    $request = Request::create('/');
    $request->setUserResolver(fn () => $user);
    $request->setLaravelSession(app('session.store'));

    return (new HandleInertiaRequests)->share($request);
}

it('membagikan data auth, hak akses, dan jumlah pending review ke Inertia', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager, 'name' => 'Bu Manajer']);
    $supervisor = User::factory()->create(['role' => UserRole::Supervisor, 'supervisor_id' => $manager->id]);
    $employee = User::factory()->create(['supervisor_id' => $supervisor->id]);
    Logbook::factory()->create(['user_id' => $employee->id, 'status' => LogbookStatus::Submitted]);
    Logbook::factory()->create(['user_id' => $employee->id, 'status' => LogbookStatus::Draft, 'work_date' => '2026-01-05']);

    $props = shared($manager);

    expect($props['auth']['user'])->toBe([
        'id' => $manager->id,
        'name' => 'Bu Manajer',
        'role' => 'manager',
        'role_label' => 'Manager',
    ])
        ->and($props['auth']['can'])->toBe(['review' => true, 'viewAnalytics' => true, 'export' => true])
        ->and(value($props['pendingReviewCount']))->toBe(1)
        ->and(array_keys($props['flash']))->toBe(['success', 'error']);
});

it('membagikan hak akses minimal untuk karyawan dan tamu', function () {
    $employee = User::factory()->create();

    $props = shared($employee);
    expect($props['auth']['can'])->toBe(['review' => false, 'viewAnalytics' => false, 'export' => false])
        ->and(value($props['pendingReviewCount']))->toBe(0);

    $guest = shared(null);
    expect($guest['auth']['user'])->toBeNull()
        ->and($guest['auth']['can'])->toBe(['review' => false, 'viewAnalytics' => false, 'export' => false]);
});
