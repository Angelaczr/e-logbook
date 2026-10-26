<?php

namespace App\Models;

use App\Services\AnalyticsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Hari libur yang dikecualikan dari hari kerja pada perhitungan kepatuhan.
 */
class Holiday extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'name'];

    protected static function booted(): void
    {
        static::saved(fn () => AnalyticsService::flushCache());
        static::deleted(fn () => AnalyticsService::flushCache());
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }
}
