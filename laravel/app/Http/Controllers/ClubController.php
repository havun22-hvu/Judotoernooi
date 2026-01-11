<?php

namespace App\Http\Controllers;

use App\Mail\ClubUitnodigingMail;
use App\Models\Club;
use App\Models\ClubUitnodiging;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\Toernooi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ClubController extends Controller
{
    public function index(Toernooi $toernooi): View
    {
        $clubs = Club::withCount(['judokas' => function ($query) use ($toernooi) {
            $query->where('toernooi_id', $toernooi->id);
        }])
        ->with(['coaches' => function ($query) use ($toernooi) {
            $query->where('toernooi_id', $toernooi->id);
        }])
        ->with(['coachKaarten' => function ($query) use ($toernooi) {
            $query->where('toernooi_id', $toernooi->id);
        }])
        ->orderBy('naam')
        ->get();

        $uitnodigingen = $toernooi->clubUitnodigingen()
            ->with('club')
            ->get()
            ->keyBy('club_id');

        return view('pages.club.index', compact('toernooi', 'clubs', 'uitnodigingen'));
    }

    public function store(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'contact_naam' => 'nullable|string|max:255',
            'telefoon' => 'nullable|string|max:20',
            'plaats' => 'nullable|string|max:255',
        ]);

        $club = Club::create($validated);

        // Auto-create coach and coach card for new club
        $coach = Coach::create([
            'club_id' => $club->id,
            'toernooi_id' => $toernooi->id,
            'naam' => 'Coach ' . $club->naam,
        ]);

        CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', 'Club toegevoegd met coach portal (PIN: ' . $coach->pincode . ')');
    }

    public function update(Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'contact_naam' => 'nullable|string|max:255',
            'telefoon' => 'nullable|string|max:20',
            'plaats' => 'nullable|string|max:255',
        ]);

        $club->update($validated);

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', 'Club bijgewerkt');
    }

    public function destroy(Toernooi $toernooi, Club $club): RedirectResponse
    {
        // Check if club has judokas in this tournament
        if ($club->judokas()->where('toernooi_id', $toernooi->id)->exists()) {
            return redirect()
                ->route('toernooi.club.index', $toernooi)
                ->with('error', 'Kan club niet verwijderen: er zijn nog judoka\'s gekoppeld');
        }

        $club->delete();

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', 'Club verwijderd');
    }

    public function verstuurUitnodiging(Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        if (!$club->email) {
            return redirect()
                ->route('toernooi.club.index', $toernooi)
                ->with('error', 'Club heeft geen emailadres');
        }

        // Create or get invitation
        $uitnodiging = ClubUitnodiging::firstOrCreate(
            ['toernooi_id' => $toernooi->id, 'club_id' => $club->id],
            ['uitgenodigd_op' => now()]
        );

        // Update sent time
        $uitnodiging->update(['uitgenodigd_op' => now()]);

        // Send email
        $recipients = array_filter([$club->email, $club->email2]);
        Mail::to($recipients)->send(new ClubUitnodigingMail($uitnodiging));

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', "Uitnodiging verstuurd naar {$club->email}");
    }

    public function verstuurAlleUitnodigingen(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $clubs = Club::whereNotNull('email')->get();
        $verzonden = 0;
        $fouten = 0;

        foreach ($clubs as $club) {
            try {
                $uitnodiging = ClubUitnodiging::firstOrCreate(
                    ['toernooi_id' => $toernooi->id, 'club_id' => $club->id],
                    ['uitgenodigd_op' => now()]
                );

                $uitnodiging->update(['uitgenodigd_op' => now()]);

                $recipients = array_filter([$club->email, $club->email2]);
                Mail::to($recipients)->send(new ClubUitnodigingMail($uitnodiging));

                $verzonden++;
            } catch (\Exception $e) {
                $fouten++;
            }
        }

        $message = "{$verzonden} uitnodigingen verstuurd";
        if ($fouten > 0) {
            $message .= " ({$fouten} mislukt)";
        }

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', $message);
    }

    /**
     * Get coach portal URL for a club (for manual sharing)
     */
    public function getCoachUrl(Toernooi $toernooi, Club $club): RedirectResponse
    {
        $uitnodiging = ClubUitnodiging::firstOrCreate(
            ['toernooi_id' => $toernooi->id, 'club_id' => $club->id],
            ['uitgenodigd_op' => now()]
        );

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('coach_url', route('coach.portal', $uitnodiging->token))
            ->with('coach_url_club', $club->naam);
    }

    /**
     * Store a new coach for a club
     */
    public function storeCoach(Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        // Check max 3 coaches per club
        $existingCount = Coach::where('club_id', $club->id)
            ->where('toernooi_id', $toernooi->id)
            ->count();

        if ($existingCount >= 3) {
            return redirect()
                ->route('toernooi.club.index', $toernooi)
                ->with('error', 'Maximum 3 coaches per club bereikt');
        }

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefoon' => 'nullable|string|max:20',
        ]);

        $coach = Coach::create([
            'club_id' => $club->id,
            'toernooi_id' => $toernooi->id,
            'naam' => $validated['naam'],
            'email' => $validated['email'] ?? null,
            'telefoon' => $validated['telefoon'] ?? null,
        ]);

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', "Coach {$coach->naam} toegevoegd (PIN: {$coach->pincode})")
            ->with('new_coach_id', $coach->id)
            ->with('new_coach_pin', $coach->pincode)
            ->with('new_coach_url', $coach->getPortalUrl());
    }

    /**
     * Update a coach
     */
    public function updateCoach(Request $request, Toernooi $toernooi, Coach $coach): RedirectResponse
    {
        if ($coach->toernooi_id !== $toernooi->id) {
            abort(403);
        }

        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefoon' => 'nullable|string|max:20',
        ]);

        $coach->update($validated);

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', 'Coach bijgewerkt');
    }

    /**
     * Delete a coach
     */
    public function destroyCoach(Request $request, Toernooi $toernooi, Coach $coach): RedirectResponse
    {
        if ($coach->toernooi_id !== $toernooi->id) {
            abort(403);
        }

        $naam = $coach->naam;
        $coach->delete();

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', "Coach {$naam} verwijderd");
    }

    /**
     * Regenerate pincode for a coach
     */
    public function regeneratePincode(Request $request, Toernooi $toernooi, Coach $coach): RedirectResponse
    {
        if ($coach->toernooi_id !== $toernooi->id) {
            abort(403);
        }

        $newPin = $coach->regeneratePincode();

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', "Nieuwe PIN voor {$coach->naam}: {$newPin}")
            ->with('new_coach_id', $coach->id)
            ->with('new_coach_pin', $newPin)
            ->with('new_coach_url', $coach->getPortalUrl());
    }

    /**
     * Add extra coach card for a club
     */
    public function addCoachKaart(Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', 'Extra coachkaart toegevoegd voor ' . $club->naam);
    }

    /**
     * Remove a coach card from a club (only if not yet activated)
     */
    public function removeCoachKaart(Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        // Find an unactivated card to remove (no naam, no foto, no device binding)
        $kaart = CoachKaart::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->whereNull('naam')
            ->whereNull('foto_path')
            ->whereNull('device_token')
            ->first();

        if (!$kaart) {
            return redirect()
                ->route('toernooi.club.index', $toernooi)
                ->with('error', 'Geen ongebruikte coachkaart om te verwijderen');
        }

        // Keep at least 1 card
        $totaal = CoachKaart::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->count();

        if ($totaal <= 1) {
            return redirect()
                ->route('toernooi.club.index', $toernooi)
                ->with('error', 'Minimaal 1 coachkaart vereist');
        }

        $kaart->delete();

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', 'Coachkaart verwijderd voor ' . $club->naam);
    }
}
