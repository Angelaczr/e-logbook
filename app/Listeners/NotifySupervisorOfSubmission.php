<?php

namespace App\Listeners;

use App\Events\LogbookSubmitted;
use App\Notifications\LogbookSubmittedNotification;

class NotifySupervisorOfSubmission
{
    public function handle(LogbookSubmitted $event): void
    {
        $supervisor = $event->submitter->supervisor;

        if ($supervisor === null || ! $supervisor->is_active) {
            return;
        }

        $supervisor->notify(new LogbookSubmittedNotification($event->logbook, $event->submitter));
    }
}
