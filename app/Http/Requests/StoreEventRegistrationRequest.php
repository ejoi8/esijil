<?php

namespace App\Http\Requests;

use App\Enums\MembershipStatus;
use App\Fields\ParticipantFields;
use App\Http\Requests\Concerns\NormalizesNokp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRegistrationRequest extends FormRequest
{
    use NormalizesNokp;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'nokp' => $this->nokpRules(),
            'phone' => ['nullable', 'string', 'max:50'],
            'membership_status' => ['required', Rule::in(MembershipStatus::values())],
            'membership_notes' => ['nullable', 'string', 'max:1000'],
        ], ParticipantFields::rules('public'));
    }

    /**
     * Submitted values for the public flexible fields (config/participant_fields.php),
     * limited to defined public keys so arbitrary keys can't be injected.
     *
     * @return array<string, mixed>
     */
    public function publicDetails(): array
    {
        $details = [];

        foreach (array_keys(ParticipantFields::publicFields()) as $key) {
            $value = $this->input("details.{$key}");

            if ($value !== null && $value !== '') {
                $details[$key] = $value;
            }
        }

        return $details;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->nokpMessages();
    }

    /**
     * @return array<string, string|null>
     */
    public function participantData(): array
    {
        return [
            'full_name' => (string) $this->input('full_name'),
            'email' => (string) $this->input('email'),
            'nokp' => $this->nokp(),
            'phone' => $this->filled('phone') ? (string) $this->input('phone') : null,
            'membership_status' => (string) $this->input('membership_status'),
            'membership_notes' => $this->filled('membership_notes') ? (string) $this->input('membership_notes') : null,
        ];
    }
}
