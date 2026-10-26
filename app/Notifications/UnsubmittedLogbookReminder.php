<?php

namespace App\Notifications;

class UnsubmittedLogbookReminder extends LogbookNotification
{
    protected function title(): string
    {
        return 'Pengingat: logbook hari ini belum dikirim';
    }

    protected function message(): string
    {
        return 'Anda belum mengirim logbook untuk hari ini. Lengkapi dan kirim sebelum hari berakhir.';
    }

    protected function path(): string
    {
        return '/logbooks/create';
    }
}
