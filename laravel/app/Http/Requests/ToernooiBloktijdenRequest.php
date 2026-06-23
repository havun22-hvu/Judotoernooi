<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the per-blok times (weging start/einde + starttijd). Input is a
 * `blokken` map keyed by blok-id; each entry holds optional H:i time strings
 * (HTML <input type="time">). Empty values are cleared to null by the controller.
 */
class ToernooiBloktijdenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by the organisator guard + route-model-binding.
        return true;
    }

    public function rules(): array
    {
        return [
            'blokken' => 'nullable|array',
            'blokken.*.weging_start' => 'nullable|date_format:H:i',
            'blokken.*.weging_einde' => 'nullable|date_format:H:i',
            'blokken.*.starttijd' => 'nullable|date_format:H:i',
        ];
    }

    public function messages(): array
    {
        return [
            'blokken.array' => 'De bloktijden hebben een ongeldige vorm',
            'blokken.*.weging_start.date_format' => 'De weging-starttijd moet een geldige tijd zijn (uu:mm)',
            'blokken.*.weging_einde.date_format' => 'De weging-eindtijd moet een geldige tijd zijn (uu:mm)',
            'blokken.*.starttijd.date_format' => 'De starttijd moet een geldige tijd zijn (uu:mm)',
        ];
    }
}
