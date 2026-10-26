<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'employee_id_number',
        'job_title',
        'department_id',
        'supervisor_id',
        'role',
        'is_active',
        'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** @var list<int>|null */
    private ?array $subordinateIdsCache = null;

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if ($user->supervisor_id === null || ! $user->isDirty('supervisor_id')) {
                return;
            }

            if ($user->exists && (int) $user->supervisor_id === $user->id) {
                throw new DomainException('Pengguna tidak boleh menjadi atasan dirinya sendiri.');
            }

            if ($user->exists && in_array((int) $user->supervisor_id, self::fetchSubordinateIds($user->id), true)) {
                throw new DomainException('Atasan tidak boleh berasal dari bawahan sendiri (relasi melingkar).');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Semua bawahan (langsung dan tidak langsung), di-cache per instance.
     *
     * @return list<int>
     */
    public function subordinateIds(): array
    {
        if (! $this->exists) {
            return [];
        }

        return $this->subordinateIdsCache ??= self::fetchSubordinateIds($this->id);
    }

    public function isDirectSupervisorOf(self $user): bool
    {
        return $this->exists && $user->supervisor_id !== null && (int) $user->supervisor_id === $this->id;
    }

    public function isAboveInHierarchy(self $user): bool
    {
        return $user->exists && in_array($user->id, $this->subordinateIds(), true);
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * UNION (bukan UNION ALL) membuat rekursi berhenti walau data memuat siklus.
     *
     * @return list<int>
     */
    private static function fetchSubordinateIds(int $userId): array
    {
        $rows = DB::select(
            'WITH RECURSIVE subordinates(id) AS (
                SELECT id FROM users WHERE supervisor_id = ?
                UNION
                SELECT u.id FROM users u INNER JOIN subordinates s ON u.supervisor_id = s.id
            )
            SELECT id FROM subordinates WHERE id <> ? ORDER BY id',
            [$userId, $userId],
        );

        return array_map(fn (object $row): int => (int) $row->id, $rows);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_id');
    }

    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class);
    }

    public function approvedLogbooks(): HasMany
    {
        return $this->hasMany(Logbook::class, 'approved_by');
    }

    public function logbookStatusHistories(): HasMany
    {
        return $this->hasMany(LogbookStatusHistory::class, 'actor_id');
    }
}
