<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Stateless check-in scan payload. Auth is the station token in the body — no
 * session, no CSRF (it's an API route).
 */
class ScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'station_token' => ['required', 'string'],
            'code' => ['required', 'string', 'max:255'],
            // Station PIN — required by the controller only when the station has one.
            'pin' => ['nullable', 'string', 'max:20'],
            // false = identify only (no write); true/absent = record the check-in.
            'confirm' => ['sometimes', 'boolean'],
            // Member bypass token from the scanner page (alternative to the PIN).
            'bypass' => ['nullable', 'string'],
        ];
    }
}
