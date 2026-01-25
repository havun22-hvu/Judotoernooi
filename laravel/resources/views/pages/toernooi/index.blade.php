@extends('layouts.app')

@section('title', 'Alle Toernooien - Sitebeheer')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Sitebeheer - Alle Toernooien</h1>
        <p class="text-gray-500 mt-1">Overzicht van alle organisatoren en hun toernooien</p>
    </div>
</div>

{{-- Statistieken --}}
<div class="grid grid-cols-3 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-3xl font-bold text-blue-600">{{ $organisatoren->count() }}</div>
        <div class="text-gray-500 text-sm">Organisatoren</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-3xl font-bold text-green-600">{{ $organisatoren->sum(fn($o) => $o->toernooien->count()) + $toernooienZonderOrganisator->count() }}</div>
        <div class="text-gray-500 text-sm">Toernooien totaal</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-3xl font-bold text-purple-600">{{ $organisatoren->sum(fn($o) => $o->toernooien->sum('judokas_count')) + $toernooienZonderOrganisator->sum('judokas_count') }}</div>
        <div class="text-gray-500 text-sm">Judoka's totaal</div>
    </div>
</div>

{{-- Organisatoren met toernooien --}}
@foreach($organisatoren as $organisator)
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    {{-- Organisator header --}}
    <div class="bg-gray-50 px-6 py-4 border-b flex justify-between items-center">
        <div>
            <h2 class="text-lg font-bold text-gray-800">
                @if($organisator->isSitebeheerder())
                    <span class="text-purple-600">👑</span>
                @endif
                {{ $organisator->naam }}
            </h2>
            <div class="text-sm text-gray-500">
                {{ $organisator->email }}
                @if($organisator->isSitebeheerder())
                    <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs">Sitebeheerder</span>
                @endif
            </div>
        </div>
        <div class="text-right text-sm text-gray-500">
            <div>{{ $organisator->toernooien->count() }} toernooi{{ $organisator->toernooien->count() != 1 ? 'en' : '' }}</div>
            <div>Aangemaakt: {{ $organisator->created_at?->format('d-m-Y') ?? '-' }}</div>
        </div>
    </div>

    {{-- Toernooien tabel --}}
    @if($organisator->toernooien->count() > 0)
    <table class="min-w-full">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Poules</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aangemaakt</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Laatst gebruikt</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acties</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach($organisator->toernooien as $toernooi)
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-3 whitespace-nowrap font-medium">{{ $toernooi->naam }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600">{{ $toernooi->datum?->format('d-m-Y') ?? '-' }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-center text-sm">{{ $toernooi->judokas_count }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-center text-sm">{{ $toernooi->poules_count }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">{{ $toernooi->created_at?->format('d-m-Y H:i') ?? '-' }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">{{ $toernooi->updated_at?->diffForHumans() ?? '-' }}</td>
                <td class="px-6 py-3 whitespace-nowrap space-x-2">
                    <a href="{{ route('toernooi.show', $toernooi) }}" class="text-blue-600 hover:text-blue-800 text-sm">Open</a>
                    <button onclick="confirmDelete('{{ $toernooi->slug }}', '{{ addslashes($toernooi->naam) }}')" class="text-red-500 hover:text-red-700 text-sm">Verwijder</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="px-6 py-4 text-gray-500 text-sm italic">Geen toernooien</div>
    @endif
</div>
@endforeach

{{-- Toernooien zonder organisator --}}
@if($toernooienZonderOrganisator->count() > 0)
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="bg-orange-50 px-6 py-4 border-b">
        <h2 class="text-lg font-bold text-orange-800">⚠️ Toernooien zonder organisator</h2>
        <div class="text-sm text-orange-600">Deze toernooien hebben geen gekoppelde organisator</div>
    </div>
    <table class="min-w-full">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Poules</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aangemaakt</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Laatst gebruikt</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acties</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach($toernooienZonderOrganisator as $toernooi)
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-3 whitespace-nowrap font-medium">{{ $toernooi->naam }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600">{{ $toernooi->datum?->format('d-m-Y') ?? '-' }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-center text-sm">{{ $toernooi->judokas_count }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-center text-sm">{{ $toernooi->poules_count }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">{{ $toernooi->created_at?->format('d-m-Y H:i') ?? '-' }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">{{ $toernooi->updated_at?->diffForHumans() ?? '-' }}</td>
                <td class="px-6 py-3 whitespace-nowrap space-x-2">
                    <a href="{{ route('toernooi.show', $toernooi) }}" class="text-blue-600 hover:text-blue-800 text-sm">Open</a>
                    <button onclick="confirmDelete('{{ $toernooi->slug }}', '{{ addslashes($toernooi->naam) }}')" class="text-red-500 hover:text-red-700 text-sm">Verwijder</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<!-- Hidden form for delete -->
<form id="delete-form" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
    <input type="hidden" name="bewaar_presets" id="bewaar-presets" value="0">
</form>

<script>
function confirmDelete(slug, naam) {
    if (confirm(`🚨 VERWIJDER "${naam}" PERMANENT?\n\nDit verwijdert:\n• Alle judoka's\n• Alle poules en wedstrijden\n• Alle instellingen\n\nDIT KAN NIET ONGEDAAN WORDEN!`)) {
        const form = document.getElementById('delete-form');
        form.action = `/toernooi/${slug}`;
        form.submit();
    }
}
</script>
@endsection
