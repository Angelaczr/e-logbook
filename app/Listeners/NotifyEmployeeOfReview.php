<?php

namespace App\Listeners;

use App\Events\LogbookReviewed;
use App\Notifications\LogbookReviewedNotification;

class NotifyEmployeeOfReview
{
    public function handle(LogbookReviewed $event): void
    {
        $owner = $event->logbook->user;

        if ($owner === null || ! $owner->is_active) {
            return;
        }

        $owner->notify(new LogbookReviewedNotification($event->logbook, $event->reviewer, $event->decision, $event->notes));
    }
}
