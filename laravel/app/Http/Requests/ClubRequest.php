<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'naam' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'contact_naam' => 'nullable|string|max:255',
            'telefoon' => ['nullable', 'string', 'max:20', 'regex:/^(\+31|0)[1-9][\d\s\-]{7,12}$/'],
            'plaats' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'naam.required' => 'De clubnaam is verplicht',
            'naam.max' => 'De clubnaam mag maximaal 255 tekens zijn',
            'email.email' => 'Voer een geldig e-mailadres in',
            'email2.email' => 'Voer een geldig tweede e-mailadres in',
            'telefoon.regex' => 'Voer een geldig Nederlands telefoonnummer in (bijv. 06-12345678 of +31612345678)',
        ];
    }
}
