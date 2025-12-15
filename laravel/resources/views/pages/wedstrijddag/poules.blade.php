@extends('layouts.app')

@section('title', 'Wedstrijddag Poules')

@section('content')
<div x-data="wedstrijddagPoules()" class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">Wedstrijddag Poules</h1>
        <a href="{{ route('toernooi.blok.zaaloverzicht', $toernooi) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
            Naar Zaaloverzicht
        </a>
    </div>

    @forelse($blokken as $blok)
    <div class="bg-white rounded-lg shadow" x-data="{ open: true }">
        {{-- Blok header (inklapbaar) --}}
        <button @click="open = !open" class="w-full flex justify-between items-center px-4 py-3 bg-gray-800 text-white rounded-t-lg hover:bg-gray-700">
            <div class="flex items-center gap-4">
                <span class="text-lg font-bold">Blok {{ $blok['nummer'] }}</span>
                <span class="text-gray-300 text-sm">{{ $blok['categories']->count() }} categorieën</span>
            </div>
            <svg :class="{ 'rotate-180': open }" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Categories within blok --}}
        <div x-show="open" x-collapse class="divide-y">
            @forelse($blok['categories'] as $category)
            <div class="bg-white">
                {{-- Category header --}}
                <div class="flex justify-between items-center px-4 py-3 bg-gray-100 border-b">
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-bold">
                            {{ $category['leeftijdsklasse'] }} {{ $category['gewichtsklasse'] }}
                        </h2>
                        @php
                            $jsLeeftijd = addslashes($category['leeftijdsklasse']);
                            $jsGewicht = addslashes($category['gewichtsklasse']);
                            $jsKey = addslashes($category['key']);
                            // Check of categorie actieve judoka's heeft
                            $catTolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
                            $totaalActiefInCategorie = $category['poules']->sum(function($p) use ($catTolerantie) {
                                return $p->judokas->filter(fn($j) => !$j->moetUitPouleVerwijderd($catTolerantie))->count();
                            });
                        @endphp
                        <button
                            @click="nieuwePoule('{{ $jsLeeftijd }}', '{{ $jsGewicht }}')"
                            class="text-gray-500 hover:text-gray-700 hover:bg-gray-200 px-2 py-0.5 rounded text-sm font-medium"
                            title="Nieuwe poule toevoegen"
                        >
                            + Poule
                        </button>
                    </div>
                    @if($totaalActiefInCategorie > 0)
                    @php
                        $isActivated = isset($activatedCategories[$category['key']]);
                    @endphp
                    @if($isActivated)
                    {{-- Groen: categorie is geactiveerd in zaaloverzicht --}}
                    <span class="bg-green-600 text-white px-3 py-1.5 text-sm rounded flex items-center gap-1">
                        ✓ Actief
                    </span>
                    @else
                    <button
                        @click="naarZaaloverzicht('{{ $jsKey }}')"
                        :class="sentCategories['{{ $jsKey }}'] ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700'"
                        class="text-white px-3 py-1.5 text-sm rounded transition-all"
                    >
                        <span x-show="!sentCategories['{{ $jsKey }}']">Naar zaaloverzicht</span>
                        <span x-show="sentCategories['{{ $jsKey }}']">✓ Doorgestuurd</span>
                    </button>
                    @endif
                    @else
                    <span class="text-gray-400 text-sm italic px-3 py-1.5">Geen actieve judoka's</span>
                    @endif
                </div>

                <div class="p-4">
                    <div class="flex gap-4">
                        {{-- Existing poules --}}
                        <div class="flex flex-wrap gap-4 flex-1">
                            @foreach($category['poules'] as $poule)
                            @php
                                // Tel alleen actieve judoka's (niet afwezig en binnen gewichtsklasse)
                                $tolerantie = $toernooi->gewicht_tolerantie ?? 0.5;
                                $actieveJudokas = $poule->judokas->filter(fn($j) => !$j->moetUitPouleVerwijderd($tolerantie));
                                $aantalActief = $actieveJudokas->count();
                                $aantalWedstrijden = $aantalActief > 1 ? ($aantalActief * ($aantalActief - 1)) / 2 : 0;
                                $heeftDoorgestreept = $poule->judokas->count() > $aantalActief;
                            @endphp
                            <div
                                class="border rounded-lg overflow-hidden min-w-[200px] bg-white transition-colors poule-card {{ $aantalActief === 0 ? 'opacity-50' : '' }}"
                                data-poule-id="{{ $poule->id }}"
                                data-actief="{{ $aantalActief }}"
                            >
                                <div class="bg-blue-700 text-white px-3 py-2 pointer-events-none">
                                    <div class="font-bold text-sm">#{{ $poule->nummer }} {{ $poule->leeftijdsklasse }} / {{ $poule->gewichtsklasse }}</div>
                                    <div class="text-xs text-blue-200 poule-stats">{{ $aantalActief }} judoka's {{ $aantalWedstrijden }} wedstrijden</div>
                                </div>
                                <div class="divide-y divide-gray-100 sortable-poule min-h-[40px]" data-poule-id="{{ $poule->id }}">
                                    @foreach($poule->judokas as $judoka)
                                    @php
                                        $isGewogen = $judoka->gewicht_gewogen !== null;
                                        $isAfwezig = $judoka->aanwezigheid === 'afwezig';
                                        $isAfwijkendGewicht = $isGewogen && !$judoka->isGewichtBinnenKlasse(null, $tolerantie);
                                        $isDoorgestreept = $isAfwezig || $isAfwijkendGewicht;
                                    @endphp
                                    <div
                                        class="px-2 py-1.5 text-sm judoka-item {{ $isDoorgestreept ? 'bg-gray-100 opacity-60' : 'hover:bg-blue-50 cursor-move' }}"
                                        data-judoka-id="{{ $judoka->id }}"
                                        @if(!$isDoorgestreept) draggable="true" @endif
                                    >
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-1 flex-1 min-w-0">
                                                {{-- Status marker --}}
                                                @if($isAfwijkendGewicht)
                                                    <span class="text-red-500 text-xs flex-shrink-0" title="Afwijkend gewicht">●</span>
                                                @elseif($isGewogen && !$isDoorgestreept)
                                                    <span class="text-green-500 text-xs flex-shrink-0">●</span>
                                                @endif
                                                <div class="min-w-0 {{ $isDoorgestreept ? 'line-through text-gray-400' : '' }}">
                                                    <div class="font-medium {{ $isDoorgestreept ? 'text-gray-400' : 'text-gray-800' }} truncate">{{ $judoka->naam }} <span class="text-gray-400 font-normal">({{ $judoka->gewichtsklasse }})</span></div>
                                                    <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div class="text-right text-xs flex-shrink-0">
                                                    <div class="{{ $isAfwijkendGewicht ? 'text-red-600' : 'text-gray-600' }} font-medium">{{ $judoka->gewicht_gewogen ? $judoka->gewicht_gewogen . ' kg' : ($judoka->gewicht ? $judoka->gewicht . ' kg' : '-') }}</div>
                                                    <div class="text-gray-400">{{ ucfirst($judoka->band) }}</div>
                                                </div>
                                                {{-- Verwijder knop voor doorgestreepte judoka's --}}
                                                @if($isDoorgestreept)
                                                <button
                                                    @click="verwijderUitPoule({{ $judoka->id }}, {{ $poule->id }})"
                                                    class="w-6 h-6 flex items-center justify-center bg-red-100 hover:bg-red-200 text-red-600 rounded-full text-xs font-bold"
                                                    title="{{ $isAfwezig ? 'Afwezig - verwijder uit poule' : 'Afwijkend gewicht - verwijder uit poule' }}"
                                                >−</button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>

                        {{-- Wachtruimte (rechts) --}}
                        <div class="border-2 border-dashed border-orange-300 rounded-lg p-3 min-w-[200px] bg-orange-50 flex-shrink-0">
                            <div class="font-medium text-sm text-orange-600 mb-2 flex justify-between">
                                <span>Wachtruimte</span>
                                <span class="text-xs text-orange-400">{{ count($category['wachtruimte']) }}</span>
                            </div>
                            <div class="divide-y divide-orange-200 sortable-wachtruimte min-h-[40px]" data-category="{{ $category['key'] }}">
                                @forelse($category['wachtruimte'] as $judoka)
                                <div
                                    class="px-2 py-1.5 hover:bg-orange-100 cursor-move text-sm judoka-item"
                                    data-judoka-id="{{ $judoka->id }}"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center gap-1 flex-1 min-w-0">
                                            <span class="text-red-500 text-xs flex-shrink-0">●</span>
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-800 truncate">{{ $judoka->naam }} <span class="text-gray-400 font-normal">({{ $judoka->gewichtsklasse }})</span></div>
                                                <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right text-xs ml-2 flex-shrink-0">
                                            <div class="text-orange-600 font-medium">{{ $judoka->gewicht_gewogen }} kg</div>
                                        </div>
                                    </div>
                                </div>
                                @empty
                                <div class="text-sm text-gray-400 italic py-2 text-center">Leeg</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="p-4 text-gray-500 text-center">Geen categorieën in dit blok</div>
            @endforelse
        </div>
    </div>
    @empty
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Geen blokken gevonden. Maak eerst blokken aan via de Blokken pagina.
    </div>
    @endforelse
</div>

<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function wedstrijddagPoules() {
    return {
        sentCategories: @json($sentToZaaloverzicht ?? []),

        async verwijderUitPoule(judokaId, pouleId) {
            if (!confirm('Weet je zeker dat je deze judoka uit de poule wilt verwijderen?')) return;

            try {
                const response = await fetch('{{ route("toernooi.wedstrijddag.verwijder-uit-poule", $toernooi) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ judoka_id: judokaId, poule_id: pouleId }),
                });

                if (response.ok) {
                    // Verwijder het element uit de DOM
                    const judokaEl = document.querySelector(`.judoka-item[data-judoka-id="${judokaId}"]`);
                    if (judokaEl) {
                        judokaEl.remove();
                    }
                    // Update stats
                    const data = await response.json();
                    if (data.poule) {
                        updatePouleStats(data.poule);
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Fout bij verwijderen');
            }
        },

        async naarZaaloverzicht(categoryKey) {
            try {
                const response = await fetch('{{ route("toernooi.wedstrijddag.naar-zaaloverzicht", $toernooi) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ category: categoryKey }),
                });

                if (response.ok) {
                    this.sentCategories[categoryKey] = true;
                }
            } catch (error) {
                console.error('Error:', error);
            }
        },

        async nieuwePoule(leeftijdsklasse, gewichtsklasse) {
            try {
                const response = await fetch('{{ route("toernooi.wedstrijddag.nieuwe-poule", $toernooi) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ leeftijdsklasse, gewichtsklasse }),
                });

                if (response.ok) {
                    window.location.reload();
                }
            } catch (error) {
                console.error('Error creating poule:', error);
            }
        }
    }
}

const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const verplaatsUrl = '{{ route("toernooi.wedstrijddag.verplaats-judoka", $toernooi) }}';

document.addEventListener('DOMContentLoaded', function() {
    // Initialize sortable on all poule containers
    document.querySelectorAll('.sortable-poule').forEach(container => {
        new Sortable(container, {
            group: 'wedstrijddag-poules',
            animation: 150,
            ghostClass: 'bg-blue-100',
            chosenClass: 'bg-blue-200',
            dragClass: 'shadow-lg',
            onEnd: async function(evt) {
                const judokaId = evt.item.dataset.judokaId;
                const vanPouleId = evt.from.dataset.pouleId;
                const naarPouleId = evt.to.dataset.pouleId;
                const newIndex = evt.newIndex;

                // Calculate positions of all judokas in the target poule
                const positions = Array.from(evt.to.querySelectorAll('.judoka-item'))
                    .map((el, idx) => ({ id: parseInt(el.dataset.judokaId), positie: idx + 1 }));

                try {
                    const response = await fetch(verplaatsUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            judoka_id: judokaId,
                            poule_id: naarPouleId,
                            from_poule_id: vanPouleId !== naarPouleId ? vanPouleId : null,
                            positions: positions
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update stats without full reload
                        if (data.van_poule) updatePouleStats(data.van_poule);
                        if (data.naar_poule) updatePouleStats(data.naar_poule);
                    } else {
                        alert('Fout: ' + (data.error || data.message || 'Onbekende fout'));
                        window.location.reload();
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Fout bij verplaatsen: ' + error.message);
                    window.location.reload();
                }
            }
        });
    });

    // Initialize sortable on wachtruimte (only as source, not drop target)
    document.querySelectorAll('.sortable-wachtruimte').forEach(container => {
        new Sortable(container, {
            group: {
                name: 'wedstrijddag-poules',
                pull: true,
                put: false // Cannot drop into wachtruimte
            },
            animation: 150,
            ghostClass: 'bg-orange-200',
            sort: false // No sorting within wachtruimte
        });
    });

    function updatePouleStats(pouleData) {
        const pouleCard = document.querySelector(`.poule-card[data-poule-id="${pouleData.id}"]`);
        if (!pouleCard) {
            console.warn('Poule card not found for id:', pouleData.id);
            return;
        }

        // Update the stats in header
        const statsSpan = pouleCard.querySelector('.poule-stats');
        if (statsSpan) {
            statsSpan.textContent = `${pouleData.aantal_judokas} judoka's ${pouleData.aantal_wedstrijden} wedstrijden`;
        } else {
            console.warn('Stats span not found in poule card:', pouleData.id);
        }
    }
});
</script>

<style>
.sortable-ghost {
    opacity: 0.4;
}
</style>
@endsection
