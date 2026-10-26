<?php

namespace App\Models;

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Logbook extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Hanya data yang boleh diisi pengguna. Status, penyetuju, total, dan hitungan revisi
     * diubah lewat LogbookService (forceFill), tidak pernah dari input request.
     */
    protected $fillable = ['work_date', 'shift'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'status' => LogbookStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LogbookItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(LogbookStatusHistory::class);
    }

    public function scopeForUser(Builder $query, User|int $user): void
    {
        $query->where('user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /**
     * @param  LogbookStatus|list<LogbookStatus>  $status
     */
    public function scopeStatus(Builder $query, LogbookStatus|array $status): void
    {
        $statuses = array_map(
            fn (LogbookStatus $item): string => $item->value,
            is_array($status) ? $status : [$status],
        );

        $query->whereIn('status', $statuses);
    }

    public function scopeDateBetween(Builder $query, ?string $from, ?string $to): void
    {
        $query
            ->when($from !== null, fn (Builder $q) => $q->whereDate('work_date', '>=', $from))
            ->when($to !== null, fn (Builder $q) => $q->whereDate('work_date', '<=', $to));
    }

    /**
     * Logbook milik bawahan yang berhak ditinjau pengguna ini (tanpa milik sendiri), apa pun statusnya.
     */
    public function scopeReviewableBy(Builder $query, User $reviewer): void
    {
        $query->where('user_id', '<>', $reviewer->getKey());

        match ($reviewer->role) {
            UserRole::SuperAdmin => null,
            UserRole::Manager => $query->whereIn('user_id', $reviewer->subordinateIds()),
            UserRole::Supervisor => $query->whereIn(
                'user_id',
                User::query()->where('supervisor_id', $reviewer->getKey())->select('id'),
            ),
            UserRole::Employee => $query->whereRaw('1 = 0'),
        };
    }

    public function scopePendingReviewFor(Builder $query, User $reviewer): void
    {
        $query->reviewableBy($reviewer)->where('status', LogbookStatus::Submitted->value);
    }

    public function recalculateTotalMinutes(): int
    {
        $total = (int) $this->items()->sum('duration_minutes');

        $this->total_minutes = $total;
        $this->save();

        return $total;
    }
}
