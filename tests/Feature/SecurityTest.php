<?php

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;

it('melarang lazy loading di luar produksi agar N+1 ketahuan', function () {
    expect(Model::preventsLazyLoading())->toBeTrue();
});

it('mengabaikan kolom sensitif logbook pada mass assignment', function () {
    $owner = User::factory()->create();
    $reviewer = User::factory()->create();

    $logbook = new Logbook;
    $logbook->fill([
        'work_date' => '2026-10-01',
        'shift' => 'regular',
        'user_id' => $reviewer->id,
        'status' => LogbookStatus::Approved,
        'approved_by' => $reviewer->id,
        'total_minutes' => 9999,
        'revision_count' => 5,
    ]);

    expect(array_keys($logbook->getAttributes()))->toBe(['work_date', 'shift']);
});

it('membatasi aksi review dengan rate limit', function () {
    $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

    foreach (range(1, 10) as $_) {
        $this->actingAs($supervisor)->post('/reviews/bulk-approve', ['ids' => [1]])->assertRedirect();
    }

    $this->actingAs($supervisor)->post('/reviews/bulk-approve', ['ids' => [1]])->assertStatus(429);
});

it('tidak membocorkan atau mengubah logbook orang lain lewat ID di URL', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $logbook = Logbook::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($stranger);
    $this->get("/logbooks/{$logbook->id}")->assertForbidden();
    $this->get("/logbooks/{$logbook->id}/edit")->assertForbidden();
    $this->put("/logbooks/{$logbook->id}", ['work_date' => '2026-10-01', 'items' => []])->assertForbidden();
    $this->delete("/logbooks/{$logbook->id}")->assertForbidden();
    $this->post("/logbooks/{$logbook->id}/submit")->assertForbidden();
    $this->get("/reviews/{$logbook->id}")->assertForbidden();
    $this->post("/reviews/{$logbook->id}/approve")->assertForbidden();
});

it('melindungi semua route aplikasi dengan autentikasi kecuali halaman login', function () {
    $open = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'logbooks')
            || str_starts_with($route->uri(), 'reviews')
            || str_starts_with($route->uri(), 'analytics')
            || str_starts_with($route->uri(), 'notifications'))
        ->reject(fn ($route) => in_array('auth', $route->gatherMiddleware(), true));

    expect($open->map->uri()->all())->toBe([]);
});
