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
            'seatsRemaining' => $event->seatsRemaining(),
            'isFull' => $event->isFull(),
        ]);
    }

    public function store(
        StoreEventRegistrationRequest $request,
        Event $event,
        RegistrationCertificateIssuer $certificateIssuer,
    ): RedirectResponse {
        $this->abortUnlessPublished($event);

        if (! $this->registrationIsOpen($event)) {
            return back()
                ->withInput()
                ->withErrors([
                    'event' => 'Registration is not open for this event.',
                ]);
        }

        $result = DB::transaction(function () use ($request, $event, $certificateIssuer): ?array {
            // Seat capacity (stock control): a new registration consumes a seat.
            // The check locks the event row to serialize concurrent sign-ups and
            // never blocks a returning participant who already holds a seat.
            if ($event->capacity !== null && $this->eventIsFullForNewSignup($request, $event)) {
                return null;
            }

            $participant = $this->resolveParticipant($request, $event);
            $registration = $this->resolveRegistration($request, $event, $participant);

            $certificateIssuer->issueFor($registration);

            return [$participant, $registration];
        });

        if ($result === null) {
            return back()
                ->withInput()
                ->withErrors(['event' => 'Pendaftaran penuh — tiada tempat lagi.']);
        }

        [$participant, $registration] = $result;

        if ($registration->wasRecentlyCreated && ($event->organization?->notifies('registration_submitted_enabled') ?? true)) {
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

        abort_unless($registration->certificate_template_id !== null, 404);

        return $storedCertificatePdf->download($registration);
    }

    protected function abortUnlessPublished(Event $event): void
    {
        abort_unless(EventStatus::fromMixed($event->status) === EventStatus::Published, 404);
    }

    protected function registrationIsOpen(Event $event): bool
    {
        return (bool) $event->registration_open;
    }

    /**
     * Within the registration transaction: lock the event row to serialize
     * concurrent sign-ups, then report whether a NEW registration would exceed
     * the seat capacity. A returning participant (who already holds a seat) is
     * never considered full. Only called when the event has a capacity set.
     */
    protected function eventIsFullForNewSignup(StoreEventRegistrationRequest $request, Event $event): bool
    {
        Event::whereKey($event->getKey())->lockForUpdate()->first();

        $participant = Participant::withTrashed()
            ->where('organization_id', $event->organization_id)
            ->where('email', (string) $request->input('email'))
            ->first();

        $alreadyRegistered = $participant !== null && Registration::withTrashed()
            ->where('event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->exists();

        return ! $alreadyRegistered
            && Registration::where('event_id', $event->id)->count() >= $event->capacity;
    }

    protected function abortUnlessAuthorizedRegistrationSession(Registration $registration): void
    {
        abort_unless(session('event_registration_success_id') === $registration->id, 403);
    }

    protected function resolveParticipant(StoreEventRegistrationRequest $request, Event $event): Participant
    {
        // Returning participants are matched by email within the organization.
        $participant = Participant::withTrashed()
            ->where('organization_id', $event->organization_id)
            ->where('email', (string) $request->input('email'))
            ->first();

        if ($participant !== null && $participant->trashed()) {
            $participant->restore();
        }

        return $this->saveParticipant($participant ?? new Participant, $request, $event);
    }

    protected function saveParticipant(Participant $participant, StoreEventRegistrationRequest $request, Event $event): Participant
    {
        $participant->fill($request->participantData());
        $participant->organization_id ??= $event->organization_id;

        // Merge (not replace) so admin-only detail fields survive re-registration.
        $details = $request->publicParticipantDetails();
        if ($details !== []) {
            $participant->details = array_merge($participant->details ?? [], $details);
        }

        $participant->save();

        return $participant;
    }

    protected function resolveRegistration(StoreEventRegistrationRequest $request, Event $event, Participant $participant): Registration
    {
        $details = $request->publicRegistrationDetails();

        $registration = Registration::withTrashed()
            ->where('event_id', $event->id)
            ->where('participant_id', $participant->id)
            ->first();

        if ($registration !== null) {
            if ($registration->trashed()) {
                $registration->restore();
            }

            return $this->applyRegistrationDetails($registration, $details);
        }

        try {
            return Registration::query()->create([
                'organization_id' => $event->organization_id,
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'registered_at' => now(),
                'attendance_status' => AttendanceStatus::Registered->value,
                'source' => RegistrationSource::PublicForm->value,
                'details' => $details !== [] ? $details : null,
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

            return $this->applyRegistrationDetails($registration, $details);
        }
    }

    /**
     * Merge (not replace) submitted registration custom fields, so admin-only
     * detail fields survive a re-registration.
     *
     * @param  array<string, mixed>  $details
     */
    protected function applyRegistrationDetails(Registration $registration, array $details): Registration
    {
        if ($details !== []) {
            $registration->details = array_merge($registration->details ?? [], $details);
            $registration->save();
        }

        return $registration;
    }

    protected function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            || (($exception->errorInfo[1] ?? null) === 1062);
    }
}
