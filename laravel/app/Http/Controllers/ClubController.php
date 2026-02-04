<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClubRequest;
use App\Mail\ClubUitnodigingMail;
use App\Models\Club;
use App\Models\ClubUitnodiging;
use App\Models\Coach;
use App\Models\CoachKaart;
use App\Models\EmailLog;
use App\Models\Organisator;
use App\Models\Toernooi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ClubController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Organisator Level - Club Management (persists across toernooien)
    |--------------------------------------------------------------------------
    */

    /**
     * List all clubs for this organisator
     */
    public function indexOrganisator(Organisator $organisator): View
    {
        $loggedIn = auth('organisator')->user();

        // Verify access
        if ($loggedIn->id !== $organisator->id && !$loggedIn->isSitebeheerder()) {
            abort(403);
        }

        $clubs = Club::where('organisator_id', $organisator->id)
            ->withCount('judokas')
            ->orderBy('naam')
            ->get();

        return view('organisator.clubs.index', compact('organisator', 'clubs'));
    }

    /**
     * Store a new club for this organisator
     */
    public function storeOrganisator(ClubRequest $request, Organisator $organisator): RedirectResponse
    {
        $loggedIn = auth('organisator')->user();

        if ($loggedIn->id !== $organisator->id && !$loggedIn->isSitebeheerder()) {
            abort(403);
        }

        $validated = $request->validated();

        $validated['organisator_id'] = $organisator->id;

        $club = Club::create($validated);

        $params = ['organisator' => $organisator];
        if ($request->input('back')) {
            $params['back'] = $request->input('back');
        }

        return redirect()
            ->route('organisator.clubs.index', $params)
            ->with('success', "Club '{$club->naam}' toegevoegd");
    }

    /**
     * Update a club
     */
    public function updateOrganisator(ClubRequest $request, Organisator $organisator, Club $club): RedirectResponse
    {
        $loggedIn = auth('organisator')->user();

        if ($loggedIn->id !== $organisator->id && !$loggedIn->isSitebeheerder()) {
            abort(403);
        }

        // Verify club belongs to organisator
        if ($club->organisator_id !== $organisator->id) {
            abort(403);
        }

        $validated = $request->validated();

        $club->update($validated);

        $params = ['organisator' => $organisator];
        if ($request->input('back')) {
            $params['back'] = $request->input('back');
        }

        return redirect()
            ->route('organisator.clubs.index', $params)
            ->with('success', 'Club bijgewerkt');
    }

    /**
     * Delete a club
     */
    public function destroyOrganisator(Organisator $organisator, Club $club): RedirectResponse
    {
        $loggedIn = auth('organisator')->user();

        if ($loggedIn->id !== $organisator->id && !$loggedIn->isSitebeheerder()) {
            abort(403);
        }

        if ($club->organisator_id !== $organisator->id) {
            abort(403);
        }

        $naam = $club->naam;
        $aantalJudokas = $club->judokas()->count();

        // Delete all judokas from this club first
        if ($aantalJudokas > 0) {
            $club->judokas()->delete();
        }

        $club->delete();

        $params = ['organisator' => $organisator];
        if (request('back')) {
            $params['back'] = request('back');
        }

        return redirect()
            ->route('organisator.clubs.index', $params)
            ->with('success', "Club '{$naam}' verwijderd");
    }

    /*
    |--------------------------------------------------------------------------
    | Toernooi Level - Club Invitations & Coaches
    |--------------------------------------------------------------------------
    */

    public function index(Organisator $organisator, Toernooi $toernooi): View
    {
        // Get the organisator who owns this toernooi
        $organisator = $toernooi->organisator;

        // Load organisator's clubs with toernooi-specific counts
        $clubs = Club::where('organisator_id', $organisator->id)
            ->withCount(['judokas' => function ($query) use ($toernooi) {
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

        // Get clubs that are linked to this toernooi (uitgenodigd) with pivot data
        $uitgenodigdeClubs = $toernooi->clubs()->get()->keyBy('id');
        $uitgenodigdeClubIds = $uitgenodigdeClubs->keys()->toArray();

        $uitnodigingen = $toernooi->clubUitnodigingen()
            ->with('club')
            ->get()
            ->keyBy('club_id');

        // Bereken benodigd aantal coachkaarten per club via model methode
        $benodigdeKaarten = [];
        foreach ($clubs as $club) {
            $benodigdeKaarten[$club->id] = $club->berekenAantalCoachKaarten($toernooi);
        }

        // Ensure clubs have portal access
        $this->ensureClubsHavePortalAccess($toernooi);

        return view('pages.club.index', compact('toernooi', 'clubs', 'uitnodigingen', 'benodigdeKaarten', 'uitgenodigdeClubIds', 'uitgenodigdeClubs', 'organisator'));
    }

    /**
     * Toggle club selection for this toernooi
     */
    public function toggleClub(Organisator $organisator, Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        // Verify club belongs to this toernooi's organisator
        if ($club->organisator_id !== $toernooi->organisator_id) {
            abort(403);
        }

        $isLinked = $toernooi->clubs()->where('clubs.id', $club->id)->exists();

        if ($isLinked) {
            // Check if club has judokas - can't remove if has judokas
            if ($club->judokas()->where('toernooi_id', $toernooi->id)->exists()) {
                return redirect()
                    ->route('toernooi.club.index', $toernooi->routeParams())
                    ->with('error', "Kan {$club->naam} niet verwijderen: er zijn nog judoka's ingeschreven");
            }
            $toernooi->clubs()->detach($club->id);
            $message = "{$club->naam} verwijderd uit dit toernooi";
        } else {
            // Add club to toernooi with portal credentials
            $toernooi->clubs()->attach($club->id, [
                'portal_code' => $club->portal_code,
                'pincode' => $club->pincode,
            ]);
            $message = "{$club->naam} toegevoegd aan dit toernooi";
        }

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', $message);
    }

    /**
     * Select all clubs for this toernooi
     */
    public function selectAllClubs(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        $clubs = Club::where('organisator_id', $toernooi->organisator_id)->get();
        $added = 0;

        foreach ($clubs as $club) {
            if (!$toernooi->clubs()->where('clubs.id', $club->id)->exists()) {
                $toernooi->clubs()->attach($club->id, [
                    'portal_code' => $club->portal_code,
                    'pincode' => $club->pincode,
                ]);
                $added++;
            }
        }

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', "{$added} clubs toegevoegd");
    }

    /**
     * Deselect all clubs for this toernooi (only those without judokas)
     */
    public function deselectAllClubs(Organisator $organisator, Toernooi $toernooi): RedirectResponse
    {
        $removed = 0;
        $skipped = 0;

        foreach ($toernooi->clubs as $club) {
            if ($club->judokas()->where('toernooi_id', $toernooi->id)->exists()) {
                $skipped++;
            } else {
                $toernooi->clubs()->detach($club->id);
                $removed++;
            }
        }

        $message = "{$removed} clubs verwijderd";
        if ($skipped > 0) {
            $message .= " ({$skipped} overgeslagen wegens judoka's)";
        }

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', $message);
    }

    public function store(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'contact_naam' => 'nullable|string|max:255',
            'telefoon' => 'nullable|string|max:20',
            'plaats' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
        ]);

        // Club krijgt automatisch portal_code en pincode via model boot
        $club = Club::create($validated);

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', 'Club toegevoegd (PIN: ' . $club->pincode . ')');
    }

    public function update(Organisator $organisator, Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'contact_naam' => 'nullable|string|max:255',
            'telefoon' => 'nullable|string|max:20',
            'plaats' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
        ]);

        $club->update($validated);

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', 'Club bijgewerkt');
    }

    public function destroy(Organisator $organisator, Toernooi $toernooi, Club $club): RedirectResponse
    {
        // Check if club has judokas in this tournament
        if ($club->judokas()->where('toernooi_id', $toernooi->id)->exists()) {
            return redirect()
                ->route('toernooi.club.index', $toernooi->routeParams())
                ->with('error', 'Kan club niet verwijderen: er zijn nog judoka\'s gekoppeld');
        }

        $club->delete();

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', 'Club verwijderd');
    }

    public function verstuurUitnodiging(Organisator $organisator, Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        if (!$club->email) {
            return redirect()
                ->route('toernooi.club.index', $toernooi->routeParams())
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
        $subject = "Uitnodiging {$toernooi->naam}";

        try {
            Mail::to($recipients)->send(new ClubUitnodigingMail($uitnodiging));

            EmailLog::logSent(
                $toernooi->id,
                'uitnodiging',
                $recipients,
                $subject,
                "Uitnodiging voor {$club->naam}",
                $club->id
            );
        } catch (\Exception $e) {
            EmailLog::logFailed(
                $toernooi->id,
                'uitnodiging',
                $recipients,
                $subject,
                $e->getMessage(),
                $club->id
            );

            return redirect()
                ->route('toernooi.club.index', $toernooi->routeParams())
                ->with('error', "Uitnodiging versturen mislukt: {$e->getMessage()}");
        }

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', "Uitnodiging verstuurd naar {$club->email}");
    }

    public function verstuurAlleUitnodigingen(Organisator $organisator, Request $request, Toernooi $toernooi): RedirectResponse
    {
        // Only send to clubs that are linked to this toernooi AND have email
        $clubs = $toernooi->clubs()->whereNotNull('email')->get();

        if ($clubs->isEmpty()) {
            return redirect()
                ->route('toernooi.club.index', $toernooi->routeParams())
                ->with('error', 'Geen clubs geselecteerd met emailadres');
        }

        $verzonden = 0;
        $fouten = 0;

        $subject = "Uitnodiging {$toernooi->naam}";

        foreach ($clubs as $club) {
            $recipients = array_filter([$club->email, $club->email2]);

            try {
                $uitnodiging = ClubUitnodiging::firstOrCreate(
                    ['toernooi_id' => $toernooi->id, 'club_id' => $club->id],
                    ['uitgenodigd_op' => now()]
                );

                $uitnodiging->update(['uitgenodigd_op' => now()]);

                Mail::to($recipients)->send(new ClubUitnodigingMail($uitnodiging));

                EmailLog::logSent(
                    $toernooi->id,
                    'uitnodiging',
                    $recipients,
                    $subject,
                    "Uitnodiging voor {$club->naam}",
                    $club->id
                );

                $verzonden++;
            } catch (\Exception $e) {
                EmailLog::logFailed(
                    $toernooi->id,
                    'uitnodiging',
                    $recipients,
                    $subject,
                    $e->getMessage(),
                    $club->id
                );

                $fouten++;
            }
        }

        $message = "{$verzonden} uitnodigingen verstuurd";
        if ($fouten > 0) {
            $message .= " ({$fouten} mislukt)";
        }

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', $message);
    }

    /**
     * Get coach portal URL for a club (for manual sharing)
     */
    public function getCoachUrl(Organisator $organisator, Toernooi $toernooi, Club $club): RedirectResponse
    {
        $uitnodiging = ClubUitnodiging::firstOrCreate(
            ['toernooi_id' => $toernooi->id, 'club_id' => $club->id],
            ['uitgenodigd_op' => now()]
        );

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('coach_url', route('coach.portal', $uitnodiging->token))
            ->with('coach_url_club', $club->naam);
    }

    /**
     * Store a new coach for a club
     */
    public function storeCoach(Organisator $organisator, Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        // Check max 3 coaches per club
        $existingCount = Coach::where('club_id', $club->id)
            ->where('toernooi_id', $toernooi->id)
            ->count();

        if ($existingCount >= 3) {
            return redirect()
                ->route('toernooi.club.index', $toernooi->routeParams())
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
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', "Coach {$coach->naam} toegevoegd (PIN: {$coach->pincode})")
            ->with('new_coach_id', $coach->id)
            ->with('new_coach_pin', $coach->pincode)
            ->with('new_coach_url', $coach->club->getPortalUrl($toernooi));
    }

    /**
     * Update a coach
     */
    public function updateCoach(Organisator $organisator, Request $request, Toernooi $toernooi, Coach $coach): RedirectResponse
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
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', 'Coach bijgewerkt');
    }

    /**
     * Delete a coach
     */
    public function destroyCoach(Organisator $organisator, Request $request, Toernooi $toernooi, Coach $coach): RedirectResponse
    {
        if ($coach->toernooi_id !== $toernooi->id) {
            abort(403);
        }

        $naam = $coach->naam;
        $coach->delete();

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', "Coach {$naam} verwijderd");
    }

    /**
     * Regenerate pincode for a coach
     */
    public function regeneratePincode(Organisator $organisator, Request $request, Toernooi $toernooi, Coach $coach): RedirectResponse
    {
        if ($coach->toernooi_id !== $toernooi->id) {
            abort(403);
        }

        $newPin = $coach->regeneratePincode();

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', "Nieuwe PIN voor {$coach->naam}: {$newPin}")
            ->with('new_coach_id', $coach->id)
            ->with('new_coach_pin', $newPin)
            ->with('new_coach_url', $coach->club->getPortalUrl($toernooi));
    }

    /**
     * Add extra coach card for a club
     */
    public function addCoachKaart(Organisator $organisator, Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        CoachKaart::create([
            'toernooi_id' => $toernooi->id,
            'club_id' => $club->id,
        ]);

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', 'Extra coachkaart toegevoegd voor ' . $club->naam);
    }

    /**
     * Remove a coach card from a club (only if not yet activated)
     */
    public function removeCoachKaart(Organisator $organisator, Request $request, Toernooi $toernooi, Club $club): RedirectResponse
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
                ->route('toernooi.club.index', $toernooi->routeParams())
                ->with('error', 'Geen ongebruikte coachkaart om te verwijderen');
        }

        // Keep at least 1 card
        $totaal = CoachKaart::where('toernooi_id', $toernooi->id)
            ->where('club_id', $club->id)
            ->count();

        if ($totaal <= 1) {
            return redirect()
                ->route('toernooi.club.index', $toernooi->routeParams())
                ->with('error', 'Minimaal 1 coachkaart vereist');
        }

        $kaart->delete();

        return redirect()
            ->route('toernooi.club.index', $toernooi->routeParams())
            ->with('success', 'Coachkaart verwijderd voor ' . $club->naam);
    }

    /**
     * Ensure all clubs have portal access and coachkaarten for clubs with judokas
     */
    private function ensureClubsHavePortalAccess(Toernooi $toernooi): void
    {
        $alleClubs = Club::all();

        foreach ($alleClubs as $club) {
            // Ensure club has portal_code and pincode
            if (empty($club->portal_code)) {
                $club->portal_code = Club::generatePortalCode();
                $club->save();
            }
            if (empty($club->pincode)) {
                $club->pincode = Club::generatePincode();
                $club->save();
            }

            // Only create coachkaart if club has judokas
            $heeftJudokas = $club->judokas()
                ->where('toernooi_id', $toernooi->id)
                ->exists();

            if ($heeftJudokas) {
                $hasCoachKaart = CoachKaart::where('club_id', $club->id)
                    ->where('toernooi_id', $toernooi->id)
                    ->exists();

                if (!$hasCoachKaart) {
                    CoachKaart::create([
                        'toernooi_id' => $toernooi->id,
                        'club_id' => $club->id,
                    ]);
                }
            }
        }
    }

    /**
     * Show email log for this toernooi
     */
    public function emailLog(Organisator $organisator, Toernooi $toernooi): View
    {
        $emails = EmailLog::where('toernooi_id', $toernooi->id)
            ->with('club')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('toernooi.email-log', compact('organisator', 'toernooi', 'emails'));
    }
}
