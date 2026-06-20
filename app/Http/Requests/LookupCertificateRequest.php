<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesNokp;
use Illuminate\Foundation\Http\FormRequest;

class LookupCertificateRequest extends FormRequest
{
    use NormalizesNokp;

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
            'nokp' => $this->nokpRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->nokpMessages();
    }
}
