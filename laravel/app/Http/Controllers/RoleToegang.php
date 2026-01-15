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

        // Get age class order from preset config (youngest to oldest)
        $leeftijdsklasseVolgorde = $toernooi->getCategorieVolgorde();

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
        ]);
    }

    /**
     * Jury/Hoofdjury interface (device-bound)
     */
    public function juryDeviceBound(Request $request): View
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        // Get age class order from preset config (youngest to oldest)
        $leeftijdsklasseVolgorde = $toernooi->getCategorieVolgorde();

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
     * Same data as BlokController::sprekerInterface but standalone PWA view
     */
    public function sprekerDeviceBound(Request $request): View
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        // Klare poules (spreker_klaar gezet, nog niet afgeroepen)
        $klarePoules = $toernooi->poules()
            ->whereNotNull('spreker_klaar')
            ->whereNull('afgeroepen_at')
            ->with(['mat', 'blok', 'judokas.club', 'wedstrijden'])
            ->orderBy('spreker_klaar', 'asc')
            ->get()
            ->map(function ($poule) {
                if ($poule->type === 'eliminatie') {
                    $poule->standings = $this->getEliminatieStandings($poule);
                    $poule->is_eliminatie = true;
                    return $poule;
                }

                // Calculate standings for regular poule
                $standings = $poule->judokas->map(function ($judoka) use ($poule) {
                    $wp = 0;
                    $jp = 0;
                    foreach ($poule->wedstrijden as $w) {
                        if (!$w->is_gespeeld) continue;
                        if ($w->judoka_wit_id === $judoka->id) {
                            $jp += $w->score_wit ?? 0;
                            if ($w->winnaar_id === $judoka->id) $wp++;
                        } elseif ($w->judoka_blauw_id === $judoka->id) {
                            $jp += $w->score_blauw ?? 0;
                            if ($w->winnaar_id === $judoka->id) $wp++;
                        }
                    }
                    return ['judoka' => $judoka, 'wp' => $wp, 'jp' => $jp];
                });

                $wedstrijden = $poule->wedstrijden;
                $poule->standings = $standings->sort(function ($a, $b) use ($wedstrijden) {
                    if ($a['wp'] !== $b['wp']) return $b['wp'] - $a['wp'];
                    if ($a['jp'] !== $b['jp']) return $b['jp'] - $a['jp'];
                    foreach ($wedstrijden as $w) {
                        $isMatch = ($w->judoka_wit_id === $a['judoka']->id && $w->judoka_blauw_id === $b['judoka']->id)
                                || ($w->judoka_wit_id === $b['judoka']->id && $w->judoka_blauw_id === $a['judoka']->id);
                        if ($isMatch && $w->winnaar_id) {
                            return $w->winnaar_id === $a['judoka']->id ? -1 : 1;
                        }
                    }
                    return 0;
                })->values();

                $poule->is_eliminatie = false;
                return $poule;
            });

        // Recent afgeroepen (voor "Terug" functie)
        $afgeroepen = $toernooi->poules()
            ->whereNotNull('afgeroepen_at')
            ->where('afgeroepen_at', '>=', now()->subMinutes(30))
            ->with(['mat', 'blok', 'judokas.club', 'wedstrijden'])
            ->orderBy('afgeroepen_at', 'desc')
            ->get()
            ->map(function ($poule) {
                if ($poule->type === 'eliminatie') {
                    $poule->standings = $this->getEliminatieStandings($poule);
                    $poule->is_eliminatie = true;
                }
                return $poule;
            });

        // Poules per blok/mat voor "Oproepen" tab
        $blokken = $toernooi->blokken()
            ->with(['poules' => function ($q) {
                $q->with(['mat', 'judokas.club'])
                    ->whereNotNull('mat_id')
                    ->orderBy('mat_id')
                    ->orderBy('volgorde');
            }])
            ->orderBy('nummer')
            ->get();

        $poulesPerBlok = $blokken->mapWithKeys(function ($blok) {
            $poulesPerMat = $blok->poules->groupBy('mat_id')->map(function ($poules, $matId) {
                return [
                    'mat' => $poules->first()->mat,
                    'poules' => $poules,
                ];
            })->sortKeys();
            return [$blok->nummer => ['blok' => $blok, 'matten' => $poulesPerMat]];
        });

        return view('pages.spreker.interface', compact('toernooi', 'klarePoules', 'afgeroepen', 'poulesPerBlok', 'toegang'));
    }

    /**
     * Get standings for elimination bracket (for spreker interface)
     */
    private function getEliminatieStandings($poule): \Illuminate\Support\Collection
    {
        $standings = collect();

        // GOUD = Finale winnaar
        $finale = $poule->wedstrijden->first(fn($w) => $w->groep === 'A' && $w->ronde === 'finale');
        if ($finale && $finale->is_gespeeld && $finale->winnaar_id) {
            $goud = $finale->winnaar_id === $finale->judoka_wit_id
                ? $poule->judokas->firstWhere('id', $finale->judoka_wit_id)
                : $poule->judokas->firstWhere('id', $finale->judoka_blauw_id);
            if ($goud) {
                $standings->push(['judoka' => $goud, 'wp' => null, 'jp' => null, 'plaats' => 1]);
            }

            // ZILVER = Finale verliezer
            $zilver = $finale->winnaar_id === $finale->judoka_wit_id
                ? $poule->judokas->firstWhere('id', $finale->judoka_blauw_id)
                : $poule->judokas->firstWhere('id', $finale->judoka_wit_id);
            if ($zilver) {
                $standings->push(['judoka' => $zilver, 'wp' => null, 'jp' => null, 'plaats' => 2]);
            }
        }

        // BRONS = Winnaars van troostfinales
        $bronsWedstrijden = $poule->wedstrijden->filter(fn($w) =>
            in_array($w->ronde, ['b_halve_finale_2', 'b_brons', 'b_finale']) && $w->is_gespeeld && $w->winnaar_id
        );

        $bronsIds = $bronsWedstrijden->pluck('winnaar_id')->unique()
            ->reject(fn($id) => $standings->contains(fn($s) => $s['judoka']->id === $id));

        foreach ($bronsIds as $id) {
            $judoka = $poule->judokas->firstWhere('id', $id);
            if ($judoka) {
                $standings->push(['judoka' => $judoka, 'wp' => null, 'jp' => null, 'plaats' => 3]);
            }
        }

        return $standings;
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
