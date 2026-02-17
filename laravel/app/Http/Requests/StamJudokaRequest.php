<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StamJudokaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'naam' => 'required|string|max:255',
            'geboortejaar' => 'required|integer|min:1950|max:' . date('Y'),
            'geslacht' => 'required|in:M,V',
            'band' => 'required|in:wit,geel,oranje,groen,blauw,bruin,zwart',
            'gewicht' => 'nullable|numeric|min:10|max:200',
            'notities' => 'nullable|string|max:1000',
        ];
    }
}
