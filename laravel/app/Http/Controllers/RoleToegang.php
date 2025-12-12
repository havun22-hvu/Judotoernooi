<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleToegang extends Controller
{
    /**
     * Handle role access via secret code
     */
    public function access(Request $request, string $code): View|RedirectResponse
    {
        // Find toernooi and role by code
        $result = $this->findToernooiByCode($code);

        if (!$result) {
            abort(404);
        }

        [$toernooi, $rol] = $result;

        // Store in session
        $request->session()->put('toernooi_id', $toernooi->id);
        $request->session()->put('rol', $rol);

        // Redirect to appropriate interface
        return match ($rol) {
            'hoofdjury' => redirect()->route('toernooi.poule.index', $toernooi),
            'weging' => redirect()->route('toernooi.weging.interface', $toernooi),
            'mat' => redirect()->route('toernooi.mat.interface', $toernooi),
            'spreker' => redirect()->route('toernooi.spreker.interface', $toernooi),
            default => abort(404),
        };
    }

    /**
     * Find toernooi and role by code
     */
    private function findToernooiByCode(string $code): ?array
    {
        $roles = ['hoofdjury', 'weging', 'mat', 'spreker'];

        foreach ($roles as $rol) {
            $toernooi = Toernooi::where("code_{$rol}", $code)->first();
            if ($toernooi) {
                return [$toernooi, $rol];
            }
        }

        return null;
    }

    /**
     * Generate a unique 12-character code
     */
    public static function generateCode(): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
        do {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (self::codeExists($code));

        return $code;
    }

    /**
     * Check if code already exists in any role column
     */
    private static function codeExists(string $code): bool
    {
        return Toernooi::where('code_hoofdjury', $code)
            ->orWhere('code_weging', $code)
            ->orWhere('code_mat', $code)
            ->orWhere('code_spreker', $code)
            ->exists();
    }
}
