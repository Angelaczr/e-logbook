<?php

use App\Enums\ProgressStatus;
use App\Models\Logbook;
use App\Models\LogbookItem;
use App\Models\User;

function makeItem(Logbook $logbook, string $start, string $end, array $extra = []): LogbookItem
{
    return $logbook->items()->create([
        'task_description' => 'Pekerjaan uji',
        'start_time' => $start,
        'end_time' => $end,
        'status_progress' => ProgressStatus::Completed,
        ...$extra,
    ]);
}

function makeLogbook(): Logbook
{
    return Logbook::factory()->create(['user_id' => User::factory()->create()->id, 'work_date' => '2026-10-24']);
}

it('menghitung durasi di server pada hari yang sama', function (string $start, string $end, int $minutes) {
    expect(LogbookItem::minutesBetween($start, $end))->toBe($minutes);
})->with([
    ['08:00', '09:30', 90],
    ['08:00:00', '17:00:00', 540],
    ['00:00', '23:59', 1439],
]);

it('menangani shift malam yang melewati tengah malam', function () {
    expect(LogbookItem::minutesBetween('22:00', '06:00'))->toBe(480)
        ->and(LogbookItem::minutesBetween('23:30', '00:15'))->toBe(45);
});

it('mengabaikan durasi kiriman klien dan memperbarui total logbook', function () {
    $logbook = makeLogbook();

    $first = makeItem($logbook, '08:00', '09:00', ['duration_minutes' => 999]);
    makeItem($logbook, '22:00', '02:00');

    expect($first->duration_minutes)->toBe(60)
        ->and($logbook->fresh()->total_minutes)->toBe(300);

    $first->update(['end_time' => '10:00']);
    expect($logbook->fresh()->total_minutes)->toBe(360);

    $first->delete();
    expect($logbook->fresh()->total_minutes)->toBe(240);
});
