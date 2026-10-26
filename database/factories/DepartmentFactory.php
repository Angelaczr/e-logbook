<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'DEP-'.fake()->unique()->numerify('####'),
            'name' => 'Departemen '.fake()->unique()->word(),
            'parent_id' => null,
            'head_id' => null,
            'is_active' => true,
        ];
    }

    public function divisionOf(Department $department): static
    {
        return $this->state(fn () => [
            'code' => $department->code.'-'.fake()->unique()->numerify('##'),
            'name' => 'Divisi '.fake()->unique()->word(),
            'parent_id' => $department->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
