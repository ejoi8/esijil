<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\EventStatus;
use App\Enums\RegistrationSource;
use App\Http\Requests\StoreEventRegistrationRequest;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Notifications\RegistrationSubmitted;
use App\Services\Certificates\RegistrationCertificateIssuer;
use App\Services\Certificates\StoredCertificatePdf;
use App\Settings\NotificationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventRegistrationController extends Controller
{
    public function show(Event $event): View
    {
        $this->abortUnlessPublished($event);

        return view('event-registrations.show', [
            'event' => $event,
            'registrationIsOpen' => $this->registrationIsOpen($event),
        ]);
    }

    public function store(
        StoreEventRegistrationRequest $request,
        Event $event,
        RegistrationCertificateIssuer $certificateIssuer,
        NotificationSettings $notificationSettings,
    ): RedirectResponse {
        $this->abortUnlessPublished($event);

        if (! $this->registrationIsOpen($event)) {
            return back()
                ->withInput()
                ->withErrors([
                    'event' => 'Registration is not open for this event.',
                ]);
        }

        [$participant, $registration] = DB::transaction(function () use ($request, $event, $certificateIssuer): array {
            $participant = $this->resolveParticipant($request);
            $registration = $this->resolveRegistration($event, $participant);

            $certificateIssuer->issueFor($registration);

            return [$participant, $registration];
        });

        if ($registration->wasRecentlyCreated && $notificationSettings->registration_submitted_enabled) {
            $participant->notify(new RegistrationSubmitted($registration));
        }

        $request->session()->put('event_registration_success_id', $registration->id);

        return redirect()
            ->route('events.register.success', $registration)
            ->with($registration->wasRecentlyCreated ? 'registration_created' : 'registration_exists', true);
    }

    public function success(Registration $registration): View
    {
        $this->abortUnlessAuthorizedRegistrationSession($registration);

        $registration->loadMissing('event', 'participant');

        return view('event-registrations.success', [
            'registration' => $registration,
        ]);
    }

    public function downloadCertificate(Registration $registration, StoredCertificatePdf $storedCertificatePdf): StreamedResponse
    {
        $this->abortUnlessAuthorizedRegistrationSession($registration);

        abort_unless($registration->certificate_type !== null, 404);

        return $storedCertificatePdf->download($registration);
    }

    protected function abortUnlessPublished(Event $event): void
    {
        abort_unless(EventStatus::fromMixed($event->status) === EventStatus::Published, 404);
    }

    protected function registrationIsOpen(Event $event): bool
    {
        $now = now();

        if ($event->registration_opens_at !== null && $now->lt($event->registration_opens_at)) {
            return false;
        }

        if ($event->registration_closes_at !== null && $now->gt($event->registration_closes_at)) {
            return false;
        }

        return true;
    }

    protected function abortUnlessAuthorizedRegistrationSession(Registration $registration): void
    {
        abort_unless(session('event_registration_success_id') === $registration->id, 403);
    }

    protected function resolveParticipant(StoreEventRegistrationRequest $request): Participant
    {
        $participant = Participant::withTrashed()
            ->where('nokp', $request->nokp())
            ->first();

        if ($participant !== null) {
            if ($participant->trashed()) {
                $participant->restore();
            }

            $participant->fill($request->participantData());
            $participant->save();

            return $participant;
        }

        try {
            return Participant::query()->create($request->participantData());
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $participant = Participant::withTrashed()
                ->where('nokp', $request->nokp())
                ->firstOrFail();

            if ($participant->trashed()) {
                $participant->restore();
            }

            $participant->fill($request->participantData());
            $participant->save();

            return $participant;
        }
    }

    protected function resolveRegistration(Event $event, Participant $participant): Registration
    {
        $registration = Registration::withTrashed()
            ->where('event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->first();

        if ($registration !== null) {
            if ($registration->trashed()) {
                $registration->restore();
            }

            return $registration;
        }

        try {
            return Registration::query()->create([
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'registered_at' => now(),
                'attendance_status' => AttendanceStatus::Registered->value,
                'source' => RegistrationSource::PublicForm->value,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            $registration = Registration::withTrashed()
                ->where('event_id', $event->id)
                ->where('participant_id', $participant->id)
                ->firstOrFail();

            if ($registration->trashed()) {
                $registration->restore();
            }

            return $registration;
        }
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || (($exception->errorInfo[1] ?? null) === 1062);
    }
}
