<?php

namespace Database\Factories;

use App\Enums\LogbookStatus;
use App\Models\Logbook;
use App\Models\LogbookStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogbookStatusHistory>
 */
class LogbookStatusHistoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'logbook_id' => Logbook::factory(),
            'actor_id' => User::factory(),
            'from_status' => LogbookStatus::Draft,
            'to_status' => LogbookStatus::Submitted,
            'notes' => null,
            'ip_address' => fake()->localIpv4(),
            'created_at' => now(),
        ];
    }
}
