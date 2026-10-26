<?php

use App\Enums\LogbookStatus;
use App\Models\Logbook;
use App\Models\LogbookStatusHistory;
use App\Models\User;

it('tidak boleh diubah setelah dibuat', function () {
    $user = User::factory()->create();
    $logbook = Logbook::factory()->create(['user_id' => $user->id, 'work_date' => '2026-10-24']);
    $history = $logbook->histories()->create([
        'actor_id' => $user->id,
        'from_status' => LogbookStatus::Draft,
        'to_status' => LogbookStatus::Submitted,
    ]);

    expect(fn () => $history->update(['notes' => 'ubah']))->toThrow(LogicException::class)
        ->and(fn () => $history->delete())->toThrow(LogicException::class)
        ->and(LogbookStatusHistory::count())->toBe(1);
});
