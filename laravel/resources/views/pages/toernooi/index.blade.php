@extends('layouts.app')

@section('title', 'Havun Admin - Alle Organisatoren')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Havun Admin Dashboard</h1>
        <p class="text-gray-500 mt-1">Overzicht van alle klanten (organisatoren) en hun toernooien</p>
    </div>
    <div class="flex gap-4">
        <a href="{{ route('admin.klanten') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Klantenbeheer
        </a>
        <a href="{{ route('organisator.dashboard', ['organisator' => Auth::guard('organisator')->user()->slug]) }}" class="text-blue-600 hover:text-blue-800 flex items-center">
            &larr; Terug naar Dashboard
        </a>
    </div>
</div>

{{-- KPI's voor Havun --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-3xl font-bold text-blue-600">{{ $organisatoren->where('is_sitebeheerder', false)->count() }}</div>
        <div class="text-gray-500 text-sm">Klanten (organisatoren)</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-3xl font-bold text-green-600">{{ $organisatoren->sum(fn($o) => $o->toernooien->count()) + $toernooienZonderOrganisator->count() }}</div>
        <div class="text-gray-500 text-sm">Toernooien totaal</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-3xl font-bold text-purple-600">{{ $organisatoren->sum(fn($o) => $o->toernooien->sum('judokas_count')) + $toernooienZonderOrganisator->sum('judokas_count') }}</div>
        <div class="text-gray-500 text-sm">Judoka's verwerkt</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        @php
            $afgeslotenCount = $organisatoren->sum(fn($o) => $o->toernooien->whereNotNull('afgesloten_at')->count());
        @endphp
        <div class="text-3xl font-bold text-orange-600">{{ $afgeslotenCount }}</div>
        <div class="text-gray-500 text-sm">Toernooien afgerond</div>
    </div>
</div>

{{-- Organisatoren met toernooien --}}
@foreach($organisatoren->where('is_sitebeheerder', false) as $organisator)
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    {{-- Organisator header --}}
    <div class="bg-gray-50 px-6 py-4 border-b">
        <div class="flex justify-between items-start">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-800">{{ $organisator->naam }}</h2>
                    <a href="{{ route('organisator.dashboard', ['organisator' => $organisator->slug]) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">→ Open dashboard</a>
                </div>
                <div class="text-sm text-gray-500 mt-1">
                    <span class="inline-flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        {{ $organisator->email }}
                    </span>
                    @if($organisator->telefoon)
                    <span class="ml-4 inline-flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        {{ $organisator->telefoon }}
                    </span>
                    @endif
                </div>
            </div>
            <div class="text-right">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-2xl font-bold text-blue-600">{{ $organisator->toernooien->count() }}</div>
                        <div class="text-xs text-gray-500">Toernooien</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">{{ $organisator->clubs_count ?? $organisator->clubs()->count() }}</div>
                        <div class="text-xs text-gray-500">Clubs</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-purple-600">{{ $organisator->toernooi_templates_count ?? $organisator->toernooiTemplates()->count() }}</div>
                        <div class="text-xs text-gray-500">Templates</div>
                    </div>
                </div>
            </div>
        </div>
        {{-- Extra info row --}}
        <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200 text-sm">
            <div class="flex gap-6 text-gray-500">
                <span>Klant sinds: <strong>{{ $organisator->created_at?->format('d-m-Y') ?? '-' }}</strong></span>
                <span>Laatste login:
                    @if($organisator->laatste_login)
                        <strong class="{{ $organisator->laatste_login->diffInDays() > 30 ? 'text-orange-600' : 'text-green-600' }}">
                            {{ $organisator->laatste_login->diffForHumans() }}
                        </strong>
                    @else
                        <strong class="text-gray-400">Nooit</strong>
                    @endif
                </span>
            </div>
            <div class="flex gap-2">
                @php
                    $actief = $organisator->toernooien->whereNull('afgesloten_at')->count();
                    $afgerond = $organisator->toernooien->whereNotNull('afgesloten_at')->count();
                @endphp
                @if($actief > 0)
                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">{{ $actief }} actief</span>
                @endif
                @if($afgerond > 0)
                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">{{ $afgerond }} afgerond</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Toernooien tabel --}}
    @if($organisator->toernooien->count() > 0)
    <table class="min-w-full">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Toernooi</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Poules</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Laatst actief</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Acties</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach($organisator->toernooien->sortByDesc('datum') as $toernooi)
            <tr class="hover:bg-gray-50 {{ $toernooi->afgesloten_at ? 'bg-gray-50 opacity-75' : '' }}">
                <td class="px-6 py-3 whitespace-nowrap">
                    <div class="font-medium">{{ $toernooi->naam }}</div>
                    @if($toernooi->organisatie && $toernooi->organisatie !== $organisator->naam)
                        <div class="text-xs text-gray-400">{{ $toernooi->organisatie }}</div>
                    @endif
                </td>
                <td class="px-6 py-3 whitespace-nowrap text-sm">
                    @if($toernooi->datum)
                        <span class="{{ $toernooi->datum->isPast() ? 'text-gray-500' : 'text-blue-600 font-medium' }}">
                            {{ $toernooi->datum->format('d-m-Y') }}
                        </span>
                        @if($toernooi->datum->isToday())
                            <span class="ml-1 px-1.5 py-0.5 bg-red-100 text-red-700 rounded text-xs animate-pulse">VANDAAG</span>
                        @elseif($toernooi->datum->isFuture() && $toernooi->datum->diffInDays() <= 7)
                            <span class="ml-1 px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded text-xs">{{ $toernooi->datum->diffInDays() }}d</span>
                        @endif
                    @else
                        <span class="text-gray-400">-</span>
                    @endif
                </td>
                <td class="px-6 py-3 whitespace-nowrap text-center">
                    <span class="font-medium">{{ $toernooi->judokas_count }}</span>
                </td>
                <td class="px-6 py-3 whitespace-nowrap text-center text-sm">{{ $toernooi->poules_count }}</td>
                <td class="px-6 py-3 whitespace-nowrap text-center">
                    @if($toernooi->afgesloten_at)
                        <span class="px-2 py-1 bg-gray-200 text-gray-600 rounded text-xs">Afgerond</span>
                    @elseif($toernooi->weegkaarten_gemaakt_op)
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Wedstrijddag</span>
                    @elseif($toernooi->judokas_count > 0)
                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">Voorbereiding</span>
                    @else
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">Nieuw</span>
                    @endif
                </td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                    {{ $toernooi->updated_at?->diffForHumans() ?? '-' }}
                </td>
                <td class="px-6 py-3 whitespace-nowrap space-x-2">
                    <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800 text-sm">Open</a>
                    <button onclick="confirmDelete('{{ $toernooi->slug }}', '{{ addslashes($toernooi->naam) }}')" class="text-red-500 hover:text-red-700 text-sm">Verwijder</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="px-6 py-4 text-gray-500 text-sm italic">Nog geen toernooien aangemaakt</div>
    @endif
</div>
@endforeach

{{-- Sitebeheerders apart --}}
@if($organisatoren->where('is_sitebeheerder', true)->count() > 0)
<div class="mt-8 pt-8 border-t border-gray-300">
    <h2 class="text-lg font-semibold text-gray-600 mb-4">Sitebeheerders (Havun)</h2>
    @foreach($organisatoren->where('is_sitebeheerder', true) as $organisator)
    <div class="bg-purple-50 rounded-lg shadow mb-4 p-4">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-purple-600 font-bold">👑 {{ $organisator->naam }}</span>
                <span class="text-gray-500 text-sm ml-2">{{ $organisator->email }}</span>
            </div>
            <div class="text-sm text-gray-500">
                Laatste login: {{ $organisator->laatste_login?->diffForHumans() ?? 'Nooit' }}
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- Toernooien zonder organisator --}}
@if($toernooienZonderOrganisator->count() > 0)
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden mt-8">
    <div class="bg-orange-50 px-6 py-4 border-b">
        <h2 class="text-lg font-bold text-orange-800">⚠️ Toernooien zonder organisator ({{ $toernooienZonderOrganisator->count() }})</h2>
        <div class="text-sm text-orange-600">Deze toernooien hebben geen gekoppelde klant - mogelijk legacy data</div>
    </div>
    <table class="min-w-full">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Judoka's</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">Poules</th>
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
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">{{ $toernooi->updated_at?->diffForHumans() ?? '-' }}</td>
                <td class="px-6 py-3 whitespace-nowrap space-x-2">
                    <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800 text-sm">Open</a>
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
