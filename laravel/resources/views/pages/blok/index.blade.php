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
    </div>
</div>

<!-- Tab navigatie -->
<div class="mb-6">
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button type="button" id="tab-automatisch" onclick="switchTab('automatisch')"
                    class="tab-btn border-b-2 border-yellow-500 text-yellow-600 py-3 px-1 text-sm font-medium">
                Automatisch (OR-Tools)
            </button>
            <button type="button" id="tab-handmatig" onclick="switchTab('handmatig')"
                    class="tab-btn border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-3 px-1 text-sm font-medium">
                Handmatig
            </button>
        </nav>
    </div>
</div>

<script>
function switchTab(tab) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-yellow-500', 'text-yellow-600');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById('tab-' + tab).classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('tab-' + tab).classList.add('border-yellow-500', 'text-yellow-600');

    // Show/hide content
    document.getElementById('content-automatisch').classList.toggle('hidden', tab !== 'automatisch');
    document.getElementById('content-handmatig').classList.toggle('hidden', tab !== 'handmatig');
}
</script>

<!-- TAB: Automatisch -->
<div id="content-automatisch">

<!-- Genereer knop voor automatische verdeling -->
<div class="mb-4">
    <form id="genereerForm" action="{{ route('toernooi.blok.genereer-verdeling', $toernooi) }}" method="POST"
          onsubmit="return submitMetPrioriteiten()">
        @csrf
        <input type="hidden" name="prioriteit_spreiding" id="submit_spreiding" value="hoog">
        <input type="hidden" name="prioriteit_gewicht" id="submit_gewicht" value="hoog">
        <input type="hidden" name="prioriteit_matten" id="submit_matten" value="normaal">
        <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
            Genereer Automatische Verdeling
        </button>
    </form>
    <script>
    function submitMetPrioriteiten() {
        if (!confirm('Blok/Mat verdeling opnieuw genereren? Dit overschrijft de huidige verdeling.')) {
            return false;
        }
        document.getElementById('submit_spreiding').value = document.querySelector('#content-automatisch input[name="prioriteit_spreiding"]').value;
        document.getElementById('submit_gewicht').value = document.querySelector('#content-automatisch input[name="prioriteit_gewicht"]').value;
        document.getElementById('submit_matten').value = document.querySelector('#content-automatisch input[name="prioriteit_matten"]').value;
        return true;
    }
    </script>
</div>

<!-- Totaal statistieken -->
@php
    $totaalPoules = $blokken->sum(fn($b) => $b->poules->count());
    $totaalWedstrijden = collect($statistieken)->sum('totaal_wedstrijden');
    $nietVerdeeld = $toernooi->poules()->whereNull('blok_id')->count();
    $gemiddeldPerBlok = $blokken->count() > 0 ? round($totaalWedstrijden / $blokken->count()) : 0;
@endphp

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="flex flex-wrap gap-6 text-sm">
        <div><span class="font-bold text-gray-700">Blokken:</span> {{ $blokken->count() }}</div>
        <div><span class="font-bold text-gray-700">Matten:</span> {{ $toernooi->matten->count() }}</div>
        <div><span class="font-bold text-gray-700">Verdeelde poules:</span> {{ $totaalPoules }}</div>
        <div><span class="font-bold text-gray-700">Totaal wedstrijden:</span> {{ $totaalWedstrijden }}</div>
        <div><span class="font-bold text-gray-700">Gemiddeld per blok:</span> {{ $gemiddeldPerBlok }}</div>
        @if($nietVerdeeld > 0)
        <div class="text-red-600"><span class="font-bold">Niet verdeeld:</span> {{ $nietVerdeeld }} poules</div>
        @endif
    </div>
</div>

<!-- Verdelings prioriteiten -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h3 class="font-bold text-gray-700">Verdelings Prioriteiten</h3>
        <span class="text-xs text-gray-500">Pas aan hoe de verdeling wordt berekend</span>
    </div>
    <form id="prioriteitenForm" class="space-y-3">
        <!-- Gelijke spreiding over blokken -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-xs font-bold">1</span>
                <span class="text-sm text-gray-700">Gelijke spreiding over blokken</span>
            </div>
            <div class="flex gap-1" data-priority="spreiding">
                @foreach(['hoog' => 'Belangrijk', 'normaal' => 'Gewoon', 'laag' => 'Minder'] as $value => $label)
                <button type="button" data-value="{{ $value }}" class="priority-btn px-3 py-1 text-xs rounded border transition-colors
                    @if($prioriteiten['spreiding'] === $value)
                        @if($value === 'hoog') bg-green-100 border-green-400 text-green-700 font-bold
                        @elseif($value === 'normaal') bg-yellow-100 border-yellow-400 text-yellow-700 font-bold
                        @else bg-red-100 border-red-400 text-red-700 font-bold @endif
                    @else bg-gray-100 border-gray-300 text-gray-600 @endif">
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <input type="hidden" name="prioriteit_spreiding" value="{{ $prioriteiten['spreiding'] }}">
        </div>

        <!-- Aansluiting gewichtsklassen -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 bg-purple-100 text-purple-700 rounded-full flex items-center justify-center text-xs font-bold">2</span>
                <span class="text-sm text-gray-700">Aansluiting gewichtsklassen</span>
            </div>
            <div class="flex gap-1" data-priority="gewicht">
                @foreach(['hoog' => 'Belangrijk', 'normaal' => 'Gewoon', 'laag' => 'Minder'] as $value => $label)
                <button type="button" data-value="{{ $value }}" class="priority-btn px-3 py-1 text-xs rounded border transition-colors
                    @if($prioriteiten['gewicht'] === $value)
                        @if($value === 'hoog') bg-green-100 border-green-400 text-green-700 font-bold
                        @elseif($value === 'normaal') bg-yellow-100 border-yellow-400 text-yellow-700 font-bold
                        @else bg-red-100 border-red-400 text-red-700 font-bold @endif
                    @else bg-gray-100 border-gray-300 text-gray-600 @endif">
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <input type="hidden" name="prioriteit_gewicht" value="{{ $prioriteiten['gewicht'] }}">
        </div>

        <!-- Voorkeurs matten -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 bg-orange-100 text-orange-700 rounded-full flex items-center justify-center text-xs font-bold">3</span>
                <span class="text-sm text-gray-700">Voorkeurs matten per leeftijd</span>
            </div>
            <div class="flex gap-1" data-priority="matten">
                @foreach(['hoog' => 'Belangrijk', 'normaal' => 'Gewoon', 'laag' => 'Minder'] as $value => $label)
                <button type="button" data-value="{{ $value }}" class="priority-btn px-3 py-1 text-xs rounded border transition-colors
                    @if($prioriteiten['matten'] === $value)
                        @if($value === 'hoog') bg-green-100 border-green-400 text-green-700 font-bold
                        @elseif($value === 'normaal') bg-yellow-100 border-yellow-400 text-yellow-700 font-bold
                        @else bg-red-100 border-red-400 text-red-700 font-bold @endif
                    @else bg-gray-100 border-gray-300 text-gray-600 @endif">
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <input type="hidden" name="prioriteit_matten" value="{{ $prioriteiten['matten'] }}">
        </div>
    </form>
    <div id="save-status" class="text-xs text-gray-400 mt-2 text-right"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const priorityGroups = document.querySelectorAll('[data-priority]');
    const saveStatus = document.getElementById('save-status');

    function savePrioriteiten() {
        const data = {
            spreiding: document.querySelector('input[name="prioriteit_spreiding"]').value,
            gewicht: document.querySelector('input[name="prioriteit_gewicht"]').value,
            matten: document.querySelector('input[name="prioriteit_matten"]').value,
        };

        saveStatus.textContent = 'Opslaan...';
        saveStatus.classList.remove('text-green-600', 'text-red-600');
        saveStatus.classList.add('text-gray-400');

        fetch('{{ route('toernooi.blok.save-prioriteiten', $toernooi) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                saveStatus.textContent = 'Opgeslagen';
                saveStatus.classList.remove('text-gray-400', 'text-red-600');
                saveStatus.classList.add('text-green-600');
                setTimeout(() => { saveStatus.textContent = ''; }, 2000);
            }
        })
        .catch(error => {
            saveStatus.textContent = 'Fout bij opslaan';
            saveStatus.classList.remove('text-gray-400', 'text-green-600');
            saveStatus.classList.add('text-red-600');
        });
    }

    priorityGroups.forEach(group => {
        const priority = group.dataset.priority;
        const buttons = group.querySelectorAll('.priority-btn');
        const hiddenInput = document.querySelector(`input[name="prioriteit_${priority}"]`);

        buttons.forEach(btn => {
            btn.addEventListener('click', function() {
                const value = this.dataset.value;

                // Reset all buttons in this group
                buttons.forEach(b => {
                    b.classList.remove('bg-green-100', 'border-green-400', 'text-green-700', 'font-bold');
                    b.classList.remove('bg-yellow-100', 'border-yellow-400', 'text-yellow-700', 'font-bold');
                    b.classList.remove('bg-red-100', 'border-red-400', 'text-red-700', 'font-bold');
                    b.classList.add('bg-gray-100', 'border-gray-300', 'text-gray-600');
                    b.classList.remove('font-bold');
                });

                // Style active button
                this.classList.remove('bg-gray-100', 'border-gray-300', 'text-gray-600');
                this.classList.add('font-bold');

                if (value === 'hoog') {
                    this.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
                } else if (value === 'normaal') {
                    this.classList.add('bg-yellow-100', 'border-yellow-400', 'text-yellow-700');
                } else {
                    this.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
                }

                // Update hidden input
                hiddenInput.value = value;

                // Auto-save
                savePrioriteiten();
            });
        });
    });
});
</script>

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
    $leeftijdVolgordeBlok = ["Mini's", 'A-pupillen', 'B-pupillen', 'C-pupillen', 'Dames -15', 'Heren -15', 'Dames -18', 'Heren -18', 'Dames -21', 'Heren -21', 'Dames', 'Heren', 'Aspiranten', 'Junioren', 'Senioren'];
    // Groepeer per leeftijdsklasse, dan per gewichtsklasse
    $categorieenInBlok = $blok->poules->groupBy('leeftijdsklasse')->map(function($poules) {
        return $poules->groupBy('gewichtsklasse')->map(function($ps) {
            return [
                'poules' => $ps->count(),
                'wedstrijden' => $ps->sum('aantal_wedstrijden'),
            ];
        })->filter(fn($data) => $data['wedstrijden'] > 0)->sortKeys();
    })->filter(fn($gewichten) => $gewichten->isNotEmpty())
      ->sortBy(fn($v, $k) => ($pos = array_search($k, $leeftijdVolgordeBlok)) !== false ? $pos : 99);
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

</div><!-- /content-automatisch -->

<!-- TAB: Handmatig -->
<div id="content-handmatig" class="hidden">

@php
    $aantalBlokken = $toernooi->aantal_blokken;
    $aantalMatten = $toernooi->matten->count();
    $leeftijdVolgorde = ["Mini's", 'A-pupillen', 'B-pupillen', 'C-pupillen', 'Dames -15', 'Heren -15', 'Dames -18', 'Heren -18', 'Dames', 'Heren'];

    // Groepeer gewichtsklassen met hun wedstrijden, filter 0 wedstrijden weg
    $gewichtsklassen = $toernooi->poules()
        ->selectRaw('leeftijdsklasse, gewichtsklasse, SUM(aantal_wedstrijden) as totaal_wedstrijden, blok_id')
        ->groupBy('leeftijdsklasse', 'gewichtsklasse', 'blok_id')
        ->with('blok:id,nummer')
        ->get()
        ->groupBy(fn($p) => $p->leeftijdsklasse . ' ' . $p->gewichtsklasse)
        ->map(fn($group) => [
            'leeftijdsklasse' => $group->first()->leeftijdsklasse,
            'gewichtsklasse' => $group->first()->gewichtsklasse,
            'wedstrijden' => $group->sum('totaal_wedstrijden'),
            'blok' => $group->first()->blok->nummer ?? null,
        ])
        ->filter(fn($v) => $v['wedstrijden'] > 0)
        ->sortBy(fn($v) => (($pos = array_search($v['leeftijdsklasse'], $leeftijdVolgorde)) !== false ? $pos * 1000 : 99000) + (int)preg_replace('/[^0-9]/', '', $v['gewichtsklasse']));

    $handmatigTotaalWedstrijden = $gewichtsklassen->sum('wedstrijden');
    $handmatigGemiddeld = $aantalBlokken > 0 ? round($handmatigTotaalWedstrijden / $aantalBlokken) : 0;
@endphp

<!-- Statistieken -->
<div class="bg-white rounded-lg shadow p-4 mb-6">
    <div class="flex flex-wrap gap-6 text-sm">
        <div><span class="font-bold text-gray-700">Blokken:</span> {{ $aantalBlokken }}</div>
        <div><span class="font-bold text-gray-700">Matten:</span> {{ $aantalMatten }}</div>
        <div><span class="font-bold text-gray-700">Categorieën:</span> {{ $gewichtsklassen->count() }}</div>
        <div><span class="font-bold text-gray-700">Totaal wedstrijden:</span> {{ $handmatigTotaalWedstrijden }}</div>
        <div><span class="font-bold text-gray-700">Gemiddeld per blok:</span> {{ $handmatigGemiddeld }}</div>
    </div>
</div>

<div class="bg-white rounded-lg shadow p-4 mb-6">
    <h3 class="font-bold text-gray-700 mb-3">Stap 1: Categorieën verdelen over Blokken</h3>
    <p class="text-sm text-gray-600 mb-4">
        Wijs gewichtsklassen toe aan blokken. Na opslaan kun je per blok de poules over matten verdelen.
    </p>

    <div class="flex gap-6">
        <!-- Linker kolom: Gewichtsklassen toewijzen aan blokken -->
        <div class="flex-1">
            <div class="bg-gray-100 rounded-lg p-4">
                <div class="grid grid-cols-3 gap-2 font-bold text-sm text-gray-600 mb-2 pb-2 border-b">
                    <div>Categorie</div>
                    <div class="text-center">Wedstrijden</div>
                    <div class="text-center">Blok</div>
                </div>

                <form id="handmatigForm" action="{{ route('toernooi.blok.handmatige-verdeling', $toernooi) }}" method="POST">
                    @csrf
                    @foreach($gewichtsklassen as $key => $data)
                    <div class="grid grid-cols-3 gap-2 py-1 border-b border-gray-200 text-sm items-center">
                        <div class="font-medium text-gray-700">
                            {{ $data['leeftijdsklasse'] }} {{ $data['gewichtsklasse'] }} kg
                        </div>
                        <div class="text-center text-gray-600">{{ $data['wedstrijden'] }}</div>
                        <div class="text-center">
                            <select name="blok[{{ $key }}]" class="blok-select w-16 border rounded px-2 py-1 text-center text-sm"
                                    onchange="updateBlokTotalen()">
                                <option value="">-</option>
                                @for($i = 1; $i <= $aantalBlokken; $i++)
                                <option value="{{ $i }}" {{ $data['blok'] == $i ? 'selected' : '' }}>{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                        <input type="hidden" name="wedstrijden[{{ $key }}]" value="{{ $data['wedstrijden'] }}">
                    </div>
                    @endforeach

                    <div class="mt-4 flex gap-2">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                            Blokverdeling Opslaan
                        </button>
                        <button type="button" onclick="kopieerVanAutomatisch()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Kopieer van Automatisch
                        </button>
                        <button type="button" onclick="resetVerdeling()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                            Nieuw
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Rechter kolom: Totalen per blok -->
        <div class="w-64 flex-shrink-0">
            <div class="bg-gray-100 rounded-lg p-4 sticky top-4">
                <h4 class="font-bold text-gray-700 mb-3">Totalen per Blok</h4>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="text-left py-1">Blok</th>
                            <th class="text-right py-1">Wedstrijden</th>
                        </tr>
                    </thead>
                    <tbody id="blokTotalen">
                        @for($i = 1; $i <= $aantalBlokken; $i++)
                        <tr class="border-b border-gray-200">
                            <td class="py-1">Blok {{ $i }}</td>
                            <td class="text-right py-1 font-medium" id="blok-totaal-{{ $i }}">0</td>
                        </tr>
                        @endfor
                    </tbody>
                    <tfoot>
                        <tr class="font-bold bg-green-100">
                            <td class="py-2">Totaal</td>
                            <td class="text-right py-2" id="totaal-wedstrijden">0</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="mt-4 p-3 bg-blue-50 rounded text-xs text-blue-800">
                    <strong>Streefwaarde per blok:</strong><br>
                    <span id="streefwaarde">-</span> wedstrijden
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const aantalBlokken = {{ $aantalBlokken }};

function updateBlokTotalen() {
    const totalen = {};
    for (let i = 1; i <= aantalBlokken; i++) {
        totalen[i] = 0;
    }

    // Loop door alle rijen en tel wedstrijden per blok
    document.querySelectorAll('#handmatigForm .blok-select').forEach((select, index) => {
        const blok = parseInt(select.value);
        const wedstrijdenInput = select.closest('.grid').querySelector('input[name^="wedstrijden"]');
        const wedstrijden = parseInt(wedstrijdenInput?.value) || 0;

        if (blok && blok >= 1 && blok <= aantalBlokken) {
            totalen[blok] += wedstrijden;
        }
    });

    // Update UI
    let totaal = 0;
    for (let i = 1; i <= aantalBlokken; i++) {
        document.getElementById('blok-totaal-' + i).textContent = totalen[i];
        totaal += totalen[i];
    }
    document.getElementById('totaal-wedstrijden').textContent = totaal;
    document.getElementById('streefwaarde').textContent = Math.round(totaal / aantalBlokken);
}

function kopieerVanAutomatisch() {
    if (!confirm('Huidige selecties overschrijven met de automatische verdeling?')) return;

    // Haal de huidige automatische verdeling op via de categorieën overzicht
    @foreach($gewichtsklassen as $key => $data)
    @if($data['blok'])
    document.querySelector('select[name="blok[{{ $key }}]"]').value = '{{ $data['blok'] }}';
    @endif
    @endforeach

    updateBlokTotalen();
}

function resetVerdeling() {
    if (!confirm('Alle bloktoewijzingen verwijderen?')) return;

    document.querySelectorAll('#handmatigForm .blok-select').forEach(select => {
        select.value = '';
    });

    updateBlokTotalen();
}

// Initial update
document.addEventListener('DOMContentLoaded', updateBlokTotalen);
</script>

</div><!-- /content-handmatig -->

@endsection
