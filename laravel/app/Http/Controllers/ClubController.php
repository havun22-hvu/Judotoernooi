<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubUitnodiging;
use App\Models\Toernooi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClubController extends Controller
{
    public function index(Toernooi $toernooi): View
    {
        $clubs = Club::withCount(['judokas' => function ($query) use ($toernooi) {
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
            'contact_naam' => 'nullable|string|max:255',
            'plaats' => 'nullable|string|max:255',
        ]);

        Club::create($validated);

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', 'Club toegevoegd');
    }

    public function update(Request $request, Toernooi $toernooi, Club $club): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'contact_naam' => 'nullable|string|max:255',
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

        // TODO: Send actual email
        // Mail::to($club->email)->send(new ClubUitnodigingMail($uitnodiging));

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', "Uitnodiging verstuurd naar {$club->email}");
    }

    public function verstuurAlleUitnodigingen(Request $request, Toernooi $toernooi): RedirectResponse
    {
        $clubs = Club::whereNotNull('email')->get();
        $verzonden = 0;

        foreach ($clubs as $club) {
            $uitnodiging = ClubUitnodiging::firstOrCreate(
                ['toernooi_id' => $toernooi->id, 'club_id' => $club->id],
                ['uitgenodigd_op' => now()]
            );

            $uitnodiging->update(['uitgenodigd_op' => now()]);
            $verzonden++;

            // TODO: Send actual email
        }

        return redirect()
            ->route('toernooi.club.index', $toernooi)
            ->with('success', "{$verzonden} uitnodigingen verstuurd");
    }
}
