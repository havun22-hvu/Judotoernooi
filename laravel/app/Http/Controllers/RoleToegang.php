<?php

namespace App\Http\Controllers;

use App\Models\Toernooi;
use App\Models\Mat;
use App\Services\ToernooiService;
use App\Services\WedstrijdService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleToegang extends Controller
{
    public function __construct(
        private ToernooiService $toernooiService,
        private WedstrijdService $wedstrijdService
    ) {}

    /**
     * Handle role access via secret code - redirects to generic URL
     */
    public function access(Request $request, string $code): RedirectResponse
    {
        $result = $this->findToernooiByCode($code);

        if (!$result) {
            abort(response()->view('errors.vrijwilliger', [
                'message' => 'Deze link is ongeldig of niet meer actief. Vraag een nieuwe link bij de jurytafel.',
            ], 404));
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

        // Get first non-closed block (same logic as MatController::show)
        $blok = $toernooi->blokken()
            ->where('weging_gesloten', true)
            ->orderBy('nummer')
            ->first();

        $schema = $blok
            ? $this->wedstrijdService->getSchemaVoorMat($blok, $matModel)
            : [];

        return view('pages.mat.show', [
            'toernooi' => $toernooi,
            'mat' => $matModel,
            'blok' => $blok,
            'schema' => $schema,
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
        // EXCLUDE barrage poules - they are only used to determine standings in original poule
        $klarePoules = $toernooi->poules()
            ->whereNotNull('spreker_klaar')
            ->whereNull('afgeroepen_at')
            ->where('type', '!=', 'barrage')  // Don't show barrage poules separately
            ->with(['mat', 'blok', 'judokas.club', 'wedstrijden'])
            ->orderBy('spreker_klaar', 'asc')
            ->get()
            ->map(function ($poule) use ($toernooi) {
                if ($poule->type === 'eliminatie') {
                    $poule->standings = $this->getEliminatieStandings($poule);
                    $poule->is_eliminatie = true;
                    return $poule;
                }

                // Check if there's a completed barrage for this poule
                $barrage = $toernooi->poules()
                    ->where('barrage_van_poule_id', $poule->id)
                    ->whereNotNull('spreker_klaar')
                    ->with(['wedstrijden', 'judokas'])
                    ->first();

                // Calculate standings for regular poule + barrage points
                $standings = $poule->judokas->map(function ($judoka) use ($poule, $barrage) {
                    $wp = 0;
                    $jp = 0;

                    // Points from original poule
                    foreach ($poule->wedstrijden as $w) {
                        if (!$w->is_gespeeld) continue;
                        if ($w->judoka_wit_id === $judoka->id) {
                            $jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                            if ($w->winnaar_id === $judoka->id) $wp += 2;
                        } elseif ($w->judoka_blauw_id === $judoka->id) {
                            $jp += (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
                            if ($w->winnaar_id === $judoka->id) $wp += 2;
                        }
                    }

                    // ADD barrage points if judoka participated in barrage
                    if ($barrage && $barrage->judokas->contains('id', $judoka->id)) {
                        foreach ($barrage->wedstrijden as $w) {
                            if ($w->judoka_wit_id === $judoka->id) {
                                $wp += $w->winnaar_id === $judoka->id ? 2 : 0;
                                $jp += (int) preg_replace('/[^0-9]/', '', $w->score_wit ?? '');
                            } elseif ($w->judoka_blauw_id === $judoka->id) {
                                $wp += $w->winnaar_id === $judoka->id ? 2 : 0;
                                $jp += (int) preg_replace('/[^0-9]/', '', $w->score_blauw ?? '');
                            }
                        }
                    }

                    return ['judoka' => $judoka, 'wp' => (int) $wp, 'jp' => (int) $jp];
                });

                $wedstrijden = $poule->wedstrijden;
                $poule->standings = $standings->sort(function ($a, $b) use ($wedstrijden) {
                    $wpA = (int) $a['wp'];
                    $wpB = (int) $b['wp'];
                    if ($wpA !== $wpB) return $wpB - $wpA;
                    $jpA = (int) $a['jp'];
                    $jpB = (int) $b['jp'];
                    if ($jpA !== $jpB) return $jpB - $jpA;
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
                $poule->has_barrage = $barrage !== null;
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

        // Poules per blok/mat voor "Oproepen" tab (alleen doorgestuurde poules)
        $blokken = $toernooi->blokken()
            ->with(['poules' => function ($q) {
                $q->with(['mat', 'judokas.club'])
                    ->whereNotNull('mat_id')
                    ->whereNotNull('doorgestuurd_op')
                    ->orderBy('mat_id')
                    ->orderBy('nummer');
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
     * Save spreker notities (device-bound PWA)
     */
    public function sprekerNotitiesSave(Request $request): \Illuminate\Http\JsonResponse
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        $validated = $request->validate([
            'notities' => 'nullable|string|max:10000',
        ]);

        $toernooi->update(['spreker_notities' => $validated['notities']]);

        return response()->json([
            'success' => true,
            'message' => 'Notities opgeslagen',
        ]);
    }

    /**
     * Get spreker notities (device-bound PWA)
     */
    public function sprekerNotitiesGet(Request $request): \Illuminate\Http\JsonResponse
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        return response()->json([
            'success' => true,
            'notities' => $toernooi->spreker_notities ?? '',
        ]);
    }

    /**
     * Mark poule as announced (device-bound PWA)
     */
    public function sprekerAfgeroepen(Request $request): \Illuminate\Http\JsonResponse
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        $validated = $request->validate([
            'poule_id' => 'required|integer',
        ]);

        $poule = $toernooi->poules()->find($validated['poule_id']);
        if (!$poule) {
            return response()->json(['success' => false, 'message' => 'Poule niet gevonden'], 404);
        }

        $poule->update(['afgeroepen_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Poule gemarkeerd als afgeroepen',
        ]);
    }

    /**
     * Undo announced poule (device-bound PWA)
     */
    public function sprekerTerug(Request $request): \Illuminate\Http\JsonResponse
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        $validated = $request->validate([
            'poule_id' => 'required|integer',
        ]);

        $poule = $toernooi->poules()->find($validated['poule_id']);
        if (!$poule) {
            return response()->json(['success' => false, 'message' => 'Poule niet gevonden'], 404);
        }

        $poule->update(['afgeroepen_at' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Poule teruggezet',
        ]);
    }

    /**
     * Get poule standings for speaker interface (view previously announced)
     */
    public function sprekerStandings(Request $request): \Illuminate\Http\JsonResponse
    {
        $toegang = $request->get('device_toegang');
        $toernooi = $toegang->toernooi;

        $validated = $request->validate([
            'poule_id' => 'required|integer',
        ]);

        $poule = $toernooi->poules()->with(['judokas.club', 'wedstrijden'])->find($validated['poule_id']);
        if (!$poule) {
            return response()->json(['success' => false, 'message' => 'Poule niet gevonden'], 404);
        }

        $isEliminatie = $poule->type === 'eliminatie';

        if ($isEliminatie) {
            $standings = $this->getEliminatieStandings($poule);
        } else {
            $standings = $this->berekenPouleStand($poule);
        }

        return response()->json([
            'success' => true,
            'poule' => [
                'id' => $poule->id,
                'nummer' => $poule->nummer,
                'leeftijdsklasse' => $poule->leeftijdsklasse,
                'gewichtsklasse' => $poule->gewichtsklasse,
                'type' => $poule->type,
                'is_eliminatie' => $isEliminatie,
            ],
            'standings' => $standings->map(fn($s) => [
                'naam' => $s['judoka']->naam,
                'club' => $s['judoka']->club?->naam ?? '-',
                'wp' => $s['wp'],
                'jp' => $s['jp'],
                'plaats' => $s['plaats'] ?? null,
            ])->toArray(),
        ]);
    }

    /**
     * Calculate poule standings (WP/JP sorted)
     */
    private function berekenPouleStand($poule): \Illuminate\Support\Collection
    {
        $standings = $poule->judokas->map(function ($judoka) use ($poule) {
            $wp = 0;
            $jp = 0;

            foreach ($poule->wedstrijden as $wedstrijd) {
                if ($wedstrijd->judoka_wit_id === $judoka->id) {
                    $wp += $wedstrijd->winnaar_id === $judoka->id ? 2 : 0;
                    $jp += (int) preg_replace('/[^0-9]/', '', $wedstrijd->score_wit ?? '');
                } elseif ($wedstrijd->judoka_blauw_id === $judoka->id) {
                    $wp += $wedstrijd->winnaar_id === $judoka->id ? 2 : 0;
                    $jp += (int) preg_replace('/[^0-9]/', '', $wedstrijd->score_blauw ?? '');
                }
            }

            return [
                'judoka' => $judoka,
                'wp' => (int) $wp,
                'jp' => (int) $jp,
            ];
        });

        $wedstrijden = $poule->wedstrijden;
        return $standings->sort(function ($a, $b) use ($wedstrijden) {
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

        if (!$toernooiId) {
            abort(response()->view('errors.vrijwilliger', [
                'message' => 'Je sessie is verlopen. Vraag een nieuwe link bij de jurytafel.',
            ], 403));
        }

        $toernooi = Toernooi::find($toernooiId);

        if (!$toernooi) {
            abort(response()->view('errors.vrijwilliger', [
                'message' => 'Het toernooi kon niet worden gevonden. Vraag een nieuwe link bij de jurytafel.',
            ], 404));
        }

        return $toernooi;
    }

    /**
     * Check if user has correct role
     */
    private function checkRole(Request $request, string $expectedRole): void
    {
        $rol = $request->session()->get('rol_type');
        if ($rol !== $expectedRole) {
            abort(response()->view('errors.vrijwilliger', [
                'message' => 'Je hebt geen toegang tot deze functie. Vraag de juiste link bij de jurytafel.',
            ], 403));
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
