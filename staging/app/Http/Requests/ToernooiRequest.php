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

        // Convert JSON string to array for wedstrijd_schemas
        if ($this->has('wedstrijd_schemas') && is_string($this->wedstrijd_schemas)) {
            $this->merge([
                'wedstrijd_schemas' => json_decode($this->wedstrijd_schemas, true),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'template_id' => 'nullable|integer|exists:toernooi_templates,id',
            'naam' => 'required|string|max:255',
            'organisatie' => 'nullable|string|max:255',
            'datum' => 'required|date',
            'inschrijving_deadline' => 'nullable|date|before_or_equal:datum',
            'max_judokas' => 'nullable|integer|min:1',
            'locatie' => 'nullable|string|max:255',
            'verwacht_aantal_judokas' => 'nullable|integer|min:10|max:2000',
            'aantal_matten' => 'nullable|integer|min:1|max:20',
            'aantal_blokken' => 'nullable|integer|min:1|max:12',
            'poule_grootte_voorkeur' => 'nullable|array|min:1',
            'poule_grootte_voorkeur.*' => 'integer|min:2|max:12',
            'wedstrijd_systeem' => 'nullable|array',
            'wedstrijd_systeem.*' => 'string|in:poules,punten_competitie,poules_kruisfinale,eliminatie',
            'eliminatie_gewichtsklassen' => 'nullable|array',
            'eliminatie_gewichtsklassen.*' => 'nullable|array',
            'eliminatie_gewichtsklassen.*.*' => 'string',
            'eliminatie_type' => 'nullable|string|in:dubbel,ijf',
            'aantal_brons' => 'nullable|integer|in:1,2',
            'kruisfinales_aantal' => 'nullable|integer|min:1|max:3',
            'dubbel_bij_2_judokas' => 'nullable|boolean',
            'best_of_three_bij_2' => 'nullable|boolean',
            'dubbel_bij_3_judokas' => 'nullable|boolean',
            'dubbel_bij_4_judokas' => 'nullable|boolean',
            'gewicht_tolerantie' => 'nullable|numeric|min:0|max:5',
            'weging_verplicht' => 'nullable|boolean',
            'max_wegingen' => 'nullable|integer|min:1|max:10',
            'judokas_per_coach' => 'nullable|integer|min:1',
            'coach_incheck_actief' => 'nullable|boolean',
            'danpunten_actief' => 'nullable|boolean',
            'gewichtsklassen' => 'nullable|array',
            'gewichtsklassen.*' => 'nullable|string',
            'gebruik_gewichtsklassen' => 'nullable|boolean',
            'max_kg_verschil' => 'nullable|numeric|min:1|max:10',
            'max_leeftijd_verschil' => 'nullable|integer|min:1|max:5',
            'verdeling_prioriteiten' => 'nullable|array',
            'verdeling_prioriteiten.*' => 'string|in:leeftijd,gewicht,band',
            'wedstrijd_schemas' => 'nullable|array',
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
