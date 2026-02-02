<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WedstrijdUitslagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wedstrijd_id' => 'required|exists:wedstrijden,id',
            'winnaar_id' => 'nullable|exists:judokas,id',
            'score_wit' => 'nullable|integer|in:0,1,2',
            'score_blauw' => 'nullable|integer|in:0,1,2',
            'uitslag_type' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'wedstrijd_id.required' => 'Wedstrijd ID is verplicht',
            'wedstrijd_id.exists' => 'Wedstrijd niet gevonden',
            'winnaar_id.exists' => 'Winnaar niet gevonden',
            'score_wit.integer' => 'Score wit moet een getal zijn',
            'score_wit.in' => 'Score wit moet 0, 1 of 2 zijn',
            'score_blauw.integer' => 'Score blauw moet een getal zijn',
            'score_blauw.in' => 'Score blauw moet 0, 1 of 2 zijn',
        ];
    }
}
