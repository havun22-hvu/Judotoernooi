@extends('layouts.app')

@section('title', 'Toernooien')

@section('content')
<div class="flex justify-between items-center mb-8">
    <h1 class="text-3xl font-bold text-gray-800">Toernooien</h1>
    <a href="{{ route('toernooi.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        + Nieuw Toernooi
    </a>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Organisatie</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acties</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($toernooien as $toernooi)
            <tr>
                <td class="px-6 py-4 whitespace-nowrap font-medium">{{ $toernooi->naam }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ $toernooi->datum->format('d-m-Y') }}</td>
                <td class="px-6 py-4 whitespace-nowrap">{{ $toernooi->organisatie }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                    @if($toernooi->is_actief)
                    <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Actief</span>
                    @else
                    <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">Inactief</span>
                    @endif
                </td>
                <td class="px-6 py-4 whitespace-nowrap space-x-2">
                    <a href="{{ route('toernooi.show', $toernooi) }}" class="inline-flex items-center px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-sm rounded" title="Start toernooi">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Start
                    </a>
                    <button onclick="confirmReset({{ $toernooi->id }}, '{{ addslashes($toernooi->naam) }}')" class="inline-flex items-center px-3 py-1 bg-orange-500 hover:bg-orange-600 text-white text-sm rounded" title="Reset toernooi (behoud judoka's)">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Reset
                    </button>
                    {{-- Delete voor eigenaar of sitebeheerder --}}
                    @if(auth('organisator')->user()?->isSitebeheerder() || auth('organisator')->user()?->ownsToernooi($toernooi))
                    <button onclick="confirmDelete('{{ $toernooi->slug }}', '{{ addslashes($toernooi->naam) }}')" class="inline-flex items-center px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-sm rounded" title="Verwijder toernooi permanent">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete
                    </button>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                    Nog geen toernooien. <a href="{{ route('toernooi.create') }}" class="text-blue-600">Maak er een aan</a>.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $toernooien->links() }}
</div>

<!-- Hidden forms for reset and delete -->
<form id="reset-form" method="POST" style="display:none;">
    @csrf
</form>
<form id="delete-form" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
    <input type="hidden" name="bewaar_presets" id="bewaar-presets" value="0">
</form>

<script>
function confirmReset(id, naam) {
    if (confirm(`⚠️ RESET TOERNOOI\n\nWeet je zeker dat je "${naam}" wilt resetten?\n\nDit verwijdert:\n• Alle poules en wedstrijden\n• Alle weeg-resultaten\n• Alle uitslagen\n\nDit behoudt:\n• Alle judoka's\n• Toernooi instellingen\n• Blokken en matten`)) {
        const form = document.getElementById('reset-form');
        form.action = `/toernooi/${id}/reset`;
        form.submit();
    }
}

function confirmDelete(slug, naam) {
    const bevestig = prompt(`🚨 VERWIJDER TOERNOOI PERMANENT\n\nDit verwijdert ALLES:\n• Alle judoka's\n• Alle poules en wedstrijden\n• Alle instellingen\n\nDIT KAN NIET ONGEDAAN WORDEN!\n\nTyp de naam van het toernooi om te bevestigen:`);

    if (bevestig === naam) {
        const presetKeuze = prompt('Wil je je gewichtsklassen-presets BEWAREN?\n\nTyp "ja" om presets te bewaren\nTyp "nee" om alles te verwijderen');

        if (presetKeuze === null) {
            return; // Gebruiker annuleerde
        }

        const bewaarPresets = presetKeuze.toLowerCase() === 'ja';

        const form = document.getElementById('delete-form');
        document.getElementById('bewaar-presets').value = bewaarPresets ? '1' : '0';
        form.action = `/toernooi/${slug}`;
        form.submit();
    } else if (bevestig !== null) {
        alert('Naam komt niet overeen. Toernooi NIET verwijderd.');
    }
}
</script>
@endsection
