<?php

namespace App\Http\Controllers;

use App\Models\Organisator;
use App\Models\Club;
use App\Models\CoachKaart;
use App\Models\Toernooi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CoachKaartController extends Controller
{
    /**
     * Show a coach card - redirects to activation if not activated
     */
    public function show(Request $request, string $qrCode): View|RedirectResponse
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)
            ->with(['club', 'toernooi'])
            ->firstOrFail();

        // Not activated yet? Redirect to activation
        if (!$coachKaart->is_geactiveerd) {
            return redirect()->route('coach-kaart.activeer', $qrCode);
        }

        $aantalJudokas = $coachKaart->club->judokas()
            ->where('toernooi_id', $coachKaart->toernooi_id)
            ->count();

        $totaalKaarten = CoachKaart::where('toernooi_id', $coachKaart->toernooi_id)
            ->where('club_id', $coachKaart->club_id)
            ->count();

        $kaartNummer = CoachKaart::where('toernooi_id', $coachKaart->toernooi_id)
            ->where('club_id', $coachKaart->club_id)
            ->where('id', '<=', $coachKaart->id)
            ->count();

        // Check device binding - QR only visible on bound device
        $deviceToken = $request->cookie('coach_kaart_' . $coachKaart->id);
        $isCorrectDevice = $coachKaart->isDeviceGebonden() && $deviceToken === $coachKaart->device_token;

        return view('pages.coach-kaart.show', compact(
            'coachKaart',
            'aantalJudokas',
            'totaalKaarten',
            'kaartNummer',
            'isCorrectDevice'
        ));
    }

    /**
     * Show activation form (or takeover form if already activated on another device)
     */
    public function activeer(Request $request, string $qrCode): View|RedirectResponse
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)
            ->with(['club', 'toernooi'])
            ->firstOrFail();

        // Check if this is the correct device
        $deviceToken = $request->cookie('coach_kaart_' . $coachKaart->id);
        $isCorrectDevice = $coachKaart->isDeviceGebonden() && $deviceToken === $coachKaart->device_token;

        // Already activated on THIS device? Go to card
        if ($coachKaart->is_geactiveerd && $isCorrectDevice) {
            return redirect()->route('coach-kaart.show', $qrCode);
        }

        // Determine if this is a takeover (already activated on different device)
        $isOvername = $coachKaart->is_geactiveerd && !$isCorrectDevice;

        return view('pages.coach-kaart.activeer', compact('coachKaart', 'isOvername'));
    }

    /**
     * Process activation or takeover - save name, photo and bind device
     */
    public function activeerOpslaan(Request $request, string $qrCode): RedirectResponse
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)
            ->with(['club', 'toernooi'])
            ->firstOrFail();

        // Check if takeover is blocked (coach still checked in)
        if ($coachKaart->is_geactiveerd && !$coachKaart->kanOverdragen()) {
            return redirect()->back()
                ->withErrors(['overdracht' => 'Overdracht niet mogelijk. Huidige coach moet eerst uitchecken bij de dojo scanner.']);
        }

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'foto' => 'required|image|max:5120', // Max 5MB
            'pincode' => 'required|string|size:4',
        ]);

        // Verify pincode
        if ($validated['pincode'] !== $coachKaart->pincode) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['pincode' => 'Onjuiste pincode']);
        }

        // Store the photo
        $path = $request->file('foto')->store('coach-fotos', 'public');

        // Generate device token
        $deviceToken = CoachKaart::generateDeviceToken();
        $deviceInfo = $this->getDeviceInfo($request->userAgent());

        // Check if this is a takeover or first activation
        $isOvername = $coachKaart->is_geactiveerd;

        if ($isOvername) {
            // Takeover: use overdragen() which logs the transfer
            $coachKaart->overdragen($validated['naam'], $path, $deviceToken, $deviceInfo);
            $message = 'Coach kaart overgenomen!';
        } else {
            // First activation
            $coachKaart->activeer($validated['naam'], $path, $deviceToken, $deviceInfo);
            $message = 'Coach kaart geactiveerd!';
        }

        // Set cookie (1 year expiry)
        $cookie = Cookie::make(
            'coach_kaart_' . $coachKaart->id,
            $deviceToken,
            60 * 24 * 365, // 1 year
            '/',
            null,
            true, // secure
            true  // httpOnly
        );

        return redirect()->route('coach-kaart.show', $qrCode)
            ->with('success', $message)
            ->withCookie($cookie);
    }

    /**
     * Parse user agent to get device info.
     */
    protected function getDeviceInfo(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Onbekend device';
        }

        $device = 'Onbekend';
        $browser = 'Onbekend';

        if (str_contains($userAgent, 'iPhone')) {
            $device = 'iPhone';
        } elseif (str_contains($userAgent, 'iPad')) {
            $device = 'iPad';
        } elseif (str_contains($userAgent, 'Android')) {
            $device = 'Android';
        } elseif (str_contains($userAgent, 'Windows')) {
            $device = 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            $device = 'Mac';
        }

        if (str_contains($userAgent, 'Chrome') && !str_contains($userAgent, 'Edg')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Safari') && !str_contains($userAgent, 'Chrome')) {
            $browser = 'Safari';
        } elseif (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Edg')) {
            $browser = 'Edge';
        }

        return "{$device} {$browser}";
    }

    /**
     * Scan endpoint - validates access and marks as scanned
     * Now includes time-based token validation to prevent screenshot fraud
     */
    public function scan(Request $request, string $qrCode): View
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)
            ->with(['club', 'toernooi', 'wisselingen'])
            ->firstOrFail();

        // Validate time-based token (if provided)
        $timestamp = $request->query('t');
        $signature = $request->query('s');
        $tokenExpired = false;

        if ($timestamp && $signature) {
            // Token provided - validate it
            $tokenExpired = !$coachKaart->validateScanToken((int) $timestamp, $signature, 5);
        } else {
            // No token = old QR or direct URL access = treat as expired
            // This catches screenshots of old QR codes without tokens
            $tokenExpired = true;
        }

        // Check if card is valid (activated with photo)
        $isGeldig = $coachKaart->isGeldig() && !$tokenExpired;

        $wasAlreadyScanned = $coachKaart->is_gescand;

        if ($isGeldig && !$wasAlreadyScanned) {
            $coachKaart->markeerGescand();
        }

        // Get transfer history for display
        $wisselingen = $coachKaart->wisselingen;

        return view('pages.coach-kaart.scan-result', compact('coachKaart', 'wasAlreadyScanned', 'isGeldig', 'wisselingen', 'tokenExpired'));
    }

    /**
     * Generate coach cards for all clubs in a tournament
     *
     * Called by organisator after "Einde Voorbereiding" to calculate
     * the correct number of coach cards based on largest block per club.
     *
     * Formula: ceil(max_judokas_in_largest_block / judokas_per_coach)
     * Example: 11 judokas in largest block, 5 per coach = ceil(11/5) = 3 cards
     */
    public function genereer(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $clubs = Club::withCount(['judokas' => fn($q) => $q->where('toernooi_id', $toernooi->id)])
            ->whereHas('judokas', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->get();

        $aangemaakt = 0;
        $verwijderd = 0;

        foreach ($clubs as $club) {
            // forceCalculate=true: calculate based on largest block (after voorbereiding)
            $benodigdAantal = $club->berekenAantalCoachKaarten($toernooi, true);
            $huidigAantal = $club->coachKaartenVoorToernooi($toernooi->id)->count();

            // Create missing cards
            for ($i = $huidigAantal; $i < $benodigdAantal; $i++) {
                CoachKaart::create([
                    'toernooi_id' => $toernooi->id,
                    'club_id' => $club->id,
                ]);
                $aangemaakt++;
            }

            // Remove excess cards (only unscanned ones)
            if ($huidigAantal > $benodigdAantal) {
                $excess = $huidigAantal - $benodigdAantal;
                $deleted = $club->coachKaartenVoorToernooi($toernooi->id)
                    ->where('is_gescand', false)
                    ->orderBy('id', 'desc')
                    ->limit($excess)
                    ->delete();
                $verwijderd += $deleted;
            }
        }

        // Mark voorbereiding as done (if not already)
        if (!$toernooi->voorbereiding_klaar_op) {
            $toernooi->update(['voorbereiding_klaar_op' => now()]);
        }

        $message = "{$aangemaakt} coach kaarten aangemaakt";
        if ($verwijderd > 0) {
            $message .= ", {$verwijderd} verwijderd";
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Check coach in at dojo scanner
     */
    public function checkin(string $qrCode): RedirectResponse
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)->firstOrFail();

        if (!$coachKaart->toernooi?->coach_incheck_actief) {
            return redirect()->back()->with('info', 'Check-in systeem is niet actief voor dit toernooi');
        }

        $coachKaart->checkin();

        return redirect()->route('coach-kaart.scan', $qrCode)
            ->with('success', 'Coach ingecheckt');
    }

    /**
     * Check coach out at dojo scanner
     */
    public function checkout(string $qrCode): RedirectResponse
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)->firstOrFail();

        if (!$coachKaart->toernooi?->coach_incheck_actief) {
            return redirect()->back()->with('info', 'Check-in systeem is niet actief voor dit toernooi');
        }

        $coachKaart->checkout();

        return redirect()->route('coach-kaart.scan', $qrCode)
            ->with('success', 'Coach uitgecheckt');
    }

    /**
     * List all coach cards for a tournament (admin view)
     */
    public function index(Organisator $organisator, Toernooi $toernooi): View
    {
        $clubs = Club::withCount(['judokas' => fn($q) => $q->where('toernooi_id', $toernooi->id)])
            ->with(['coachKaarten' => fn($q) => $q->where('toernooi_id', $toernooi->id)])
            ->whereHas('judokas', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->orderBy('naam')
            ->get();

        return view('pages.coach-kaart.index', compact('toernooi', 'clubs'));
    }

    /**
     * Geschiedenis van een coachkaart (alle coaches met check-ins)
     */
    public function geschiedenis(string $qrCode): View
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)
            ->with(['club', 'toernooi', 'wisselingen', 'checkinsVandaag'])
            ->firstOrFail();

        return view('pages.coach-kaart.geschiedenis', compact('coachKaart'));
    }

    /**
     * API: Alle clubs met coach kaarten voor dojo scanner overzicht
     */
    public function dojoClubs(Organisator $organisator, Toernooi $toernooi)
    {
        $clubs = Club::whereHas('coachKaarten', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->with(['coachKaarten' => fn($q) => $q->where('toernooi_id', $toernooi->id)])
            ->orderBy('naam')
            ->get()
            ->map(function ($club) {
                $kaarten = $club->coachKaarten;
                return [
                    'id' => $club->id,
                    'naam' => $club->naam,
                    'totaal_kaarten' => $kaarten->count(),
                    'ingecheckt' => $kaarten->filter(fn($k) => $k->isIngecheckt())->count(),
                    'uitgecheckt' => $kaarten->filter(fn($k) => !$k->isIngecheckt() && $k->is_geactiveerd)->count(),
                    'ongebruikt' => $kaarten->filter(fn($k) => !$k->is_geactiveerd)->count(),
                ];
            });

        return response()->json($clubs);
    }

    /**
     * API: Detail van een club voor dojo scanner overzicht
     */
    public function dojoClubDetail(Organisator $organisator, Toernooi $toernooi, Club $club)
    {
        $kaarten = CoachKaart::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->get()
            ->map(function ($kaart, $index) {
                $status = 'ongebruikt';
                $statusTijd = null;

                if ($kaart->isIngecheckt()) {
                    $status = 'in';
                    $statusTijd = $kaart->ingecheckt_op->format('H:i');
                } elseif ($kaart->is_geactiveerd) {
                    $status = 'uit';
                    // Zoek laatste uitcheck
                    $laatsteUit = $kaart->checkinsVandaag()->where('actie', '!=', 'in')->first();
                    $statusTijd = $laatsteUit?->created_at->format('H:i');
                }

                return [
                    'id' => $kaart->id,
                    'qr_code' => $kaart->qr_code,
                    'nummer' => $index + 1,
                    'naam' => $kaart->naam ?? '(niet geactiveerd)',
                    'status' => $status,
                    'status_tijd' => $statusTijd,
                ];
            });

        return response()->json([
            'club' => [
                'id' => $club->id,
                'naam' => $club->naam,
            ],
            'kaarten' => $kaarten,
        ]);
    }

    /**
     * Toggle coach incheck system for a tournament
     */
    public function toggleIncheck(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        $toernooi->update([
            'coach_incheck_actief' => !$toernooi->coach_incheck_actief,
        ]);

        $status = $toernooi->coach_incheck_actief ? 'geactiveerd' : 'gedeactiveerd';
        return redirect()->back()->with('success', "Coach in/uitcheck systeem {$status}");
    }

    /**
     * Force checkout a coach (hoofdjury only)
     */
    public function forceCheckout(CoachKaart $coachKaart): RedirectResponse
    {
        if (!$coachKaart->isIngecheckt()) {
            return redirect()->back()->with('info', 'Coach is niet ingecheckt');
        }

        $coachKaart->forceCheckout();

        return redirect()->back()->with('success', "Coach {$coachKaart->naam} geforceerd uitgecheckt");
    }

    /**
     * Get all currently checked-in coaches for a tournament
     */
    public function ingecheckteCoaches(Organisator $organisator, Toernooi $toernooi): View
    {
        $ingecheckteKaarten = CoachKaart::where('toernooi_id', $toernooi->id)
            ->whereNotNull('ingecheckt_op')
            ->with(['club'])
            ->orderBy('ingecheckt_op', 'desc')
            ->get();

        return view('pages.coach-kaart.ingecheckt', compact('toernooi', 'ingecheckteKaarten'));
    }
}
