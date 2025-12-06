<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToernooiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add proper authorization later
    }

    public function rules(): array
    {
        return [
            'naam' => 'required|string|max:255',
            'organisatie' => 'nullable|string|max:255',
            'datum' => 'required|date',
            'locatie' => 'nullable|string|max:255',
            'aantal_matten' => 'nullable|integer|min:1|max:20',
            'aantal_blokken' => 'nullable|integer|min:1|max:12',
            'min_judokas_poule' => 'nullable|integer|min:2|max:10',
            'optimal_judokas_poule' => 'nullable|integer|min:3|max:10',
            'max_judokas_poule' => 'nullable|integer|min:4|max:12',
            'gewicht_tolerantie' => 'nullable|numeric|min:0|max:5',
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
