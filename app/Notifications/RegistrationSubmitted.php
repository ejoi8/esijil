<?php

namespace App\Notifications;

use App\Models\Registration;
use App\Notifications\Concerns\StampsEmailLogTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;
    use StampsEmailLogTenant;

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
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $this->registration->loadMissing('event', 'participant');

        $event = $this->registration->event;
        $participant = $this->registration->participant;

        return $this->stampEmailLogTenant(
            (new MailMessage)
                ->subject("Pengesahan pendaftaran: {$event->title}")
                ->view('notifications.registration-submitted', [
                    'event' => $event,
                    'participant' => $participant,
                    'registration' => $this->registration,
                ]),
            $this->registration->organization_id,
        );
    }

    /**
     * Get the array representation of the notification.
     *
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
