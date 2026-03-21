<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JudokaUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'naam' => 'required|string|max:255',
            'geboortejaar' => 'required|integer|min:1900|max:' . date('Y'),
            'geslacht' => 'required|in:M,V',
            'band' => 'required|string|max:20',
            'jbn_lidnummer' => 'nullable|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
        ];
    }

    public function messages(): array
    {
        return [
            'naam.required' => 'De naam is verplicht',
            'geboortejaar.required' => 'Geboortejaar is verplicht',
            'geboortejaar.integer' => 'Geboortejaar moet een getal zijn',
            'geslacht.required' => 'Geslacht is verplicht',
            'geslacht.in' => 'Geslacht moet M of V zijn',
            'band.required' => 'Band is verplicht',
            'gewicht.numeric' => 'Gewicht moet een getal zijn',
            'gewicht.min' => 'Gewicht moet minimaal 10 kg zijn',
            'gewicht.max' => 'Gewicht mag maximaal 200 kg zijn',
        ];
    }
}
