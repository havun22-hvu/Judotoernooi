<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JudokaStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'naam' => 'required|string|max:255',
            'club_id' => 'nullable|exists:clubs,id',
            'geboortejaar' => 'nullable|integer|min:1990|max:' . date('Y'),
            'geslacht' => 'required|in:M,V',
            'band' => 'nullable|string|max:20',
            // Required on manual entry: not every tournament runs a weigh-in, so a judoka
            // entered without a weight never gets one. Import keeps its own per-row handling.
            'gewicht' => 'required|numeric|min:10|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'naam.required' => 'De naam is verplicht',
            'naam.max' => 'De naam mag maximaal 255 tekens zijn',
            'geboortejaar.integer' => 'Geboortejaar moet een getal zijn',
            'geboortejaar.min' => 'Ongeldig geboortejaar',
            'geslacht.required' => 'Geslacht is verplicht — zonder geslacht kan de judoka niet worden ingedeeld',
            'geslacht.in' => 'Geslacht moet M of V zijn',
            'gewicht.required' => 'Gewicht is verplicht — niet elk toernooi heeft een weging',
            'gewicht.numeric' => 'Gewicht moet een getal zijn',
            'gewicht.min' => 'Gewicht moet minimaal 10 kg zijn',
            'gewicht.max' => 'Gewicht mag maximaal 200 kg zijn',
        ];
    }
}
