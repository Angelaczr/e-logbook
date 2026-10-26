<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogbookView extends Model
{
    public $timestamps = false;

    /** Pembukaan berulang dalam jendela ini tidak ditulis ulang. */
    private const THROTTLE_SECONDS = 60;

    protected $fillable = ['logbook_id', 'user_id', 'last_viewed_at'];

    protected function casts(): array
    {
        return [
            'last_viewed_at' => 'datetime',
        ];
    }

    /**
     * Mencatat bahwa peninjau membuka detail logbook; dibatasi satu tulis per menit per pasangan.
     */
    public static function record(Logbook $logbook, User $viewer): void
    {
        $last = self::query()
            ->where('logbook_id', $logbook->id)
            ->where('user_id', $viewer->id)
            ->value('last_viewed_at');

        if ($last !== null && now()->diffInSeconds($last, absolute: true) < self::THROTTLE_SECONDS) {
            return;
        }

        self::query()->upsert(
            [['logbook_id' => $logbook->id, 'user_id' => $viewer->id, 'last_viewed_at' => now()]],
            ['logbook_id', 'user_id'],
            ['last_viewed_at'],
        );
    }
}
