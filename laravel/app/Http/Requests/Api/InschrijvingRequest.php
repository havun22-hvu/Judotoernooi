<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/inschrijvingen — HavunClub enters a stam judoka into a tournament.
 *
 * judoka_id refers to a StamJudoka (the standing roster entry). The tenant is
 * resolved by the club.token middleware; toernooi + judoka are checked to belong
 * to that tenant in the controller.
 */
class InschrijvingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'toernooi_id' => ['required', 'integer'],
            'judoka_id' => ['required', 'integer'],
            'naam' => ['nullable', 'string', 'max:255'],
            'band' => ['nullable', 'string', 'max:30'],
            'gewicht' => ['nullable', 'numeric', 'min:0', 'max:300'],
        ];
    }
}
