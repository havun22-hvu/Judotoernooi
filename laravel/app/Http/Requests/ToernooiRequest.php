<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToernooiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add proper authorization later
    }

    protected function prepareForValidation(): void
    {
        // Convert JSON string to array for poule_grootte_voorkeur
        if ($this->has('poule_grootte_voorkeur') && is_string($this->poule_grootte_voorkeur)) {
            $this->merge([
                'poule_grootte_voorkeur' => json_decode($this->poule_grootte_voorkeur, true),
            ]);
        }

        // Convert JSON string to array for verdeling_prioriteiten
        if ($this->has('verdeling_prioriteiten') && is_string($this->verdeling_prioriteiten)) {
            $this->merge([
                'verdeling_prioriteiten' => json_decode($this->verdeling_prioriteiten, true),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'naam' => 'required|string|max:255',
            'organisatie' => 'nullable|string|max:255',
            'datum' => 'required|date',
            'inschrijving_deadline' => 'nullable|date|before_or_equal:datum',
            'max_judokas' => 'nullable|integer|min:1',
            'locatie' => 'nullable|string|max:255',
            'aantal_matten' => 'nullable|integer|min:1|max:20',
            'aantal_blokken' => 'nullable|integer|min:1|max:12',
            'poule_grootte_voorkeur' => 'nullable|array|min:1',
            'poule_grootte_voorkeur.*' => 'integer|min:2|max:12',
            'clubspreiding' => 'nullable|boolean',
            'wedstrijd_systeem' => 'nullable|array',
            'wedstrijd_systeem.*' => 'string|in:poules,poules_kruisfinale,eliminatie',
            'kruisfinales_aantal' => 'nullable|integer|min:1|max:3',
            'gewicht_tolerantie' => 'nullable|numeric|min:0|max:5',
            'gewichtsklassen' => 'nullable|array',
            'gewichtsklassen.*' => 'nullable|string',
            'verdeling_prioriteiten' => 'nullable|array',
            'verdeling_prioriteiten.*' => 'string|in:groepsgrootte,bandkleur,clubspreiding',
        ];
    }

    public function messages(): array
    {
        return [
            'naam.required' => 'De naam van het toernooi is verplicht',
            'datum.required' => 'De datum van het toernooi is verplicht',
            'datum.date' => 'Voer een geldige datum in',
        ];
    }
}
