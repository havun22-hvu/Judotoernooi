@extends('layouts.app')

@section('title', 'Poules')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Poules ({{ $poules->count() }})</h1>
    <div class="flex items-center space-x-4">
        <span class="text-sm text-gray-500">Sleep judoka's tussen poules</span>
        <button onclick="openNieuwePouleModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            + Nieuwe poule
        </button>
        <button onclick="verifieerPoules()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            Verifieer poules
        </button>
        <form action="{{ route('toernooi.poule.genereer', $toernooi) }}" method="POST" class="inline"
              onsubmit="return confirm('WAARSCHUWING: Dit verwijdert ALLE huidige poules inclusief handmatige wijzigingen en maakt een nieuwe indeling. Weet je het zeker?')">
            @csrf
            <button type="submit" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded">
                Herindelen
            </button>
        </form>
    </div>
</div>

<!-- Verificatie resultaat -->
<div id="verificatie-resultaat" class="hidden mb-6"></div>

<!-- Toast notification -->
<div id="toast" class="fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
    <span id="toast-message"></span>
</div>

@php
    $problematischePoules = $poules->filter(fn($p) => $p->judokas_count < 3);
@endphp

@if($problematischePoules->count() > 0)
<div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-6">
    <h3 class="font-bold text-red-800 mb-2">Problematische poules ({{ $problematischePoules->count() }})</h3>
    <p class="text-red-700 text-sm mb-3">Deze poules hebben minder dan 3 judoka's. Klik om naar de poule te gaan:</p>
    <div class="flex flex-wrap gap-2">
        @foreach($problematischePoules as $p)
        <a href="#poule-{{ $p->id }}" class="inline-flex items-center px-3 py-1 bg-red-100 text-red-800 rounded-full text-sm hover:bg-red-200 cursor-pointer transition-colors">
            #{{ $p->nummer }} {{ $p->leeftijdsklasse }} / {{ $p->gewichtsklasse }} kg ({{ $p->judokas_count }})
        </a>
        @endforeach
    </div>
</div>
@endif

<!-- Per leeftijdsklasse -->
@forelse($poulesPerKlasse as $leeftijdsklasse => $klassePoules)
<div class="mb-8" x-data="{ open: true }">
    <button @click="open = !open" class="w-full flex justify-between items-center bg-blue-800 text-white px-4 py-3 rounded-t-lg hover:bg-blue-700">
        <span class="text-lg font-bold">{{ $leeftijdsklasse }} ({{ $klassePoules->count() }} poules, {{ $klassePoules->sum('judokas_count') }} judoka's)</span>
        <svg :class="{ 'rotate-180': open }" class="w-5 h-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-collapse class="bg-gray-50 rounded-b-lg shadow p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($klassePoules as $poule)
            <div id="poule-{{ $poule->id }}" class="bg-white rounded-lg shadow {{ $poule->judokas_count < 3 ? 'border-2 border-red-300' : '' }}" data-poule-id="{{ $poule->id }}">
                <!-- Poule header -->
                <div class="px-3 py-2 border-b {{ $poule->judokas_count < 3 ? 'bg-red-50' : 'bg-gray-50' }}">
                    <div class="font-bold text-gray-800 text-sm">#{{ $poule->nummer }} {{ $poule->leeftijdsklasse }} / {{ $poule->gewichtsklasse }} kg</div>
                    <div class="flex justify-between items-center text-xs text-gray-500">
                        <span><span data-poule-count="{{ $poule->id }}">{{ $poule->judokas_count }}</span> judoka's</span>
                        <span><span data-poule-wedstrijden="{{ $poule->id }}">{{ $poule->aantal_wedstrijden }}</span> wedstrijden</span>
                    </div>
                </div>

                <!-- Judoka's in poule (sortable) -->
                <div class="divide-y divide-gray-100 min-h-[60px] sortable-poule" data-poule-id="{{ $poule->id }}">
                    @foreach($poule->judokas as $judoka)
                    <div class="px-3 py-2 hover:bg-blue-50 cursor-move text-sm judoka-item"
                         data-judoka-id="{{ $judoka->id }}"
                         data-poule-id="{{ $poule->id }}">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium text-gray-800 truncate">{{ $judoka->naam }}</div>
                                <div class="text-xs text-gray-500 truncate">{{ $judoka->club?->naam ?? '-' }}</div>
                            </div>
                            <div class="text-right text-xs ml-2">
                                <div class="text-gray-600 font-medium">{{ $judoka->gewicht ? $judoka->gewicht . ' kg' : '-' }}</div>
                                <div class="text-gray-400">{{ ucfirst($judoka->band) }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    @if($poule->judokas->isEmpty())
                    <div class="px-3 py-4 text-gray-400 text-sm italic text-center">Leeg</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@empty
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    Nog geen poules. Genereer eerst de poule-indeling.
</div>
@endforelse

<!-- Modal nieuwe poule -->
<div id="nieuwe-poule-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Nieuwe poule aanmaken</h2>
        <form id="nieuwe-poule-form">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Leeftijdsklasse</label>
                <select id="leeftijdsklasse" class="w-full border rounded px-3 py-2" required>
                    <option value="">Selecteer...</option>
                    @foreach($toernooi->getAlleGewichtsklassen() as $key => $klasse)
                    <option value="{{ $key }}" data-label="{{ $klasse['label'] }}">{{ $klasse['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gewichtsklasse</label>
                <select id="gewichtsklasse" class="w-full border rounded px-3 py-2" required disabled>
                    <option value="">Selecteer eerst leeftijdsklasse</option>
                </select>
            </div>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeNieuwePouleModal()" class="px-4 py-2 text-gray-600 hover:text-gray-800">
                    Annuleren
                </button>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Aanmaken
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const verifieerUrl = '{{ route('toernooi.poule.verifieer', $toernooi) }}';
const verplaatsUrl = '{{ route('toernooi.poule.verplaats-judoka-api', $toernooi) }}';
const nieuwePouleUrl = '{{ route('toernooi.poule.store', $toernooi) }}';

// Gewichtsklassen per leeftijdsklasse
const gewichtsklassen = @json($toernooi->getAlleGewichtsklassen());

function openNieuwePouleModal() {
    document.getElementById('nieuwe-poule-modal').classList.remove('hidden');
    document.getElementById('leeftijdsklasse').value = '';
    document.getElementById('gewichtsklasse').innerHTML = '<option value="">Selecteer eerst leeftijdsklasse</option>';
    document.getElementById('gewichtsklasse').disabled = true;
}

function closeNieuwePouleModal() {
    document.getElementById('nieuwe-poule-modal').classList.add('hidden');
}

document.getElementById('leeftijdsklasse').addEventListener('change', function() {
    const select = document.getElementById('gewichtsklasse');
    const key = this.value;

    if (!key || !gewichtsklassen[key]) {
        select.innerHTML = '<option value="">Selecteer eerst leeftijdsklasse</option>';
        select.disabled = true;
        return;
    }

    const gewichten = gewichtsklassen[key].gewichten;
    select.innerHTML = '<option value="">Selecteer...</option>' +
        gewichten.map(g => `<option value="${g}">${g} kg</option>`).join('');
    select.disabled = false;
});

document.getElementById('nieuwe-poule-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const leeftijdsklasseKey = document.getElementById('leeftijdsklasse').value;
    const leeftijdsklasseLabel = document.getElementById('leeftijdsklasse').selectedOptions[0].dataset.label;
    const gewichtsklasse = document.getElementById('gewichtsklasse').value;

    if (!leeftijdsklasseKey || !gewichtsklasse) return;

    try {
        const response = await fetch(nieuwePouleUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                leeftijdsklasse: leeftijdsklasseLabel,
                gewichtsklasse: gewichtsklasse
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Fout bij aanmaken', true);
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Fout bij aanmaken', true);
    }
});

async function verifieerPoules() {
    const resultaatDiv = document.getElementById('verificatie-resultaat');
    resultaatDiv.className = 'mb-6 bg-blue-50 border border-blue-300 rounded-lg p-4';
    resultaatDiv.innerHTML = '<p class="text-blue-700">Bezig met verificatie...</p>';

    try {
        const response = await fetch(verifieerUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (data.success) {
            let html = '';
            const hasProblems = data.problemen.length > 0;

            if (hasProblems) {
                html = `<div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4">
                    <h3 class="font-bold text-yellow-800 mb-2">Verificatie: ${data.problemen.length} probleem(en) gevonden</h3>
                    <ul class="list-disc list-inside text-yellow-700 text-sm mb-3">
                        ${data.problemen.map(p => `<li>${p.message}</li>`).join('')}
                    </ul>
                    <p class="text-yellow-600 text-sm">Totaal: ${data.totaal_poules} poules, ${data.totaal_wedstrijden} wedstrijden${data.herberekend > 0 ? `, ${data.herberekend} poules herberekend` : ''}</p>
                </div>`;
            } else {
                html = `<div class="bg-green-50 border border-green-300 rounded-lg p-4">
                    <h3 class="font-bold text-green-800 mb-2">Verificatie geslaagd!</h3>
                    <p class="text-green-700 text-sm">Totaal: ${data.totaal_poules} poules, ${data.totaal_wedstrijden} wedstrijden${data.herberekend > 0 ? `, ${data.herberekend} poules herberekend` : ''}</p>
                </div>`;
            }

            resultaatDiv.className = 'mb-6';
            resultaatDiv.innerHTML = html;

            // Reload page if matches were recalculated
            if (data.herberekend > 0) {
                setTimeout(() => location.reload(), 2000);
            }
        }
    } catch (error) {
        console.error('Error:', error);
        resultaatDiv.className = 'mb-6 bg-red-50 border border-red-300 rounded-lg p-4';
        resultaatDiv.innerHTML = '<p class="text-red-700">Fout bij verificatie</p>';
    }
}

document.addEventListener('DOMContentLoaded', function() {

    // Initialize sortable on all poule containers
    document.querySelectorAll('.sortable-poule').forEach(container => {
        new Sortable(container, {
            group: 'poules',
            animation: 150,
            ghostClass: 'bg-blue-100',
            chosenClass: 'bg-blue-200',
            dragClass: 'shadow-lg',
            onEnd: async function(evt) {
                const judokaId = evt.item.dataset.judokaId;
                const vanPouleId = evt.from.dataset.pouleId;
                const naarPouleId = evt.to.dataset.pouleId;

                if (vanPouleId === naarPouleId) return;

                // Update data attribute
                evt.item.dataset.pouleId = naarPouleId;

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
                            van_poule_id: vanPouleId,
                            naar_poule_id: naarPouleId
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        // Update counts
                        updatePouleStats(data.van_poule);
                        updatePouleStats(data.naar_poule);

                        // Show toast
                        showToast(data.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Fout bij verplaatsen', true);
                    // Revert the move
                    evt.from.appendChild(evt.item);
                }
            }
        });
    });

    function updatePouleStats(pouleData) {
        // Update count
        const countEl = document.querySelector(`[data-poule-count="${pouleData.id}"]`);
        if (countEl) countEl.textContent = pouleData.judokas_count;

        // Update wedstrijden
        const wedstrijdenEl = document.querySelector(`[data-poule-wedstrijden="${pouleData.id}"]`);
        if (wedstrijdenEl) wedstrijdenEl.textContent = pouleData.aantal_wedstrijden;

        // Update problematic styling
        const pouleCard = document.querySelector(`[data-poule-id="${pouleData.id}"]`);
        if (pouleCard && pouleCard.classList.contains('bg-white')) {
            const header = pouleCard.querySelector('.border-b');
            if (pouleData.judokas_count < 3) {
                pouleCard.classList.add('border-2', 'border-red-300');
                header?.classList.add('bg-red-50');
                header?.classList.remove('bg-gray-50');
            } else {
                pouleCard.classList.remove('border-2', 'border-red-300');
                header?.classList.remove('bg-red-50');
                header?.classList.add('bg-gray-50');
            }
        }
    }

    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        const toastMessage = document.getElementById('toast-message');

        toastMessage.textContent = message;
        toast.classList.remove('translate-x-full', 'bg-green-600', 'bg-red-600');
        toast.classList.add(isError ? 'bg-red-600' : 'bg-green-600');

        setTimeout(() => toast.classList.add('translate-x-full'), 2000);
    }
});
</script>

<style>
.sortable-ghost {
    opacity: 0.4;
}
</style>
@endsection
