<?php

namespace App\Console\Commands;

use App\Enums\LogbookStatus;
use App\Models\Logbook;
use App\Models\User;
use App\Notifications\PendingReviewReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('logbook:remind-pending-reviews {--days=2 : Batas hari menunggu review}')]
#[Description('Ingatkan atasan yang memiliki logbook menunggu review lebih dari batas hari')]
class RemindPendingReviews extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $counts = Logbook::query()
            ->where('status', LogbookStatus::Submitted->value)
            ->where('submitted_at', '<', now()->subDays($days))
            ->join('users', 'users.id', '=', 'logbooks.user_id')
            ->whereNotNull('users.supervisor_id')
            ->selectRaw('users.supervisor_id as reviewer_id, count(*) as total')
            ->groupBy('users.supervisor_id')
            ->pluck('total', 'reviewer_id');

        $reviewers = User::query()->where('is_active', true)->whereKey($counts->keys())->get();

        foreach ($reviewers as $reviewer) {
            $reviewer->notify(new PendingReviewReminder((int) $counts[$reviewer->id], $days));
        }

        $this->info("Pengingat dikirim ke {$reviewers->count()} atasan.");

        return self::SUCCESS;
    }
}
