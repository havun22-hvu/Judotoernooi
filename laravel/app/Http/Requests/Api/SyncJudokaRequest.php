<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/judokas — HavunClub pushes a competition judoka into the stambestand.
 *
 * The tenant (Organisator) is resolved by the club.token middleware, never sent
 * as a parameter. HavunClub's payload (voornaam/achternaam/geboortedatum) is
 * mapped server-side onto StamJudoka's naam + geboortejaar.
 */
class SyncJudokaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation happens in the club.token middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'judotoernooi_id' => ['nullable', 'integer'],
            'havunclub_judoka_id' => ['nullable', 'string', 'max:100'],
            'voornaam' => ['required', 'string', 'max:255'],
            'achternaam' => ['required', 'string', 'max:255'],
            'geboortedatum' => ['required', 'date'],
            'geslacht' => ['required', 'string', 'max:20'],
            'band' => ['nullable', 'string', 'max:30'],
        ];
    }
}
