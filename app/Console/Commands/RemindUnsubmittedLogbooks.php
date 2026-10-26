<?php

namespace App\Console\Commands;

use App\Enums\LogbookStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\UnsubmittedLogbookReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('logbook:remind-unsubmitted')]
#[Description('Ingatkan karyawan yang belum mengirim logbook hari ini')]
class RemindUnsubmittedLogbooks extends Command
{
    public function handle(): int
    {
        $today = today()->toDateString();

        $users = User::query()
            ->where('is_active', true)
            ->where('role', UserRole::Employee->value)
            ->whereDoesntHave('logbooks', fn ($logbooks) => $logbooks
                ->whereDate('work_date', $today)
                ->where('status', '<>', LogbookStatus::Draft->value))
            ->get();

        foreach ($users as $user) {
            $user->notify(new UnsubmittedLogbookReminder);
        }

        $this->info("Pengingat dikirim ke {$users->count()} karyawan.");

        return self::SUCCESS;
    }
}
