<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WegingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gewicht' => 'required|numeric|min:10|max:250',
            'opmerking' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'gewicht.required' => 'Gewicht is verplicht',
            'gewicht.numeric' => 'Gewicht moet een getal zijn',
            'gewicht.min' => 'Gewicht moet minimaal 10 kg zijn',
            'gewicht.max' => 'Gewicht mag maximaal 250 kg zijn',
            'opmerking.max' => 'Opmerking mag maximaal 500 tekens zijn',
        ];
    }
}
