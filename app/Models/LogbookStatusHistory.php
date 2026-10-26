<?php

namespace App\Models;

use App\Enums\LogbookStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class LogbookStatusHistory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'logbook_id',
        'actor_id',
        'from_status',
        'to_status',
        'notes',
        'ip_address',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Riwayat status bersifat immutable dan tidak boleh diubah.');
        });

        static::deleting(function (): never {
            throw new LogicException('Riwayat status bersifat immutable dan tidak boleh dihapus.');
        });
    }

    protected function casts(): array
    {
        return [
            'from_status' => LogbookStatus::class,
            'to_status' => LogbookStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function logbook(): BelongsTo
    {
        return $this->belongsTo(Logbook::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
