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
                        @endphp
                        <button
                            @click="nieuwePoule('{{ $jsLeeftijd }}', '{{ $jsGewicht }}')"
                            class="text-gray-500 hover:text-gray-700 hover:bg-gray-200 px-2 py-0.5 rounded text-sm font-medium"
                            title="Nieuwe poule toevoegen"
                        >
                            + Poule
                        </button>
                    </div>
                    <button
                        @click="naarZaaloverzicht('{{ $jsKey }}')"
                        :class="sentCategories['{{ $jsKey }}'] ? 'ring-2 ring-green-500 ring-offset-2' : ''"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 text-sm rounded transition-all"
                    >
                        Naar zaaloverzicht
                    </button>
                </div>

                <div class="p-4">
                    <div class="flex gap-4">
                        {{-- Existing poules --}}
                        <div class="flex flex-wrap gap-4 flex-1">
                            @foreach($category['poules'] as $poule)
                            <div
                                class="border rounded-lg p-3 min-w-[200px] bg-white"
                                @dragover.prevent
                                @drop="dropJudoka($event, {{ $poule->id }})"
                            >
                                <div class="font-medium text-sm text-gray-600 mb-2 flex justify-between items-center">
                                    <span>Poule {{ $poule->nummer }}</span>
                                    <span class="text-xs text-gray-400">{{ $poule->aantal_judokas }} judoka's ({{ $poule->aantal_wedstrijden }}w)</span>
                                </div>
                                <div class="divide-y divide-gray-100">
                                    @foreach($poule->judokas as $judoka)
                                    @php
                                        $isAfwezig = $judoka->aanwezigheid === 'afwezig';
                                        $isGewogen = $judoka->gewicht_gewogen !== null;
                                        $isBinnenKlasse = $judoka->isGewichtBinnenKlasse();
                                        $moetOverpoulen = $isGewogen && !$isBinnenKlasse;
                                    @endphp
                                    <div
                                        draggable="true"
                                        @dragstart="dragStart($event, {{ $judoka->id }}, {{ $poule->id }})"
                                        @dragend="dragEnd()"
                                        class="px-2 py-1.5 hover:bg-blue-50 cursor-move text-sm {{ $isAfwezig || $moetOverpoulen ? 'line-through opacity-50' : '' }}"
                                    >
                                        <div class="flex justify-between items-start">
                                            <div class="flex items-center gap-1 flex-1 min-w-0">
                                                {{-- Status marker --}}
                                                @if($isAfwezig)
                                                    {{-- No dot --}}
                                                @elseif($moetOverpoulen)
                                                    <span class="text-red-500 text-xs flex-shrink-0">●</span>
                                                @elseif($isGewogen && $isBinnenKlasse)
                                                    <span class="text-green-500 text-xs flex-shrink-0">●</span>
                                                @endif
                                                <div class="min-w-0">
                                                    <div class="font-medium text-gray-800 truncate">{{ $judoka->naam }}</div>
                                                    <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                                </div>
                                            </div>
                                            <div class="text-right text-xs ml-2 flex-shrink-0">
                                                <div class="text-gray-600 font-medium">{{ $judoka->gewicht_gewogen ? $judoka->gewicht_gewogen . ' kg' : ($judoka->gewicht ? $judoka->gewicht . ' kg' : '-') }}</div>
                                                <div class="text-gray-400">{{ ucfirst($judoka->band) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endforeach
                        </div>

                        {{-- Wachtruimte (rechts) --}}
                        <div
                            class="border-2 border-dashed border-orange-300 rounded-lg p-3 min-w-[200px] bg-orange-50 flex-shrink-0"
                            @dragover.prevent
                            @drop="dropToWachtruimte($event, '{{ $jsKey }}')"
                        >
                            <div class="font-medium text-sm text-orange-600 mb-2 flex justify-between">
                                <span>Wachtruimte</span>
                                <span class="text-xs text-orange-400">{{ count($category['wachtruimte']) }}</span>
                            </div>
                            <div class="divide-y divide-orange-200">
                                @forelse($category['wachtruimte'] as $judoka)
                                <div
                                    draggable="true"
                                    @dragstart="dragStartFromWacht($event, {{ $judoka->id }}, '{{ $jsKey }}')"
                                    @dragend="dragEnd()"
                                    class="px-2 py-1.5 hover:bg-orange-100 cursor-move text-sm"
                                >
                                    <div class="flex justify-between items-start">
                                        <div class="flex items-center gap-1 flex-1 min-w-0">
                                            <span class="text-red-500 text-xs flex-shrink-0">●</span>
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-800 truncate">{{ $judoka->naam }}</div>
                                                <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right text-xs ml-2 flex-shrink-0">
                                            <div class="text-orange-600 font-medium">{{ $judoka->gewicht_gewogen }} kg</div>
                                            <div class="text-gray-400">was {{ $judoka->gewichtsklasse }}</div>
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

<script>
function wedstrijddagPoules() {
    return {
        draggedJudoka: null,
        draggedFromPoule: null,
        draggedFromWacht: null,
        sentCategories: @json($sentToZaaloverzicht ?? []),

        dragStart(event, judokaId, pouleId) {
            console.log('dragStart', judokaId, 'from poule', pouleId);
            this.draggedJudoka = judokaId;
            this.draggedFromPoule = pouleId;
            this.draggedFromWacht = null;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', judokaId);
        },

        dragStartFromWacht(event, judokaId, categoryKey) {
            console.log('dragStartFromWacht', judokaId, categoryKey);
            this.draggedJudoka = judokaId;
            this.draggedFromPoule = null;
            this.draggedFromWacht = categoryKey;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', judokaId);
        },

        dragEnd() {
            this.draggedJudoka = null;
            this.draggedFromPoule = null;
            this.draggedFromWacht = null;
        },

        async dropJudoka(event, pouleId) {
            if (!this.draggedJudoka) {
                console.log('No dragged judoka');
                return;
            }

            console.log('Dropping judoka', this.draggedJudoka, 'to poule', pouleId, 'from', this.draggedFromPoule);

            try {
                const response = await fetch('{{ route("toernooi.wedstrijddag.verplaats-judoka", $toernooi) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        judoka_id: this.draggedJudoka,
                        poule_id: pouleId,
                        from_poule_id: this.draggedFromPoule,
                    }),
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    window.location.reload();
                } else {
                    alert('Fout: ' + (data.error || data.message || 'Onbekende fout'));
                }
            } catch (error) {
                console.error('Error moving judoka:', error);
                alert('Fout bij verplaatsen: ' + error.message);
            }
        },

        dropToWachtruimte(event, categoryKey) {
            console.log('Drop to wachtruimte not implemented - determined by weight');
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
</script>
@endsection
