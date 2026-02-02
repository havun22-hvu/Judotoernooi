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
            'geslacht' => 'nullable|in:M,V',
            'band' => 'nullable|string|max:20',
            'gewicht' => 'nullable|numeric|min:10|max:200',
            'telefoon' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'naam.required' => 'De naam is verplicht',
            'naam.max' => 'De naam mag maximaal 255 tekens zijn',
            'geboortejaar.integer' => 'Geboortejaar moet een getal zijn',
            'geboortejaar.min' => 'Ongeldig geboortejaar',
            'geslacht.in' => 'Geslacht moet M of V zijn',
            'gewicht.numeric' => 'Gewicht moet een getal zijn',
            'gewicht.min' => 'Gewicht moet minimaal 10 kg zijn',
            'gewicht.max' => 'Gewicht mag maximaal 200 kg zijn',
        ];
    }
}
