<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use App\Models\Mat;
use App\Services\ToernooiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleToegang extends Controller
{
    public function __construct(
        private ToernooiService $toernooiService
    ) {}

    /**
     * Handle role access via secret code - redirects to generic URL
     */
    public function access(Request $request, string $code): RedirectResponse
    {
        $result = $this->findToernooiByCode($code);

        if (!$result) {
            abort(404);
        }

        [$toernooi, $rol] = $result;

        // Store in session with different keys than old system
        $request->session()->put('rol_toernooi_id', $toernooi->id);
        $request->session()->put('rol_type', $rol);

        // Redirect to generic URL (code disappears from address bar)
        return match ($rol) {
            'hoofdjury' => redirect()->route('rol.jury'),
            'weging' => redirect()->route('rol.weging'),
            'mat' => redirect()->route('rol.mat'),
            'spreker' => redirect()->route('rol.spreker'),
            'dojo' => redirect()->route('rol.dojo'),
            default => abort(404),
        };
    }

    /**
     * Weging interface (generic URL)
     */
    public function wegingInterface(Request $request): View
    {
        $toernooi = $this->getToernooiFromSession($request);
        $this->checkRole($request, 'weging');

        return view('pages.weging.interface', [
            'toernooi' => $toernooi,
            'blokken' => $toernooi->blokken,
        ]);
    }

    /**
     * Mat selection interface (generic URL)
     */
    public function matInterface(Request $request): View
    {
        $toernooi = $this->getToernooiFromSession($request);
        $this->checkRole($request, 'mat');

        return view('pages.mat.interface', [
            'toernooi' => $toernooi,
            'blokken' => $toernooi->blokken,
            'matten' => $toernooi->matten,
        ]);
    }

    /**
     * Mat show (specific mat)
     */
    public function matShow(Request $request, int $mat): View
    {
        $toernooi = $this->getToernooiFromSession($request);
        $this->checkRole($request, 'mat');

        $matModel = Mat::where('toernooi_id', $toernooi->id)
            ->where('nummer', $mat)
            ->firstOrFail();

        return view('pages.mat.show', [
            'toernooi' => $toernooi,
            'mat' => $matModel,
        ]);
    }

    /**
     * Jury/Hoofdjury interface (generic URL)
     */
    public function juryInterface(Request $request): View
    {
        $toernooi = $this->getToernooiFromSession($request);
        $this->checkRole($request, 'hoofdjury');

        // Define age class order (youngest to oldest)
        $leeftijdsklasseVolgorde = [
            "Mini's" => 1, 'A-pupillen' => 2, 'B-pupillen' => 3,
            'U9' => 1, 'U11' => 2, 'U13' => 3, 'U15' => 4, 'U18' => 5, 'U21' => 6, 'Senioren' => 7,
        ];

        $poules = $toernooi->poules()
            ->with(['blok', 'mat', 'judokas.club'])
            ->withCount('judokas')
            ->get();

        // Sort by age class and weight class
        $poules = $poules->sortBy([
            fn ($a, $b) => ($leeftijdsklasseVolgorde[$a->leeftijdsklasse] ?? 99) <=> ($leeftijdsklasseVolgorde[$b->leeftijdsklasse] ?? 99),
            fn ($a, $b) => (int) filter_var($a->gewichtsklasse, FILTER_SANITIZE_NUMBER_INT) <=> (int) filter_var($b->gewichtsklasse, FILTER_SANITIZE_NUMBER_INT),
            fn ($a, $b) => $a->nummer <=> $b->nummer,
        ]);

        $poulesPerKlasse = $poules->groupBy('leeftijdsklasse');

        return view('pages.poule.index', compact('toernooi', 'poules', 'poulesPerKlasse'));
    }

    /**
     * Spreker interface (generic URL)
     */
    public function sprekerInterface(Request $request): View
    {
        $toernooi = $this->getToernooiFromSession($request);
        $this->checkRole($request, 'spreker');

        return view('pages.blok.spreker', [
            'toernooi' => $toernooi,
            'blokken' => $toernooi->blokken()->with('matten')->get(),
        ]);
    }

    /**
     * Dojo scanner interface (generic URL)
     */
    public function dojoInterface(Request $request): View
    {
        $toernooi = $this->getToernooiFromSession($request);
        $this->checkRole($request, 'dojo');

        return view('pages.dojo.scanner', [
            'toernooi' => $toernooi,
        ]);
    }

    // ========================================
    // Device-bound interface methods (new system)
    // ========================================

    /**
     * Weging interface (device-bound)
     */
    public function wegingDeviceBound(Request $request): View
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        return view('pages.weging.interface', [
            'toernooi' => $toernooi,
            'blokken' => $toernooi->blokken,
            'toegang' => $toegang,
        ]);
    }

    /**
     * Mat interface (device-bound)
     */
    public function matDeviceBound(Request $request): View
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        return view('pages.mat.interface', [
            'toernooi' => $toernooi,
            'blokken' => $toernooi->blokken,
            'matten' => $toernooi->matten,
            'toegang' => $toegang,
            'matNummer' => $toegang->mat_nummer,
            'standalone' => true,
        ]);
    }

    /**
     * Jury/Hoofdjury interface (device-bound)
     */
    public function juryDeviceBound(Request $request): View
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        // Define age class order (youngest to oldest)
        $leeftijdsklasseVolgorde = [
            "Mini's" => 1, 'A-pupillen' => 2, 'B-pupillen' => 3,
            'U9' => 1, 'U11' => 2, 'U13' => 3, 'U15' => 4, 'U18' => 5, 'U21' => 6, 'Senioren' => 7,
        ];

        $poules = $toernooi->poules()
            ->with(['blok', 'mat', 'judokas.club'])
            ->withCount('judokas')
            ->get();

        // Sort by age class and weight class
        $poules = $poules->sortBy([
            fn ($a, $b) => ($leeftijdsklasseVolgorde[$a->leeftijdsklasse] ?? 99) <=> ($leeftijdsklasseVolgorde[$b->leeftijdsklasse] ?? 99),
            fn ($a, $b) => (int) filter_var($a->gewichtsklasse, FILTER_SANITIZE_NUMBER_INT) <=> (int) filter_var($b->gewichtsklasse, FILTER_SANITIZE_NUMBER_INT),
            fn ($a, $b) => $a->nummer <=> $b->nummer,
        ]);

        $poulesPerKlasse = $poules->groupBy('leeftijdsklasse');

        return view('pages.poule.index', compact('toernooi', 'poules', 'poulesPerKlasse', 'toegang'));
    }

    /**
     * Spreker interface (device-bound)
     */
    public function sprekerDeviceBound(Request $request): View
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        return view('pages.blok.spreker', [
            'toernooi' => $toernooi,
            'blokken' => $toernooi->blokken()->with('matten')->get(),
            'toegang' => $toegang,
        ]);
    }

    /**
     * Dojo scanner interface (device-bound)
     */
    public function dojoDeviceBound(Request $request): View
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        return view('pages.dojo.scanner', [
            'toernooi' => $toernooi,
            'toegang' => $toegang,
        ]);
    }

    /**
     * Get toernooi from session
     */
    private function getToernooiFromSession(Request $request): Toernooi
    {
        $toernooiId = $request->session()->get('rol_toernooi_id');
        return Toernooi::findOrFail($toernooiId);
    }

    /**
     * Check if user has correct role
     */
    private function checkRole(Request $request, string $expectedRole): void
    {
        $rol = $request->session()->get('rol_type');
        if ($rol !== $expectedRole) {
            abort(403, 'Geen toegang tot deze functie.');
        }
    }

    /**
     * Find toernooi and role by code
     */
    private function findToernooiByCode(string $code): ?array
    {
        $roles = ['hoofdjury', 'weging', 'mat', 'spreker', 'dojo'];

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
            ->orWhere('code_dojo', $code)
            ->exists();
    }
}
