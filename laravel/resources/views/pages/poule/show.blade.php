@extends('layouts.app')

@section('title', $poule->titel)

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-start">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ $poule->titel }}</h1>
            <p class="text-gray-600">Blok {{ $poule->blok?->nummer ?? '?' }} - Mat {{ $poule->mat?->nummer ?? '?' }}</p>
        </div>
        <a href="{{ route('toernooi.poule.index', $toernooi) }}" class="text-blue-600 hover:text-blue-800">
            ← Terug naar poules
        </a>
    </div>
</div>

<!-- Toast notification -->
<div id="toast" class="fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
    <span id="toast-message"></span>
</div>

@if($poule->judokas->count() < 3)
<div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-6">
    <h3 class="font-bold text-red-800">Problematische poule</h3>
    <p class="text-red-700 text-sm">Deze poule heeft minder dan 3 judoka's. Sleep judoka's hierheen of voeg samen met een andere poule.</p>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Judoka's in deze poule -->
    <div class="lg:col-span-2 bg-white rounded-lg shadow">
        <div class="px-4 py-3 border-b bg-blue-50">
            <h2 class="text-xl font-bold text-blue-800">
                {{ $poule->gewichtsklasse }}
                <span class="text-sm font-normal text-blue-600" id="current-poule-count">({{ $poule->judokas->count() }} judoka's)</span>
            </h2>
            <p class="text-sm text-blue-600">Sleep judoka's naar een andere poule</p>
        </div>

        <div class="divide-y divide-gray-100 min-h-[100px] sortable-poule" data-poule-id="{{ $poule->id }}">
            @foreach($poule->judokas as $judoka)
            <div class="px-4 py-3 hover:bg-blue-50 cursor-move judoka-item flex justify-between items-center"
                 data-judoka-id="{{ $judoka->id }}"
                 data-judoka-naam="{{ $judoka->naam }}"
                 data-poule-id="{{ $poule->id }}">
                <div>
                    <div class="font-medium text-gray-800">{{ $judoka->naam }}</div>
                    <div class="text-sm text-gray-500">{{ $judoka->club?->naam ?? '-' }} | {{ ucfirst($judoka->band) }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('toernooi.judoka.show', [$toernooi, $judoka]) }}"
                       class="text-blue-600 hover:text-blue-800 text-sm"
                       onclick="event.stopPropagation()">
                        Details
                    </a>
                    <form action="{{ route('toernooi.poule.verwijder-judoka', [$toernooi, $poule, $judoka]) }}"
                          method="POST" class="inline"
                          onsubmit="return confirm('Judoka verwijderen uit poule?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm" onclick="event.stopPropagation()">
                            Verwijder
                        </button>
                    </form>
                </div>
            </div>
            @endforeach

            @if($poule->judokas->isEmpty())
            <div class="px-4 py-8 text-gray-400 text-center italic">Geen judoka's in deze poule</div>
            @endif
        </div>
    </div>

    <!-- Compatibele poules (drop targets) -->
    <div class="lg:col-span-1 space-y-4">
        <h3 class="font-bold text-gray-700">Verplaats naar andere poule</h3>
        <p class="text-sm text-gray-500 mb-2">Sleep een judoka hierheen:</p>

        @forelse($compatibelePoules as $cp)
        <div class="bg-white rounded-lg shadow border-2 border-dashed border-gray-200 hover:border-blue-300 transition-colors sortable-poule"
             data-poule-id="{{ $cp->id }}">
            <div class="px-3 py-2 border-b bg-gray-50">
                <div class="flex justify-between items-center">
                    <a href="{{ route('toernooi.poule.show', [$toernooi, $cp]) }}" class="font-medium text-blue-600 hover:text-blue-800 text-sm">
                        {{ $cp->gewichtsklasse }}
                    </a>
                    <span class="text-xs text-gray-500" data-poule-count="{{ $cp->id }}">{{ $cp->judokas_count }}</span>
                </div>
            </div>
            <div class="min-h-[40px] p-2 text-xs text-gray-400 text-center">
                Sleep hierheen
            </div>
        </div>
        @empty
        <div class="text-gray-400 text-sm italic">Geen compatibele poules beschikbaar</div>
        @endforelse

        <!-- Samenvoegen optie -->
        @if($compatibelePoules->count() > 0)
        <div class="bg-white rounded-lg shadow p-4 mt-6">
            <h3 class="font-bold text-gray-700 mb-2">Poule samenvoegen</h3>
            <p class="text-xs text-gray-500 mb-3">Voeg alle judoka's van een andere poule toe:</p>
            <form action="{{ route('toernooi.poule.samenvoegen', [$toernooi, $poule]) }}" method="POST"
                  onsubmit="return confirm('Let op: de andere poule wordt verwijderd. Doorgaan?')">
                @csrf
                <select name="andere_poule_id" class="w-full border rounded px-3 py-2 text-sm mb-2">
                    <option value="">Kies poule...</option>
                    @foreach($compatibelePoules as $cp)
                    <option value="{{ $cp->id }}">{{ $cp->titel }} ({{ $cp->judokas_count }})</option>
                    @endforeach
                </select>
                <button type="submit" class="w-full bg-orange-600 hover:bg-orange-700 text-white font-medium py-2 px-4 rounded text-sm">
                    Samenvoegen
                </button>
            </form>
        </div>
        @endif
    </div>

    <!-- Acties panel -->
    <div class="lg:col-span-1 space-y-4">
        <!-- Stand -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-bold text-gray-700 mb-3">Stand</h3>
            @if(count($stand) > 0)
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left">
                        <th class="py-1">#</th>
                        <th class="py-1">Naam</th>
                        <th class="py-1 text-center">W</th>
                        <th class="py-1 text-center">V</th>
                        <th class="py-1 text-center">P</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stand as $s)
                    <tr class="border-b last:border-0">
                        <td class="py-1 font-bold">{{ $s['positie'] ?? '-' }}</td>
                        <td class="py-1 truncate max-w-[100px]">{{ $s['naam'] }}</td>
                        <td class="py-1 text-center">{{ $s['gewonnen'] }}</td>
                        <td class="py-1 text-center">{{ $s['verloren'] }}</td>
                        <td class="py-1 text-center font-bold">{{ $s['punten'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-gray-400 text-sm italic">Nog geen stand</p>
            @endif
        </div>

        <!-- Wedstrijden -->
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-bold text-gray-700 mb-3">Wedstrijden</h3>
            <p class="text-sm text-gray-600 mb-3" id="wedstrijden-info">
                {{ $poule->wedstrijden->count() }} wedstrijden
            </p>
            @if($poule->wedstrijden->count() > 0)
            <a href="{{ route('toernooi.poule.wedstrijdschema', [$toernooi, $poule]) }}"
               class="block text-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded text-sm">
                Bekijk schema
            </a>
            @else
            <form action="{{ route('toernooi.poule.genereer-wedstrijden', [$toernooi, $poule]) }}" method="POST">
                @csrf
                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded text-sm">
                    Genereer wedstrijden
                </button>
            </form>
            @endif
        </div>
    </div>
</div>

<!-- SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const huidigePouleId = {{ $poule->id }};

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
                const judokaNaam = evt.item.dataset.judokaNaam;
                const vanPouleId = evt.from.dataset.pouleId;
                const naarPouleId = evt.to.dataset.pouleId;

                if (vanPouleId === naarPouleId) return;

                // Update data attribute
                evt.item.dataset.pouleId = naarPouleId;

                try {
                    const response = await fetch('{{ route('toernooi.poule.verplaats-judoka-api', $toernooi) }}', {
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
                        showToast(data.message);

                        // Update counts
                        updateCounts(data.van_poule, data.naar_poule);

                        // If moved away from current poule, redirect after delay
                        if (parseInt(vanPouleId) === huidigePouleId) {
                            setTimeout(() => {
                                window.location.reload();
                            }, 1500);
                        }
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('Fout bij verplaatsen', true);
                    // Revert
                    evt.from.appendChild(evt.item);
                }
            }
        });
    });

    function updateCounts(vanPoule, naarPoule) {
        // Update van poule
        const vanCount = document.querySelector(`[data-poule-count="${vanPoule.id}"]`);
        if (vanCount) vanCount.textContent = vanPoule.judokas_count;

        // Update naar poule
        const naarCount = document.querySelector(`[data-poule-count="${naarPoule.id}"]`);
        if (naarCount) naarCount.textContent = naarPoule.judokas_count;

        // Update current poule header if affected
        if (vanPoule.id === huidigePouleId) {
            document.getElementById('current-poule-count').textContent = `(${vanPoule.judokas_count} judoka's)`;
            document.getElementById('wedstrijden-info').textContent = `${vanPoule.aantal_wedstrijden} wedstrijden`;
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
.sortable-ghost { opacity: 0.4; }
.judoka-item { touch-action: none; }
</style>
@endsection
