<?php

namespace App\Services;

use App\Enums\LogbookStatus;
use App\Events\LogbookReviewed;
use App\Events\LogbookSubmitted;
use App\Exceptions\InvalidLogbookTransitionException;
use App\Models\Logbook;
use App\Models\LogbookItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LogbookService
{
    private const MINUTES_PER_DAY = 24 * 60;

    /**
     * Membuat atau memperbarui draft beserta item-itemnya.
     * Item dengan `id` diperbarui, tanpa `id` dibuat, dan item yang tidak dikirim dihapus.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(User $user, array $data, ?Logbook $logbook = null): Logbook
    {
        if ($logbook !== null && $logbook->user_id !== $user->id) {
            throw new AuthorizationException('Logbook hanya dapat diubah oleh pemiliknya.');
        }

        $workDate = (string) $data['work_date'];
        $shift = (string) (($data['shift'] ?? null) ?: 'regular');
        /** @var list<array<string, mixed>> $items */
        $items = array_values($data['items'] ?? []);

        $this->assertWorkDateNotInFuture($workDate);
        $this->assertItemsValid($items);

        return DB::transaction(function () use ($user, $logbook, $workDate, $shift, $items): Logbook {
            if ($logbook === null) {
                $this->assertSlotAvailable($user, $workDate, $shift, null);
                $saved = $user->logbooks()->create(['work_date' => $workDate, 'shift' => $shift]);
            } else {
                $saved = $this->lock($logbook);
                if (! $saved->status->isEditable()) {
                    throw InvalidLogbookTransitionException::notEditable($saved->status);
                }
                $this->assertSlotAvailable($user, $workDate, $shift, $saved->id);
                $saved->update(['work_date' => $workDate, 'shift' => $shift]);
            }

            $this->syncItems($saved, $items);

            return $saved->refresh();
        });
    }

    public function submit(Logbook $logbook, User $user): Logbook
    {
        return $this->transition($logbook, LogbookStatus::Submitted, $user, null, function (Logbook $locked) use ($user): array {
            if ($locked->user_id !== $user->id) {
                throw new AuthorizationException('Logbook hanya dapat diajukan oleh pemiliknya.');
            }

            if (! $locked->items()->exists()) {
                throw ValidationException::withMessages(['items' => 'Logbook harus memiliki minimal satu item aktivitas.']);
            }

            return [
                'submitted_at' => now(),
                'approved_by' => null,
                'approved_at' => null,
                'rejection_reason' => null,
            ];
        }, fn (Logbook $saved) => LogbookSubmitted::dispatch($saved, $user));
    }

    public function approve(Logbook $logbook, User $reviewer, ?string $note = null): Logbook
    {
        return $this->review($logbook, $reviewer, LogbookStatus::Approved, $note, [
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function requestRevision(Logbook $logbook, User $reviewer, string $reason): Logbook
    {
        return $this->review($logbook, $reviewer, LogbookStatus::RevisionRequested, $reason, [
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => $reason,
            'revision_count' => DB::raw('revision_count + 1'),
        ]);
    }

    public function reject(Logbook $logbook, User $reviewer, string $reason): Logbook
    {
        return $this->review($logbook, $reviewer, LogbookStatus::Rejected, $reason, [
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function review(Logbook $logbook, User $reviewer, LogbookStatus $to, ?string $notes, array $attributes): Logbook
    {
        return $this->transition($logbook, $to, $reviewer, $notes, function (Logbook $locked) use ($reviewer, $attributes): array {
            Gate::forUser($reviewer)->authorize('review', $locked);

            return $attributes;
        }, fn (Logbook $saved) => LogbookReviewed::dispatch($saved, $reviewer, $to, $notes));
    }

    /**
     * Satu-satunya jalur perubahan status: kunci baris, validasi transisi, simpan, catat riwayat.
     *
     * @param  Closure(Logbook): array<string, mixed>  $prepare  validasi tambahan; mengembalikan atribut yang ikut diubah
     * @param  Closure(Logbook): mixed  $afterCommit  dipanggil setelah transaksi (event bersifat after-commit)
     */
    private function transition(Logbook $logbook, LogbookStatus $to, User $actor, ?string $notes, Closure $prepare, Closure $afterCommit): Logbook
    {
        return DB::transaction(function () use ($logbook, $to, $actor, $notes, $prepare, $afterCommit): Logbook {
            $locked = $this->lock($logbook);
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw InvalidLogbookTransitionException::between($from, $to);
            }

            $attributes = $prepare($locked);

            $locked->forceFill([...$attributes, 'status' => $to])->save();

            $locked->histories()->create([
                'actor_id' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'notes' => $notes,
                'ip_address' => request()->ip(),
            ]);

            $saved = $locked->refresh();
            DB::afterCommit(AnalyticsService::flushCache(...));
            $afterCommit($saved);

            return $saved;
        });
    }

    private function lock(Logbook $logbook): Logbook
    {
        return Logbook::query()->lockForUpdate()->findOrFail($logbook->id);
    }

    private function assertWorkDateNotInFuture(string $workDate): void
    {
        if (CarbonImmutable::parse($workDate)->startOfDay()->greaterThan(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['work_date' => 'Tanggal kerja tidak boleh di masa depan.']);
        }
    }

    private function assertSlotAvailable(User $user, string $workDate, string $shift, ?int $ignoreId): void
    {
        $exists = Logbook::withTrashed()
            ->where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->where('shift', $shift)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['work_date' => 'Logbook untuk tanggal dan shift ini sudah ada.']);
        }
    }

    /**
     * Jam item tidak boleh tumpang tindih (termasuk melewati tengah malam) dan total maksimal 24 jam.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function assertItemsValid(array $items): void
    {
        $ranges = [];
        $total = 0;

        foreach ($items as $index => $item) {
            $duration = LogbookItem::minutesBetween((string) $item['start_time'], (string) $item['end_time']);
            if ($duration === 0) {
                throw ValidationException::withMessages(["items.{$index}.end_time" => 'Jam selesai harus berbeda dari jam mulai.']);
            }

            $start = LogbookItem::minutesBetween('00:00', (string) $item['start_time']);
            $ranges[$index] = [$start, $start + $duration];
            $total += $duration;
        }

        foreach ($ranges as $i => $a) {
            foreach ($ranges as $j => $b) {
                if ($j > $i && $this->overlaps($a, $b)) {
                    throw ValidationException::withMessages(["items.{$j}.start_time" => 'Jam kerja tumpang tindih dengan item lain.']);
                }
            }
        }

        if ($total > self::MINUTES_PER_DAY) {
            throw ValidationException::withMessages(['items' => 'Total durasi dalam satu hari maksimal 24 jam.']);
        }
    }

    /**
     * @param  array{int, int}  $a
     * @param  array{int, int}  $b
     */
    private function overlaps(array $a, array $b): bool
    {
        foreach ([-self::MINUTES_PER_DAY, 0, self::MINUTES_PER_DAY] as $shift) {
            if ($a[0] < $b[1] + $shift && $a[1] > $b[0] + $shift) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function syncItems(Logbook $logbook, array $items): void
    {
        $existing = $logbook->items()->get()->keyBy('id');
        $keptIds = [];

        foreach ($items as $index => $item) {
            $id = $item['id'] ?? null;
            $attributes = [
                'project_id' => $item['project_id'] ?? null,
                'task_description' => $item['task_description'],
                'start_time' => $item['start_time'],
                'end_time' => $item['end_time'],
                'status_progress' => $item['status_progress'],
                'output_link_or_attachment' => $item['output_link_or_attachment'] ?? null,
                'sort_order' => $index,
            ];

            if ($id === null) {
                $logbook->items()->create($attributes);

                continue;
            }

            $model = $existing->get($id)
                ?? throw ValidationException::withMessages(["items.{$index}.id" => 'Item tidak ditemukan pada logbook ini.']);
            $model->update($attributes);
            $keptIds[] = $model->id;
        }

        $existing->except($keptIds)->each(fn (LogbookItem $item) => $item->delete());
    }
}
