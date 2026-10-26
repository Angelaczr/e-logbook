<?php

namespace App\Events;

use App\Enums\LogbookStatus;
use App\Models\Logbook;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class LogbookReviewed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  LogbookStatus  $decision  Approved, RevisionRequested, atau Rejected.
     */
    public function __construct(
        public readonly Logbook $logbook,
        public readonly User $reviewer,
        public readonly LogbookStatus $decision,
        public readonly ?string $notes,
    ) {}
}
