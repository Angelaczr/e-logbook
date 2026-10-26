<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'PRJ-'.fake()->unique()->numerify('####'),
            'name' => 'Proyek '.fake()->unique()->words(2, true),
            'type' => 'project',
            'department_id' => null,
            'is_active' => true,
        ];
    }

    public function costCenter(): static
    {
        return $this->state(fn () => [
            'code' => 'CC-'.fake()->unique()->numerify('####'),
            'name' => 'Cost Center '.fake()->unique()->words(2, true),
            'type' => 'cost_center',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
