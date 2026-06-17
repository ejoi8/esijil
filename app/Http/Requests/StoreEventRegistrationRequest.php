<?php

namespace App\Http\Requests;

use App\Enums\MembershipStatus;
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
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'nokp' => $this->nokpRules(),
            'phone' => ['nullable', 'string', 'max:50'],
            'membership_status' => ['required', Rule::in(MembershipStatus::values())],
            'membership_notes' => ['nullable', 'string', 'max:1000'],
        ];
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
