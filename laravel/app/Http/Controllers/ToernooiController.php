<?php

namespace App\Http\Controllers;

use App\Http\Requests\ToernooiRequest;
use App\Models\Toernooi;
use App\Services\ToernooiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ToernooiController extends Controller
{
    public function __construct(
        private ToernooiService $toernooiService
    ) {}

    public function index(): View
    {
        $toernooien = Toernooi::orderByDesc('datum')->paginate(10);

        return view('pages.toernooi.index', compact('toernooien'));
    }

    public function create(): View
    {
        return view('pages.toernooi.create');
    }

    public function store(ToernooiRequest $request): RedirectResponse
    {
        $toernooi = $this->toernooiService->initialiseerToernooi($request->validated());

        return redirect()
            ->route('toernooi.show', $toernooi)
            ->with('success', 'Toernooi succesvol aangemaakt');
    }

    public function show(Toernooi $toernooi): View
    {
        $statistieken = $this->toernooiService->getStatistieken($toernooi);

        return view('pages.toernooi.show', compact('toernooi', 'statistieken'));
    }

    public function edit(Toernooi $toernooi): View
    {
        return view('pages.toernooi.edit', compact('toernooi'));
    }

    public function update(ToernooiRequest $request, Toernooi $toernooi): RedirectResponse
    {
        $toernooi->update($request->validated());

        return redirect()
            ->route('toernooi.show', $toernooi)
            ->with('success', 'Toernooi bijgewerkt');
    }

    public function destroy(Toernooi $toernooi): RedirectResponse
    {
        $toernooi->delete();

        return redirect()
            ->route('toernooi.index')
            ->with('success', 'Toernooi verwijderd');
    }

    public function dashboard(): View
    {
        $toernooi = $this->toernooiService->getActiefToernooi();

        if (!$toernooi) {
            return view('pages.toernooi.geen-actief');
        }

        $statistieken = $this->toernooiService->getStatistieken($toernooi);

        return view('pages.toernooi.dashboard', compact('toernooi', 'statistieken'));
    }
}
