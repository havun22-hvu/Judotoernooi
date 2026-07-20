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
            // Required: an entry without a weight cannot be seeded, and not every tournament has
            // a weigh-in to fill it in later. Bounds match every other judoka path -- 0.5 kg and
            // 280 kg are data-entry errors, not judokas. See JudokaStoreRequest.
            'gewicht' => ['required', 'numeric', 'min:10', 'max:200'],
        ];
    }
}
