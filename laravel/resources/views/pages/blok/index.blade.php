@extends('layouts.app')

@section('title', 'Blokken & Matten')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Blokken & Matten Verdeling</h1>
    <div class="flex items-center space-x-3">
        <a href="{{ route('toernooi.blok.zaaloverzicht', $toernooi) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            Zaaloverzicht
        </a>
        @if($toernooi->blokken_verdeeld_op)
        <span class="text-sm text-gray-500">
            Laatst verdeeld: {{ $toernooi->blokken_verdeeld_op->format('d-m H:i') }}
        </span>
        @endif
        <form action="{{ route('toernooi.blok.genereer-verdeling', $toernooi) }}" method="POST"
              onsubmit="return confirm('Blok/Mat verdeling opnieuw genereren? Dit overschrijft de huidige verdeling.')">
            @csrf
            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                Genereer Verdeling
            </button>
        </form>
    </div>
</div>

<!-- Totaal statistieken -->
@php
    $totaalPoules = $blokken->sum(fn($b) => $b->poules->count());
    $totaalWedstrijden = collect($statistieken)->sum('totaal_wedstrijden');
    $nietVerdeeld = $toernooi->poules()->whereNull('blok_id')->count();
@endphp

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="flex flex-wrap gap-6 text-sm">
        <div><span class="font-bold text-gray-700">Blokken:</span> {{ $blokken->count() }}</div>
        <div><span class="font-bold text-gray-700">Matten:</span> {{ $toernooi->matten->count() }}</div>
        <div><span class="font-bold text-gray-700">Verdeelde poules:</span> {{ $totaalPoules }}</div>
        <div><span class="font-bold text-gray-700">Totaal wedstrijden:</span> {{ $totaalWedstrijden }}</div>
        @if($nietVerdeeld > 0)
        <div class="text-red-600"><span class="font-bold">Niet verdeeld:</span> {{ $nietVerdeeld }} poules</div>
        @endif
    </div>
</div>

@if($blokken->isEmpty())
<div class="bg-yellow-50 border border-yellow-300 rounded-lg p-6 text-center">
    <p class="text-yellow-800 mb-4">Nog geen blokken aangemaakt. Maak eerst blokken aan in de toernooi instellingen.</p>
</div>
@elseif($totaalPoules === 0)
<div class="bg-yellow-50 border border-yellow-300 rounded-lg p-6 text-center">
    <p class="text-yellow-800 mb-4">Poules zijn nog niet verdeeld over blokken. Klik op "Genereer Verdeling" om te starten.</p>
</div>
@else

<div class="flex gap-6">
<!-- Linker kolom: Blokken overzicht -->
<div class="flex-1">
<!-- Alle blokken overzicht -->
@foreach($blokken as $blok)
@php
    $blokStats = $statistieken[$blok->nummer] ?? ['totaal_wedstrijden' => 0, 'matten' => []];
    // Groepeer per leeftijdsklasse, dan per gewichtsklasse
    $categorieenInBlok = $blok->poules->groupBy('leeftijdsklasse')->map(function($poules) {
        return $poules->groupBy('gewichtsklasse')->map(function($ps) {
            return [
                'poules' => $ps->count(),
                'wedstrijden' => $ps->sum('aantal_wedstrijden'),
            ];
        })->filter(fn($data) => $data['wedstrijden'] > 0)->sortKeys();
    })->filter(fn($gewichten) => $gewichten->isNotEmpty())->sortKeys();
@endphp
<div class="bg-white rounded-lg shadow mb-6">
    <!-- Blok header -->
    <div class="bg-gray-800 text-white px-4 py-3 rounded-t-lg flex justify-between items-center">
        <div class="flex items-center gap-4">
            <h2 class="text-lg font-bold">Blok {{ $blok->nummer }}</h2>
            <span class="text-gray-300 text-sm">{{ $blok->poules->count() }} poules | {{ $blokStats['totaal_wedstrijden'] }} wedstrijden</span>
        </div>
        <div class="flex items-center gap-3">
            @if($blok->weging_gesloten)
            <span class="px-2 py-1 text-xs bg-red-500 rounded">Weging gesloten</span>
            @else
            <form action="{{ route('toernooi.blok.sluit-weging', [$toernooi, $blok]) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-2 py-1 text-xs bg-orange-500 hover:bg-orange-600 rounded"
                        onclick="return confirm('Weging sluiten voor Blok {{ $blok->nummer }}?')">
                    Sluit Weging
                </button>
            </form>
            @endif
        </div>
    </div>

    <!-- Categorieën per leeftijdsklasse -->
    <div class="p-4">
        @forelse($categorieenInBlok as $leeftijdsklasse => $gewichten)
        <div class="mb-3 last:mb-0">
            <div class="font-bold text-gray-700 mb-1">{{ $leeftijdsklasse }}</div>
            <div class="flex flex-wrap gap-2">
                @foreach($gewichten as $gewicht => $data)
                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm">
                    {{ $gewicht }} kg
                    <span class="text-blue-600 text-xs">({{ $data['wedstrijden'] }}w)</span>
                </span>
                @endforeach
            </div>
        </div>
        @empty
        <div class="text-gray-400 text-sm italic">Geen categorieën in dit blok</div>
        @endforelse
    </div>
</div>
@endforeach
</div>

<!-- Rechter kolom: Categorieën overzicht -->
<div class="w-80 flex-shrink-0">
    <div class="bg-white rounded-lg shadow sticky top-4">
        <div class="bg-gray-800 text-white px-4 py-3 rounded-t-lg">
            <h3 class="font-bold">Categorieën Overzicht</h3>
        </div>
        <div class="p-4">
            @php
                $leeftijdVolgorde = ["Mini's", 'A-pupillen', 'B-pupillen', 'Dames -15', 'Heren -15', 'C-pupillen', 'Aspiranten', 'Junioren', 'Senioren'];
                $alleCategorieen = $toernooi->poules()
                    ->select('leeftijdsklasse', 'gewichtsklasse', 'blok_id', 'aantal_wedstrijden')
                    ->with('blok:id,nummer')
                    ->get()
                    ->groupBy('leeftijdsklasse')
                    ->sortBy(fn($v, $k) => ($pos = array_search($k, $leeftijdVolgorde)) !== false ? $pos : 99);
            @endphp
            @foreach($alleCategorieen as $leeftijd => $poules)
                <div class="mb-3">
                    <div class="font-bold text-gray-700 text-sm border-b pb-1 mb-1">{{ $leeftijd }}</div>
                    @php
                        $gewichten = $poules->groupBy('gewichtsklasse')
                            ->map(fn($ps) => ['blok' => $ps->first()->blok->nummer ?? '?', 'wedstrijden' => $ps->sum('aantal_wedstrijden')])
                            ->filter(fn($data) => $data['wedstrijden'] > 0)
                            ->sortBy(fn($v, $k) => (int) preg_replace('/[^0-9]/', '', $k) + (str_starts_with($k, '+') ? 0.5 : 0));
                    @endphp
                    @foreach($gewichten as $gewicht => $data)
                        <div class="flex justify-between text-sm py-0.5">
                            <span class="text-gray-600">{{ $gewicht }} kg ({{ $data['wedstrijden'] }}w)</span>
                            <span class="font-medium text-blue-600">Blok {{ $data['blok'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>
</div>

@endif

<!-- Legenda -->
<div class="mt-6 text-sm text-gray-500">
    <span class="inline-block px-2 py-1 bg-purple-50 border border-purple-200 rounded mr-2">KF</span> = Kruisfinale
</div>
@endsection
