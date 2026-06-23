<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the per-role tournament access passwords (admin/jury/weging/mat/
 * spreker). Each is optional — only non-empty ones are applied by the controller.
 */
class ToernooiWachtwoordenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is enforced by the organisator guard + route-model-binding.
        return true;
    }

    public function rules(): array
    {
        $rule = 'nullable|string|min:4|max:255';

        return [
            'wachtwoord_admin' => $rule,
            'wachtwoord_jury' => $rule,
            'wachtwoord_weging' => $rule,
            'wachtwoord_mat' => $rule,
            'wachtwoord_spreker' => $rule,
        ];
    }

    public function messages(): array
    {
        return [
            'wachtwoord_admin.min' => 'Het admin-wachtwoord moet minimaal 4 tekens zijn',
            'wachtwoord_jury.min' => 'Het jury-wachtwoord moet minimaal 4 tekens zijn',
            'wachtwoord_weging.min' => 'Het weging-wachtwoord moet minimaal 4 tekens zijn',
            'wachtwoord_mat.min' => 'Het mat-wachtwoord moet minimaal 4 tekens zijn',
            'wachtwoord_spreker.min' => 'Het spreker-wachtwoord moet minimaal 4 tekens zijn',
        ];
    }
}
