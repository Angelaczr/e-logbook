<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dasar notifikasi logbook: tersimpan ke database dan dikirim lewat email, keduanya via antrean.
 */
abstract class LogbookNotification extends Notification implements ShouldQueue
{
    use Queueable;

    abstract protected function title(): string;

    abstract protected function message(): string;

    /** Path internal aplikasi (diawali "/") yang dibuka saat notifikasi diklik. */
    abstract protected function path(): string;

    protected function logbookId(): ?int
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting('Halo '.$notifiable->name.',')
            ->line($this->message())
            ->action('Buka E-Logbook', url($this->path()));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->path(),
            'logbook_id' => $this->logbookId(),
        ];
    }
}
