<?php

namespace Database\Factories;

use App\Enums\LogbookStatus;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Logbook>
 */
class LogbookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'work_date' => fake()->dateTimeBetween('-30 days', '-1 day')->format('Y-m-d'),
            'shift' => 'regular',
            'status' => LogbookStatus::Draft,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => LogbookStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn () => [
            'status' => LogbookStatus::Approved,
            'submitted_at' => now()->subHours(4),
            'approved_by' => $approver?->id ?? User::factory(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Uraian pekerjaan tidak sesuai.'): static
    {
        return $this->state(fn () => [
            'status' => LogbookStatus::Rejected,
            'submitted_at' => now()->subHours(4),
            'rejection_reason' => $reason,
        ]);
    }

    public function revisionRequested(string $reason = 'Mohon lengkapi uraian pekerjaan.'): static
    {
        return $this->state(fn () => [
            'status' => LogbookStatus::RevisionRequested,
            'submitted_at' => now()->subHours(4),
            'rejection_reason' => $reason,
        ]);
    }
}
