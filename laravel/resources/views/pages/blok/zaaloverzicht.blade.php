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
            return [
                'leeftijdsklasse' => $lk,
                'gewichtsklasse' => $gk,
                'naam' => $lk . ' ' . $gk,
                'leeftijd_sort' => $leeftijdVolgorde[$lk] ?? 99,
                'gewicht_sort' => floatval(preg_replace('/[^0-9.]/', '', $gk)),
            ];
        })
        ->unique('naam')
        ->sortBy([['leeftijd_sort', 'asc'], ['gewicht_sort', 'asc']])
        ->values();
@endphp
<div class="mb-6" x-data="{ open: true }">
    <div class="bg-gray-800 text-white px-4 py-3 rounded-t-lg">
        <button @click="open = !open" class="w-full flex justify-between items-center hover:text-gray-200">
            <div class="flex items-center gap-4">
                <span class="text-lg font-bold">Blok {{ $blok['nummer'] }}</span>
                <span class="text-gray-300 text-sm">
                    {{ collect($blok['matten'])->sum(fn($m) => count($m['poules'])) }} poules |
                    {{ collect($blok['matten'])->sum(fn($m) => collect($m['poules'])->sum('wedstrijden')) }} wedstrijden
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
                $isSent = isset(($sentToZaaloverzicht ?? [])[$catKey]);
                $hasWaiting = $catData && $catData['wachtruimte_count'] > 0;

                // rood = wachtend, wit = aanwezig, groen = naar mat
                if ($isSent) {
                    $btnClass = 'bg-green-500 text-white';
                } elseif ($hasWaiting) {
                    $btnClass = 'bg-red-500 text-white';
                } else {
                    $btnClass = 'bg-white text-gray-800';
                }
            @endphp
            <a href="{{ route('toernooi.wedstrijddag.poules', $toernooi) }}#{{ urlencode($catKey) }}"
               class="px-2 py-0.5 text-xs rounded {{ $btnClass }} hover:opacity-80"
            >
                {{ $catNaam }}
                @if($hasWaiting)
                <span class="text-xs">({{ $catData['wachtruimte_count'] }})</span>
                @endif
            </a>
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
                        // Filter out poules with 0 wedstrijden
                        $poulesMetWedstrijden = collect($matData['poules'])->filter(fn($p) => $p['wedstrijden'] > 0);
                    @endphp
                    <div class="p-2 space-y-1 min-h-[100px] mat-container" data-mat-id="{{ $matId }}" data-blok-nummer="{{ $blok['nummer'] }}">
                        @forelse($poulesMetWedstrijden as $poule)
                        @php
                            // titel = "A-pupillen -30 kg Poule 11" -> split op "Poule"
                            $titelParts = explode(' Poule ', $poule['titel']);
                            $categorie = $titelParts[0] ?? $poule['titel'];
                        @endphp
                        <div class="poule-item text-xs border rounded p-1 bg-gray-50 cursor-move hover:bg-blue-50"
                             data-poule-id="{{ $poule['id'] }}"
                             data-wedstrijden="{{ $poule['wedstrijden'] }}">
                            <div class="font-medium text-gray-800">{{ $categorie }}</div>
                            <div class="text-gray-500">Poule {{ $poule['nummer'] }} ({{ $poule['wedstrijden'] }}w)</div>
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
