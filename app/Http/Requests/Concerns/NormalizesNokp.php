<?php

namespace App\Http\Requests\Concerns;

use App\Support\Nokp;

/**
 * Shared NOKP (Malaysian IC) handling for the public request classes: strip to
 * digits before validation and enforce the 12-digit format.
 */
trait NormalizesNokp
{
    public function nokp(): string
    {
        return Nokp::digits($this->input('nokp'));
    }

    /**
     * @return array<int, string>
     */
    protected function nokpRules(): array
    {
        return ['required', 'string', 'regex:/^\d{12}$/'];
    }

    /**
     * @return array<string, string>
     */
    protected function nokpMessages(): array
    {
        return [
            'nokp.required' => 'No. KP is required.',
            'nokp.regex' => 'No. KP mesti mengandungi 12 digit.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nokp' => $this->nokp(),
        ]);
    }
}
