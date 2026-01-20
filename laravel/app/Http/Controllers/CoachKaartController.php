<?php

namespace App\Http\Controllers;

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
     * Show activation form
     */
    public function activeer(string $qrCode): View|RedirectResponse
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)
            ->with(['club', 'toernooi'])
            ->firstOrFail();

        // Already activated? Go to card
        if ($coachKaart->is_geactiveerd) {
            return redirect()->route('coach-kaart.show', $qrCode);
        }

        return view('pages.coach-kaart.activeer', compact('coachKaart'));
    }

    /**
     * Process activation - save name, photo and bind device
     */
    public function activeerOpslaan(Request $request, string $qrCode): RedirectResponse
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)
            ->with(['club', 'toernooi'])
            ->firstOrFail();

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'foto' => 'required|image|max:5120', // Max 5MB
        ]);

        // Store the photo
        $path = $request->file('foto')->store('coach-fotos', 'public');

        // Generate device token and bind
        $deviceToken = CoachKaart::generateDeviceToken();
        $deviceInfo = $this->getDeviceInfo($request->userAgent());

        $coachKaart->update([
            'naam' => $validated['naam'],
            'foto' => $path,
            'is_geactiveerd' => true,
            'geactiveerd_op' => now(),
            'device_token' => $deviceToken,
            'device_info' => $deviceInfo,
            'gebonden_op' => now(),
        ]);

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
            ->with('success', 'Coach kaart geactiveerd!')
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
     */
    public function scan(string $qrCode): View
    {
        $coachKaart = CoachKaart::where('qr_code', $qrCode)
            ->with(['club', 'toernooi'])
            ->firstOrFail();

        // Check if card is valid (activated with photo)
        $isGeldig = $coachKaart->isGeldig();

        $wasAlreadyScanned = $coachKaart->is_gescand;

        if ($isGeldig && !$wasAlreadyScanned) {
            $coachKaart->markeerGescand();
        }

        return view('pages.coach-kaart.scan-result', compact('coachKaart', 'wasAlreadyScanned', 'isGeldig'));
    }

    /**
     * Generate coach cards for all clubs in a tournament
     */
    public function genereer(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $clubs = Club::withCount(['judokas' => fn($q) => $q->where('toernooi_id', $toernooi->id)])
            ->whereHas('judokas', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->get();

        $aangemaakt = 0;

        foreach ($clubs as $club) {
            $benodigdAantal = $club->berekenAantalCoachKaarten($toernooi);
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
                $club->coachKaartenVoorToernooi($toernooi->id)
                    ->where('is_gescand', false)
                    ->orderBy('id', 'desc')
                    ->limit($excess)
                    ->delete();
            }
        }

        return redirect()->back()
            ->with('success', "{$aangemaakt} coach kaarten aangemaakt");
    }

    /**
     * List all coach cards for a tournament (admin view)
     */
    public function index(Toernooi $toernooi): View
    {
        $clubs = Club::withCount(['judokas' => fn($q) => $q->where('toernooi_id', $toernooi->id)])
            ->with(['coachKaarten' => fn($q) => $q->where('toernooi_id', $toernooi->id)])
            ->whereHas('judokas', fn($q) => $q->where('toernooi_id', $toernooi->id))
            ->orderBy('naam')
            ->get();

        return view('pages.coach-kaart.index', compact('toernooi', 'clubs'));
    }
}
