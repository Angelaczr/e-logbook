<?php

namespace Database\Factories;

use App\Enums\ProgressStatus;
use App\Models\Logbook;
use App\Models\LogbookItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogbookItem>
 */
class LogbookItemFactory extends Factory
{
    /**
     * Durasi tidak diisi: dihitung server dari start_time dan end_time.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'logbook_id' => Logbook::factory(),
            'project_id' => null,
            'task_description' => fake()->sentence(8),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'status_progress' => ProgressStatus::Completed,
            'output_link_or_attachment' => null,
            'sort_order' => 0,
        ];
    }

    public function between(string $start, string $end): static
    {
        return $this->state(fn () => ['start_time' => $start, 'end_time' => $end]);
    }
}
