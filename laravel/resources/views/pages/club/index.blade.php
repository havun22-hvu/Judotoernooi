@extends('layouts.app')

@section('title', 'Clubs uitnodigen')

@section('content')
<div x-data="{ copiedUrl: null }">

<div class="flex justify-between items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Clubs Uitnodigen</h1>
        <p class="text-gray-600 mt-1">Selecteer welke clubs je wilt uitnodigen voor dit toernooi</p>
        <div class="flex gap-2 mt-2">
            <form action="{{ route('toernooi.club.select-all', $toernooi->routeParams()) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-sm bg-green-100 hover:bg-green-200 text-green-700 px-3 py-1 rounded">
                    ✓ Alles aan
                </button>
            </form>
            <form action="{{ route('toernooi.club.deselect-all', $toernooi->routeParams()) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="text-sm bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded">
                    ✗ Alles uit
                </button>
            </form>
        </div>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('organisator.clubs.index', [$organisator, 'back' => url()->current()]) }}"
           class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded flex items-center gap-2">
            Clubs Beheren
        </a>
        <form action="{{ route('toernooi.coach-kaart.genereer', $toernooi->routeParams()) }}" method="POST" class="inline"
              onsubmit="return confirm('Coachkaarten genereren voor alle geselecteerde clubs?')">
            @csrf
            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
                Genereer Coachkaarten
            </button>
        </form>
        <form action="{{ route('toernooi.club.verstuur-alle', $toernooi->routeParams()) }}" method="POST" class="inline"
              onsubmit="return confirm('Alle geselecteerde clubs met email uitnodigen?')">
            @csrf
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                Alle Uitnodigen
            </button>
        </form>
    </div>
</div>

@if(session('success'))
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
    {{ session('error') }}
</div>
@endif

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

@if($clubs->isEmpty())
<div class="bg-white rounded-lg shadow p-8 text-center">
    <p class="text-gray-500 mb-4">Je hebt nog geen clubs in je clublijst.</p>
    <a href="{{ route('organisator.clubs.index', [$organisator, 'back' => url()->current()]) }}"
       class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
        Clubs Toevoegen
    </a>
</div>
@else

<!-- Clubs tabel -->
<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="w-full">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 w-16">Actief</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Club</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Plaats</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Email</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Judoka's</th>
                <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Coach Portal</th>
                <th class="px-4 py-3 text-right text-sm font-semibold text-gray-600">Email</th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @foreach($clubs->sortBy('naam') as $club)
            @php
                $isUitgenodigd = in_array($club->id, $uitgenodigdeClubIds);
                $portalUrl = $club->getPortalUrl($toernooi);
                $pivotPincode = $uitgenodigdeClubs[$club->id]->pivot->pincode ?? null;
                $heeftJudokas = $club->judokas_count > 0;
                $kanUitschakelen = !$heeftJudokas;
            @endphp
            <tr class="hover:bg-gray-50 {{ $isUitgenodigd ? 'bg-green-50' : '' }}">
                <td class="px-4 py-3">
                    <form action="{{ route('toernooi.club.toggle', $toernooi->routeParamsWith(['club' => $club])) }}" method="POST"
                          @if($isUitgenodigd && !$kanUitschakelen)
                          onsubmit="alert('Kan {{ $club->naam }} niet deselecteren: er zijn nog {{ $club->judokas_count }} judoka\'s ingeschreven.'); return false;"
                          @endif>
                        @csrf
                        <button type="submit" class="w-6 h-6 rounded border-2 flex items-center justify-center transition-colors
                            {{ $isUitgenodigd ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-green-400' }}">
                            @if($isUitgenodigd)
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            @endif
                        </button>
                    </form>
                </td>
                <td class="px-4 py-3">
                    <span class="font-medium text-gray-800">{{ $club->naam }}</span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $club->plaats ?? '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $club->email ?? '-' }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 text-xs {{ $club->judokas_count > 0 ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' }} rounded">
                        {{ $club->judokas_count }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    @if($isUitgenodigd)
                    <div class="space-y-1">
                        <div class="flex items-center gap-1">
                            <code class="text-xs bg-gray-100 px-1 py-0.5 rounded text-gray-600 max-w-[180px] truncate" title="{{ $portalUrl }}">
                                {{ $portalUrl }}
                            </code>
                            <button @click="navigator.clipboard.writeText('{{ $portalUrl }}'); copiedUrl = 'url-{{ $club->id }}'; setTimeout(() => copiedUrl = null, 2000)"
                                    class="px-1.5 py-0.5 text-xs rounded flex-shrink-0"
                                    :class="copiedUrl === 'url-{{ $club->id }}' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-600 hover:bg-blue-200'"
                                    title="Kopieer URL">
                                <span x-text="copiedUrl === 'url-{{ $club->id }}' ? '~' : '~'"></span>
                            </button>
                        </div>
                        <div class="flex items-center gap-1">
                            <span class="text-xs font-mono bg-amber-50 px-1.5 py-0.5 rounded text-amber-800">PIN: {{ $pivotPincode }}</span>
                            <button @click="navigator.clipboard.writeText('{{ $pivotPincode }}'); copiedUrl = 'pin-{{ $club->id }}'; setTimeout(() => copiedUrl = null, 2000)"
                                    class="px-1.5 py-0.5 text-xs rounded flex-shrink-0"
                                    :class="copiedUrl === 'pin-{{ $club->id }}' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700 hover:bg-amber-200'"
                                    title="Kopieer PIN">
                                <span x-text="copiedUrl === 'pin-{{ $club->id }}' ? '~' : '~'"></span>
                            </button>
                        </div>
                    </div>
                    @else
                    <span class="text-gray-400 text-sm">Eerst selecteren</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-right">
                    @if($isUitgenodigd && $club->email)
                    <form action="{{ route('toernooi.club.verstuur', $toernooi->routeParamsWith(['club' => $club])) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-3 py-1 text-sm bg-green-100 text-green-700 hover:bg-green-200 rounded">
                            Verstuur
                        </button>
                    </form>
                    @elseif(!$club->email)
                    <span class="text-gray-400 text-sm">Geen email</span>
                    @else
                    <span class="text-gray-400 text-sm">-</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="mt-4 text-sm text-gray-500">
    <strong>{{ count($uitgenodigdeClubIds) }}</strong> van <strong>{{ $clubs->count() }}</strong> clubs geselecteerd voor dit toernooi
</div>

@endif

</div>
@endsection
