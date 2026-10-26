<?php

namespace App\Events;

use App\Models\Logbook;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class LogbookSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Logbook $logbook,
        public readonly User $submitter,
    ) {}
}
