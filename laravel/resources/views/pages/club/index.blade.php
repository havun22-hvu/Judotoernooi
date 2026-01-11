@extends('layouts.app')

@section('title', 'Clubs')

@section('content')
<div x-data="{ copiedUrl: null }">

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Clubs</h1>
        <p class="text-gray-600 mt-1">Voeg clubs toe en stuur ze een inschrijflink</p>
    </div>
    <form action="{{ route('toernooi.club.verstuur-alle', $toernooi) }}" method="POST" class="inline"
          onsubmit="return confirm('Alle clubs met email uitnodigen?')">
        @csrf
        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            Alle Uitnodigen
        </button>
    </form>
</div>

@if($toernooi->inschrijving_deadline)
<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-sm">
    <strong>Deadline:</strong> {{ $toernooi->inschrijving_deadline->format('d-m-Y') }}
    @if($toernooi->isInschrijvingOpen())
    <span class="text-green-600 font-medium">(open)</span>
    @else
    <span class="text-red-600 font-medium">(gesloten)</span>
    @endif
</div>
@endif

<!-- PIN Warning -->
<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4 flex items-start gap-2">
    <span class="text-amber-600">⚠️</span>
    <div class="text-sm text-amber-800">
        <strong>Let op:</strong> De PIN geeft volledige toegang tot het aanmelden en wijzigen van judoka's.
        Stuur de URL + PIN alleen naar de verantwoordelijke coach en vraag om deze veilig te bewaren.
    </div>
</div>

<!-- Clubs tabel -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Club</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Plaats</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Email</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Telefoon</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Judoka's</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Kaarten</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Coach Portal</th>
                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-600">Acties</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse($clubs->sortBy('naam') as $club)
            @php
                $uitnodiging = $uitnodigingen[$club->id] ?? null;
                $inschrijfUrl = $uitnodiging ? route('coach.portal', $uitnodiging->token) : null;
                $coach = $club->coaches->first();
                $portalUrl = $coach ? url('/school/' . $coach->portal_code) : null;
            @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <span class="font-medium text-gray-800">{{ $club->naam }}</span>
                    @if($coach)
                    <div class="text-xs text-gray-500 mt-1">
                        Coach: {{ $coach->naam }}
                    </div>
                    @endif
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $club->plaats ?? '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $club->email ?? '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $club->telefoon ?? '-' }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded">{{ $club->judokas_count }}</span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded">{{ $club->coachKaarten->count() }}</span>
                        <form action="{{ route('toernooi.club.coachkaart.add', [$toernooi, $club]) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-1.5 py-0.5 text-xs bg-purple-100 text-purple-700 hover:bg-purple-200 rounded" title="Extra kaart toevoegen">+</button>
                        </form>
                    </div>
                </td>
                <td class="px-4 py-3">
                    @if($coach)
                    <div class="space-y-1">
                        {{-- URL + kopieer knop --}}
                        <div class="flex items-center gap-1">
                            <code class="text-xs bg-gray-100 px-1 py-0.5 rounded text-gray-600 max-w-[200px] truncate" title="{{ $portalUrl }}">
                                {{ $portalUrl }}
                            </code>
                            <button @click="navigator.clipboard.writeText('{{ $portalUrl }}'); copiedUrl = 'url-{{ $club->id }}'; setTimeout(() => copiedUrl = null, 2000)"
                                    class="px-1.5 py-0.5 text-xs rounded flex-shrink-0"
                                    :class="copiedUrl === 'url-{{ $club->id }}' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-600 hover:bg-blue-200'"
                                    title="Kopieer URL">
                                <span x-text="copiedUrl === 'url-{{ $club->id }}' ? '✓' : '📋'"></span>
                            </button>
                        </div>
                        {{-- PIN --}}
                        <div class="flex items-center gap-1">
                            <span class="text-xs font-mono bg-amber-50 px-1.5 py-0.5 rounded text-amber-800">PIN: {{ $coach->pincode }}</span>
                            <button @click="navigator.clipboard.writeText('{{ $coach->pincode }}'); copiedUrl = 'pin-{{ $club->id }}'; setTimeout(() => copiedUrl = null, 2000)"
                                    class="px-1.5 py-0.5 text-xs rounded flex-shrink-0"
                                    :class="copiedUrl === 'pin-{{ $club->id }}' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'"
                                    title="Kopieer PIN">
                                <span x-text="copiedUrl === 'pin-{{ $club->id }}' ? '✓' : '📋'"></span>
                            </button>
                        </div>
                    </div>
                    @else
                    <span class="text-xs text-gray-400">Geen coach</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    <div class="flex justify-end gap-2">
                        @if($club->email)
                        <form action="{{ route('toernooi.club.verstuur', [$toernooi, $club]) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="px-2 py-1 text-xs bg-green-100 text-green-700 hover:bg-green-200 rounded">
                                Email
                            </button>
                        </form>
                        @endif
                        <button onclick="editClub({{ $club->id }}, '{{ $club->naam }}', '{{ $club->plaats }}', '{{ $club->email }}', '{{ $club->telefoon }}')"
                                class="px-2 py-1 text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 rounded">
                            Bewerk
                        </button>
                        <form action="{{ route('toernooi.club.destroy', [$toernooi, $club]) }}" method="POST" class="inline"
                              onsubmit="return confirm('{{ $club->naam }} verwijderen?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-2 py-1 text-xs bg-red-100 text-red-700 hover:bg-red-200 rounded">×</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                    Nog geen clubs. Voeg hieronder een club toe.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<!-- Nieuwe club toevoegen -->
<div class="bg-white rounded-lg shadow p-4 mt-4">
    <h3 class="font-semibold text-gray-700 mb-3">Club toevoegen</h3>
    <form action="{{ route('toernooi.club.store', $toernooi) }}" method="POST" class="flex flex-wrap gap-3 items-end">
        @csrf
        <div>
            <label class="block text-sm text-gray-600 mb-1">Clubnaam *</label>
            <input type="text" name="naam" required class="border rounded px-3 py-2 w-48">
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Plaats</label>
            <input type="text" name="plaats" class="border rounded px-3 py-2 w-36">
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Email</label>
            <input type="email" name="email" class="border rounded px-3 py-2 w-56">
        </div>
        <div>
            <label class="block text-sm text-gray-600 mb-1">Telefoon</label>
            <input type="tel" name="telefoon" class="border rounded px-3 py-2 w-36">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-6 rounded">
            Toevoegen
        </button>
    </form>
</div>

<!-- Edit modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-lg font-bold mb-4">Club bewerken</h3>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Clubnaam *</label>
                    <input type="text" name="naam" id="editNaam" required class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Plaats</label>
                    <input type="text" name="plaats" id="editPlaats" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Email</label>
                    <input type="email" name="email" id="editEmail" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Telefoon</label>
                    <input type="tel" name="telefoon" id="editTelefoon" class="w-full border rounded px-3 py-2">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded">
                    Annuleren
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
                    Opslaan
                </button>
            </div>
        </form>
    </div>
</div>

</div>

<script>
function editClub(id, naam, plaats, email, telefoon) {
    document.getElementById('editForm').action = '{{ url("toernooi/{$toernooi->slug}/club") }}/' + id;
    document.getElementById('editNaam').value = naam;
    document.getElementById('editPlaats').value = plaats || '';
    document.getElementById('editEmail').value = email || '';
    document.getElementById('editTelefoon').value = telefoon || '';
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
@endsection
