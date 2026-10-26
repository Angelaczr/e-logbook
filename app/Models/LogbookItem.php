<?php

namespace App\Models;

use App\Enums\ProgressStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogbookItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'logbook_id',
        'project_id',
        'task_description',
        'start_time',
        'end_time',
        'status_progress',
        'output_link_or_attachment',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->duration_minutes = self::minutesBetween($item->start_time, $item->end_time);
        });

        static::saved(fn (self $item) => $item->refreshLogbookTotal());
        static::deleted(fn (self $item) => $item->refreshLogbookTotal());
    }

    /**
     * Selisih menit; jika waktu selesai tidak lebih besar dari mulai, dianggap lewat tengah malam.
     */
    public static function minutesBetween(string $start, string $end): int
    {
        $startMinutes = self::toMinutes($start);
        $endMinutes = self::toMinutes($end);

        if ($endMinutes < $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return $endMinutes - $startMinutes;
    }

    private static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }

    private function refreshLogbookTotal(): void
    {
        $this->logbook()->withTrashed()->first()?->recalculateTotalMinutes();
    }

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'status_progress' => ProgressStatus::class,
        ];
    }

    public function logbook(): BelongsTo
    {
        return $this->belongsTo(Logbook::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
