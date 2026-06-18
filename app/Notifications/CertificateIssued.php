<?php

namespace App\Notifications;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CertificateIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public Registration $registration)
    {
        $this->afterCommit();
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->registration->loadMissing('event', 'participant');

        $event = $this->registration->event;

        return (new MailMessage)
            ->subject("Sijil anda telah tersedia: {$event->title}")
            ->view('notifications.certificate-issued', [
                'event' => $event,
                'participant' => $this->registration->participant,
                'registration' => $this->registration,
                'lookupUrl' => route('certificate-lookup.index'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'registration_id' => $this->registration->id,
            'event_id' => $this->registration->event_id,
        ];
    }
}
