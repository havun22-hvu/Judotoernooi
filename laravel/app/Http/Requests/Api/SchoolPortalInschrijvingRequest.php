<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/school-portal/{code}/inschrijvingen
 *
 * HavunClub fills an invited school portal (scenario 2: another organiser's
 * tournament, judoschool-portals enabled). Authorisation is the per-tournament
 * portal code (route param) + 5-digit PIN — NOT the global ClubApiToken, which
 * is scoped to the whole Organisator. The judoka shape mirrors the existing
 * HavunClub sync so HavunClub sends one consistent payload everywhere.
 */
class SchoolPortalInschrijvingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pincode' => ['required', 'string', 'size:5'],
            'havunclub_judoka_id' => ['nullable'],
            'voornaam' => ['required', 'string', 'max:255'],
            'achternaam' => ['required', 'string', 'max:255'],
            'geboortedatum' => ['nullable', 'date'],
            'geslacht' => ['nullable', 'string', 'max:20'],
            'band' => ['nullable', 'string', 'max:30'],
            'gewicht' => ['nullable', 'numeric', 'min:0', 'max:300'],
        ];
    }
}
