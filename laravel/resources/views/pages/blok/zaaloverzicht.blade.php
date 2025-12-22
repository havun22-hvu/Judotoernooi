@extends('layouts.app')

@section('title', 'Zaaloverzicht')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Zaaloverzicht</h1>
    <div class="flex items-center gap-4">
        <a href="{{ route('toernooi.wedstrijddag.poules', $toernooi) }}" class="text-blue-600 hover:underline">
            Wedstrijddag Poules →
        </a>
        <a href="{{ route('toernooi.blok.index', $toernooi) }}" class="text-blue-600 hover:underline">
            ← Terug naar Blokkenverdeling
        </a>
    </div>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-2 rounded mb-4 text-sm">{{ session('success') }}</div>
@endif

<p class="text-sm text-gray-500 mb-4">💡 Sleep poules naar een andere mat om te verplaatsen</p>

@foreach($overzicht as $blok)
@php
    // Leeftijd volgorde: mini's eerst (jongste)
    $leeftijdVolgorde = [
        "Mini's" => 1, 'A-pupillen' => 2, 'B-pupillen' => 3,
        'Dames -15' => 4, 'Heren -15' => 5, 'Dames -18' => 6, 'Heren -18' => 7,
        'Dames' => 8, 'Heren' => 9,
    ];

    // Get unique categories in this blok with leeftijdsklasse and gewichtsklasse for sorting
    $blokCategories = collect($blok['matten'])
        ->flatMap(fn($m) => $m['poules'])
        ->map(function($p) use ($leeftijdVolgorde) {
            $lk = $p['leeftijdsklasse'] ?? '';
            $gk = $p['gewichtsklasse'] ?? '';
            // Sorteer: nummer + 1000 als het een + categorie is (zodat +60 na -60 komt)
            $gewichtNum = floatval(preg_replace('/[^0-9.]/', '', $gk));
            $isPlus = str_starts_with($gk, '+');
            return [
                'leeftijdsklasse' => $lk,
                'gewichtsklasse' => $gk,
                'naam' => $lk . ' ' . $gk,
                'leeftijd_sort' => $leeftijdVolgorde[$lk] ?? 99,
                'gewicht_sort' => $gewichtNum + ($isPlus ? 1000 : 0),
            ];
        })
        ->unique('naam')
        ->sortBy([['leeftijd_sort', 'asc'], ['gewicht_sort', 'asc']])
        ->values();
@endphp
<div class="mb-6" x-data="{ open: false }">
    <div class="bg-gray-800 text-white px-4 py-3 rounded-t-lg">
        <button @click="open = !open" class="w-full flex justify-between items-center hover:text-gray-200">
            <div class="flex items-center gap-4">
                <span class="text-lg font-bold">Blok {{ $blok['nummer'] }}</span>
                @php
                    $blokPoules = collect($blok['matten'])->sum(fn($m) => count($m['poules']));
                    $blokJudokas = collect($blok['matten'])->sum(fn($m) => collect($m['poules'])->sum('judokas'));
                    $blokWedstrijden = collect($blok['matten'])->sum(fn($m) => collect($m['poules'])->sum('wedstrijden'));
                    $aantalMatten = count($blok['matten']);
                    $gemPerMat = $aantalMatten > 0 ? round($blokWedstrijden / $aantalMatten, 1) : 0;
                @endphp
                <span class="text-gray-300 text-sm">
                    {{ $blokJudokas }} judoka's | {{ $blokWedstrijden }} wedstrijden | {{ $blokPoules }} poules ({{ $gemPerMat }} wed/mat)
                </span>
                @if($blok['weging_gesloten'])
                <span class="px-2 py-1 text-xs bg-red-500 rounded">Weging gesloten</span>
                @endif
            </div>
            <svg :class="{ 'rotate-180': open }" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>
        @if($blokCategories->isNotEmpty())
        <div class="flex flex-wrap gap-1.5 mt-2 pt-2 border-t border-gray-700">
            @foreach($blokCategories as $catInfo)
            @php
                $catNaam = $catInfo['naam'];
                $catKey = $catInfo['leeftijdsklasse'] . '|' . $catInfo['gewichtsklasse'];
                $catData = ($categories ?? [])[$catKey] ?? null;
                $isSent = $catData && ($catData['is_sent'] ?? false);
                $hasWedstrijden = $catData && ($catData['is_activated'] ?? false);
                $hasWaiting = $catData && $catData['wachtruimte_count'] > 0;

                // Status flow (consistent with wedstrijddag):
                // 1. Groen = doorgestuurd EN wedstrijdschema gegenereerd
                // 2. Wit = doorgestuurd, klaar voor activatie
                // 3. Grijs = NIET doorgestuurd (wacht op overpoulen)
                // BELANGRIJK: kan alleen groen zijn als eerst doorgestuurd!
                $isActivated = $isSent && $hasWedstrijden;

                if ($isActivated) {
                    $btnClass = 'bg-green-500 text-white font-medium';
                } elseif ($isSent) {
                    $btnClass = 'bg-white text-gray-800 font-medium';
                } else {
                    $btnClass = 'bg-gray-500 text-gray-300';
                }
            @endphp
            @if($isActivated)
            {{-- Groen: al geactiveerd, klik gaat naar mat interface met blok voorgeselecteerd --}}
            <a href="{{ route('toernooi.mat.interface', ['toernooi' => $toernooi, 'blok' => $blok['nummer']]) }}"
               class="px-2 py-0.5 text-xs rounded {{ $btnClass }} hover:opacity-80"
            >
                ✓ {{ $catNaam }}
            </a>
            @elseif($hasWaiting && !$isSent)
            {{-- Heeft wachtende judokas, moet eerst overpoulen --}}
            <button
                onclick="alert('Maak eerst de poules klaar bij Overpoulen')"
                class="px-2 py-0.5 text-xs rounded {{ $btnClass }} hover:opacity-80 cursor-not-allowed"
            >
                {{ $catNaam }}
                <span class="text-xs">({{ $catData['wachtruimte_count'] }})</span>
            </button>
            @elseif($isSent)
            {{-- Wit: klaar voor activatie, klik genereert wedstrijdschema --}}
            <form action="{{ route('toernooi.blok.activeer-categorie', $toernooi) }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="category" value="{{ $catKey }}">
                <input type="hidden" name="blok" value="{{ $blok['nummer'] }}">
                <button type="submit" class="px-2 py-0.5 text-xs rounded {{ $btnClass }} hover:opacity-80">
                    {{ $catNaam }}
                </button>
            </form>
            @else
            {{-- Grijs: wacht op overpoulen, link naar wedstrijddag --}}
            <a href="{{ route('toernooi.wedstrijddag.poules', $toernooi) }}#{{ urlencode($catKey) }}"
               class="px-2 py-0.5 text-xs rounded {{ $btnClass }} hover:opacity-80"
            >
                {{ $catNaam }}
            </a>
            @endif
            @endforeach
        </div>
        @endif
    </div>

    <div x-show="open" x-collapse class="bg-white rounded-b-lg shadow">
        <div class="p-4">
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                @foreach($blok['matten'] as $matNr => $matData)
                @php
                    $matId = $toernooi->matten->firstWhere('nummer', $matNr)?->id;
                    $matWedstrijden = collect($matData['poules'])->sum('wedstrijden');
                @endphp
                <div class="border rounded-lg overflow-hidden">
                    <div class="bg-blue-600 text-white px-3 py-2 flex justify-between items-center">
                        <span class="font-bold">Mat {{ $matNr }}</span>
                        <span class="text-xs bg-blue-800 px-2 py-0.5 rounded mat-wedstrijden">{{ $matWedstrijden }}w</span>
                    </div>
                    @php
                        // Filter poules zonder judoka's (lege poules)
                        $allePoules = collect($matData['poules'])->filter(fn($p) => ($p['judokas'] ?? 0) > 0);
                    @endphp
                    <div class="p-2 space-y-1 min-h-[100px] mat-container" data-mat-id="{{ $matId }}" data-blok-nummer="{{ $blok['nummer'] }}">
                        @forelse($allePoules as $poule)
                        @php
                            // titel = "A-pupillen -30 kg Poule 11" -> split op "Poule"
                            $titelParts = explode(' Poule ', $poule['titel']);
                            $categorie = $titelParts[0] ?? $poule['titel'];
                            $heeftWedstrijden = $poule['wedstrijden'] > 0;
                        @endphp
                        <div class="poule-item text-xs border rounded p-1 {{ $heeftWedstrijden ? 'bg-gray-50' : 'bg-gray-100' }} cursor-move hover:bg-blue-50"
                             data-poule-id="{{ $poule['id'] }}"
                             data-wedstrijden="{{ $poule['wedstrijden'] }}">
                            <div class="font-medium text-gray-800">{{ $categorie }}</div>
                            <div class="text-gray-500">Poule {{ $poule['nummer'] }} ({{ $heeftWedstrijden ? $poule['wedstrijden'] . 'w' : $poule['judokas'] . 'j' }})</div>
                        </div>
                        @empty
                        <div class="text-gray-400 text-xs italic empty-message">Geen poules</div>
                        @endforelse
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endforeach

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const verplaatsUrl = '{{ route('toernooi.blok.verplaats-poule', $toernooi) }}';

document.querySelectorAll('.mat-container').forEach(container => {
    new Sortable(container, {
        group: 'matten',
        animation: 150,
        ghostClass: 'bg-blue-100',
        chosenClass: 'bg-blue-200',
        dragClass: 'shadow-lg',
        onEnd: async function(evt) {
            const pouleEl = evt.item;
            const pouleId = pouleEl.dataset.pouleId;
            const newMatId = evt.to.dataset.matId;
            const oldMatId = evt.from.dataset.matId;

            if (newMatId === oldMatId) return;

            // Remove empty message if present
            const emptyMsg = evt.to.querySelector('.empty-message');
            if (emptyMsg) emptyMsg.remove();

            // Add empty message to old container if empty
            if (evt.from.querySelectorAll('.poule-item').length === 0) {
                evt.from.innerHTML = '<div class="text-gray-400 text-xs italic empty-message">Geen poules</div>';
            }

            try {
                const response = await fetch(verplaatsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        poule_id: pouleId,
                        mat_id: newMatId
                    })
                });

                const data = await response.json();
                if (!data.success) {
                    alert('Fout bij verplaatsen: ' + (data.message || 'Onbekende fout'));
                    location.reload();
                } else {
                    // Update wedstrijden counts
                    updateMatCounts();
                }
            } catch (err) {
                alert('Fout bij verplaatsen: ' + err.message);
                location.reload();
            }
        }
    });
});

function updateMatCounts() {
    document.querySelectorAll('.mat-container').forEach(container => {
        const wedstrijden = Array.from(container.querySelectorAll('.poule-item'))
            .reduce((sum, el) => sum + parseInt(el.dataset.wedstrijden || 0), 0);
        const countEl = container.parentElement.querySelector('.mat-wedstrijden');
        if (countEl) countEl.textContent = wedstrijden + 'w';
    });
}
</script>
@endsection
