@extends('layouts.app')

@section('title', __('Havun Admin - Alle Organisatoren'))

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Havun Admin Dashboard') }}</h1>
        <p class="text-gray-500 mt-1">{{ __('Overzicht van alle klanten (organisatoren)') }}<br>{{ __('en hun toernooien') }}</p>
    </div>
    <div class="flex gap-4">
        <a href="{{ route('admin.klanten') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            {{ __('Klantenbeheer') }}
        </a>
        <a href="{{ route('admin.autofix') }}" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">
            AutoFix
            @php $afCount = \App\Models\AutofixProposal::where('created_at', '>=', now()->subDay())->count(); @endphp
            @if($afCount > 0)
                <span class="ml-1 bg-white text-gray-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $afCount }}</span>
            @endif
        </a>
        <a href="{{ route('organisator.dashboard', ['organisator' => Auth::guard('organisator')->user()->slug]) }}" class="text-blue-600 hover:text-blue-800 flex items-center">
            &larr; {{ __('Terug naar Dashboard') }}
        </a>
    </div>
</div>

{{-- KPI's voor Havun --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-3xl font-bold text-blue-600">{{ $organisatoren->where('is_sitebeheerder', false)->count() }}</div>
        <div class="text-gray-500 text-sm">{{ __('Klanten (organisatoren)') }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-3xl font-bold text-green-600">{{ $organisatoren->sum(fn($o) => $o->toernooien->count()) + $toernooienZonderOrganisator->count() }}</div>
        <div class="text-gray-500 text-sm">{{ __('Toernooien totaal') }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        <div class="text-3xl font-bold text-purple-600">{{ $organisatoren->sum(fn($o) => $o->toernooien->sum('judokas_count')) + $toernooienZonderOrganisator->sum('judokas_count') }}</div>
        <div class="text-gray-500 text-sm">{{ __('Judoka\'s verwerkt') }}</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4">
        @php
            $afgeslotenCount = $organisatoren->sum(fn($o) => $o->toernooien->whereNotNull('afgesloten_at')->count());
        @endphp
        <div class="text-3xl font-bold text-orange-600">{{ $afgeslotenCount }}</div>
        <div class="text-gray-500 text-sm">{{ __('Toernooien afgerond') }}</div>
    </div>
</div>

{{-- Widget 1: Omzet Overzicht --}}
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="bg-green-50 px-6 py-3 border-b">
        <h2 class="text-lg font-bold text-green-800">{{ __('Omzet Overzicht') }}</h2>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4">
        <div class="bg-green-50 rounded-lg p-3">
            <div class="text-2xl font-bold text-green-700">&euro;{{ number_format($omzetDezeMaand + $inschrijfgeldDezeMaand, 2, ',', '.') }}</div>
            <div class="text-xs text-gray-500">{{ __('Deze maand') }}</div>
            @if($omzetDezeMaand > 0 || $inschrijfgeldDezeMaand > 0)
            <div class="text-xs text-gray-400 mt-1">
                {{ __('Upgrades') }}: &euro;{{ number_format($omzetDezeMaand, 2, ',', '.') }} &middot;
                {{ __('Inschrijfgeld') }}: &euro;{{ number_format($inschrijfgeldDezeMaand, 2, ',', '.') }}
            </div>
            @endif
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-2xl font-bold text-gray-700">&euro;{{ number_format($omzetVorigeMaand, 2, ',', '.') }}</div>
            <div class="text-xs text-gray-500">{{ __('Vorige maand') }}</div>
            <div class="text-xs text-gray-400 mt-1">{{ __('Upgrades') }}</div>
        </div>
        <div class="bg-blue-50 rounded-lg p-3">
            <div class="text-2xl font-bold text-blue-700">&euro;{{ number_format($omzetTotaal + $inschrijfgeldTotaal, 2, ',', '.') }}</div>
            <div class="text-xs text-gray-500">{{ __('Totaal') }}</div>
            <div class="text-xs text-gray-400 mt-1">
                {{ __('Upgrades') }}: &euro;{{ number_format($omzetTotaal, 2, ',', '.') }} &middot;
                {{ __('Inschrijfgeld') }}: &euro;{{ number_format($inschrijfgeldTotaal, 2, ',', '.') }}
            </div>
        </div>
        <div class="bg-orange-50 rounded-lg p-3">
            <div class="text-2xl font-bold {{ $openBetalingen > 0 ? 'text-orange-700' : 'text-gray-400' }}">{{ $openBetalingen }}</div>
            <div class="text-xs text-gray-500">{{ __('Open betalingen') }}</div>
            @if($actieveAbos > 0)
            <div class="text-xs text-blue-600 mt-1">{{ $actieveAbos }} {{ __('wimpel abo\'s') }}</div>
            @endif
        </div>
    </div>
</div>

{{-- Widget 2 & 3: Vandaag/Binnenkort + Klant Gezondheid --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    {{-- Widget 2: Vandaag & Binnenkort --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-blue-50 px-6 py-3 border-b">
            <h2 class="text-lg font-bold text-blue-800">{{ __('Vandaag & Binnenkort') }}</h2>
        </div>
        <div class="p-4">
            @if($toernooienVandaag->count() > 0)
                <div class="mb-3">
                    <div class="text-xs font-semibold text-red-600 uppercase mb-1">{{ __('Vandaag') }}</div>
                    @foreach($toernooienVandaag as $t)
                    <div class="flex items-center justify-between py-1">
                        <div class="flex items-center gap-2">
                            <span class="px-1.5 py-0.5 bg-red-100 text-red-700 rounded text-xs animate-pulse font-bold">LIVE</span>
                            <span class="font-medium text-sm">{{ $t->naam }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $t->organisator?->naam }} &middot; {{ $t->judokas_count }} {{ __('judoka\'s') }}
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            @if($toernooienDezeWeek->count() > 0)
                <div class="mb-3">
                    <div class="text-xs font-semibold text-orange-600 uppercase mb-1">{{ __('Deze week') }}</div>
                    @foreach($toernooienDezeWeek as $t)
                    <div class="flex items-center justify-between py-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-orange-600 font-medium">{{ $t->datum->format('D d M') }}</span>
                            <span class="text-sm">{{ $t->naam }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $t->organisator?->naam }} &middot; {{ $t->judokas_count }} {{ __('judoka\'s') }}
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            @if($toernooienKomendeMaand->count() > 0)
                <div>
                    <div class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ __('Komende 30 dagen') }}</div>
                    @foreach($toernooienKomendeMaand as $t)
                    <div class="flex items-center justify-between py-1">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-500">{{ $t->datum->format('d M') }}</span>
                            <span class="text-sm">{{ $t->naam }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            {{ $t->organisator?->naam }} &middot; {{ $t->judokas_count }} {{ __('judoka\'s') }}
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            @if($toernooienVandaag->count() === 0 && $toernooienDezeWeek->count() === 0 && $toernooienKomendeMaand->count() === 0)
                <div class="text-sm text-gray-400 italic py-2">{{ __('Geen toernooien gepland') }}</div>
            @endif
        </div>
    </div>

    {{-- Widget 3: Klant Gezondheid --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="bg-purple-50 px-6 py-3 border-b">
            <h2 class="text-lg font-bold text-purple-800">{{ __('Klant Gezondheid') }}</h2>
        </div>
        <div class="grid grid-cols-2 gap-4 p-4">
            <div class="bg-green-50 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-green-700">{{ $klantenActief }}</div>
                <div class="text-xs text-gray-500">{{ __('Actief') }}</div>
                <div class="text-xs text-gray-400">{{ __('< 7 dagen') }}</div>
            </div>
            <div class="bg-yellow-50 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-yellow-700">{{ $klantenInactief }}</div>
                <div class="text-xs text-gray-500">{{ __('Inactief') }}</div>
                <div class="text-xs text-gray-400">7-30 {{ __('dagen') }}</div>
            </div>
            <div class="bg-red-50 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-red-700">{{ $klantenRisico }}</div>
                <div class="text-xs text-gray-500">{{ __('Risico') }}</div>
                <div class="text-xs text-gray-400">> 30 {{ __('dagen') }}</div>
            </div>
            <div class="bg-blue-50 rounded-lg p-3 text-center">
                <div class="text-2xl font-bold text-blue-700">{{ $klantenNieuw }}</div>
                <div class="text-xs text-gray-500">{{ __('Nieuw deze maand') }}</div>
            </div>
        </div>
    </div>
</div>

{{-- Widget 4: Recente Activiteit --}}
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="bg-gray-50 px-6 py-3 border-b">
        <h2 class="text-lg font-bold text-gray-800">{{ __('Recente Activiteit') }}</h2>
    </div>
    <div class="divide-y divide-gray-100">
        @forelse($recenteActiviteit as $log)
        <div class="px-6 py-2 flex items-center justify-between hover:bg-gray-50">
            <div class="flex items-center gap-3">
                @switch($log->interface)
                    @case('weging')
                        <span class="w-6 h-6 flex items-center justify-center bg-blue-100 text-blue-600 rounded text-xs" title="Weging">W</span>
                        @break
                    @case('mat')
                        <span class="w-6 h-6 flex items-center justify-center bg-green-100 text-green-600 rounded text-xs" title="Mat">M</span>
                        @break
                    @case('dashboard')
                        <span class="w-6 h-6 flex items-center justify-center bg-purple-100 text-purple-600 rounded text-xs" title="Dashboard">D</span>
                        @break
                    @case('portaal')
                        <span class="w-6 h-6 flex items-center justify-center bg-orange-100 text-orange-600 rounded text-xs" title="Portaal">P</span>
                        @break
                    @default
                        <span class="w-6 h-6 flex items-center justify-center bg-gray-100 text-gray-600 rounded text-xs">-</span>
                @endswitch
                <div>
                    <span class="text-sm font-medium">{{ $log->actor_naam ?? '-' }}</span>
                    <span class="text-sm text-gray-500">{{ $log->beschrijving }}</span>
                </div>
            </div>
            <div class="text-xs text-gray-400 whitespace-nowrap">
                @if($log->toernooi)
                    <span class="text-gray-500 mr-2">{{ $log->toernooi->naam }}</span>
                @endif
                {{ $log->created_at?->diffForHumans() }}
            </div>
        </div>
        @empty
        <div class="px-6 py-4 text-sm text-gray-400 italic">{{ __('Geen recente activiteit') }}</div>
        @endforelse
    </div>
</div>

{{-- Widget 5: Systeem Status --}}
<div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
    <div class="px-6 py-3 border-b {{ $autofixVandaag > 5 ? 'bg-red-50' : ($autofixVandaag > 0 ? 'bg-orange-50' : 'bg-green-50') }}">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-bold {{ $autofixVandaag > 5 ? 'text-red-800' : ($autofixVandaag > 0 ? 'text-orange-800' : 'text-green-800') }}">
                {{ __('Systeem Status') }}
            </h2>
            <a href="{{ route('admin.autofix') }}" class="text-sm text-blue-600 hover:text-blue-800">{{ __('AutoFix openen') }} &rarr;</a>
        </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4">
        <div class="text-center">
            <div class="text-2xl font-bold {{ $autofixVandaag > 5 ? 'text-red-600' : ($autofixVandaag > 0 ? 'text-orange-600' : 'text-green-600') }}">{{ $autofixVandaag }}</div>
            <div class="text-xs text-gray-500">{{ __('Errors vandaag') }}</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold {{ $autofixPending > 0 ? 'text-orange-600' : 'text-gray-400' }}">{{ $autofixPending }}</div>
            <div class="text-xs text-gray-500">{{ __('Pending fixes') }}</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ $autofixApplied }}</div>
            <div class="text-xs text-gray-500">{{ __('Gefixt vandaag') }}</div>
        </div>
        <div class="text-center">
            <div class="text-sm font-medium text-gray-600">
                {{ $laatsteError ? $laatsteError->diffForHumans() : __('Geen errors') }}
            </div>
            <div class="text-xs text-gray-500">{{ __('Laatste error') }}</div>
        </div>
    </div>
    <div class="px-4 pb-3 flex gap-4 text-xs text-gray-400">
        <span>PHP {{ PHP_VERSION }}</span>
        <span>Laravel {{ app()->version() }}</span>
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
                    <a href="{{ route('organisator.dashboard', ['organisator' => $organisator->slug]) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">→ {{ __('Open dashboard') }}</a>
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
                        <div class="text-xs text-gray-500">{{ __('Toernooien') }}</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-green-600">{{ $organisator->clubs_count ?? $organisator->clubs()->count() }}</div>
                        <div class="text-xs text-gray-500">{{ __('Clubs') }}</div>
                    </div>
                    <div>
                        <div class="text-2xl font-bold text-purple-600">{{ $organisator->toernooi_templates_count ?? $organisator->toernooiTemplates()->count() }}</div>
                        <div class="text-xs text-gray-500">{{ __('Templates') }}</div>
                    </div>
                </div>
            </div>
        </div>
        {{-- Extra info row --}}
        <div class="flex justify-between items-center mt-3 pt-3 border-t border-gray-200 text-sm">
            <div class="flex gap-6 text-gray-500">
                <span>{{ __('Klant sinds') }}: <strong>{{ $organisator->created_at?->format('d-m-Y') ?? '-' }}</strong></span>
                <span>{{ __('Laatste login') }}:
                    @if($organisator->laatste_login)
                        <strong class="{{ $organisator->laatste_login->diffInDays() > 30 ? 'text-orange-600' : 'text-green-600' }}">
                            {{ $organisator->laatste_login->diffForHumans() }}
                        </strong>
                    @else
                        <strong class="text-gray-400">{{ __('Nooit') }}</strong>
                    @endif
                </span>
            </div>
            <div class="flex gap-2">
                @php
                    $actief = $organisator->toernooien->whereNull('afgesloten_at')->count();
                    $afgerond = $organisator->toernooien->whereNotNull('afgesloten_at')->count();
                @endphp
                @if($actief > 0)
                    <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">{{ $actief }} {{ __('actief') }}</span>
                @endif
                @if($afgerond > 0)
                    <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">{{ $afgerond }} {{ __('afgerond') }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Toernooien tabel --}}
    @if($organisator->toernooien->count() > 0)
    <table class="min-w-full">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Toernooi') }}</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Datum') }}</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Judoka\'s') }}</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Poules') }}</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Status') }}</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Pakket') }}</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Laatst actief') }}</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Acties') }}</th>
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
                            <span class="ml-1 px-1.5 py-0.5 bg-red-100 text-red-700 rounded text-xs animate-pulse">{{ __('VANDAAG') }}</span>
                        @elseif($toernooi->datum->isFuture())
                            @php
                                $totalDagen = (int) now()->diffInDays($toernooi->datum);
                                $weken = (int) floor($totalDagen / 7);
                                $dagen = $totalDagen % 7;
                                $countdown = $weken . 'w ' . $dagen . 'd';

                                $urgentClass = $totalDagen <= 7
                                    ? 'px-1.5 py-0.5 bg-orange-100 text-orange-700 rounded'
                                    : ($totalDagen <= 30 ? 'text-orange-600' : 'text-gray-500');
                            @endphp
                            <span class="ml-1 text-xs {{ $urgentClass }}">{{ $countdown }}</span>
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
                        <span class="px-2 py-1 bg-gray-200 text-gray-600 rounded text-xs">{{ __('Afgerond') }}</span>
                    @elseif($toernooi->weegkaarten_gemaakt_op)
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">{{ __('Wedstrijddag') }}</span>
                    @elseif($toernooi->judokas_count > 0)
                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">{{ __('Voorbereiding') }}</span>
                    @else
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs">{{ __('Nieuw') }}</span>
                    @endif
                </td>
                <td class="px-6 py-3 whitespace-nowrap text-center">
                    @if($toernooi->isPaidTier())
                        <div class="inline-flex flex-col items-center">
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">€{{ $toernooi->toernooiBetaling?->bedrag ?? '?' }}</span>
                            <span class="text-xs text-gray-500">{{ __('max') }} {{ $toernooi->paid_max_judokas }}</span>
                        </div>
                    @else
                        <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs">{{ __('Gratis') }}</span>
                    @endif
                </td>
                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-500">
                    {{ $toernooi->updated_at?->diffForHumans() ?? '-' }}
                </td>
                <td class="px-6 py-3 whitespace-nowrap space-x-2">
                    <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800 text-sm">{{ __('Open') }}</a>
                    <button onclick="confirmDelete('{{ $organisator->slug }}', '{{ $toernooi->slug }}', '{{ addslashes($toernooi->naam) }}')" class="text-red-500 hover:text-red-700 text-sm">{{ __('Verwijder') }}</button>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="px-6 py-4 text-gray-500 text-sm italic">{{ __('Nog geen toernooien aangemaakt') }}</div>
    @endif
</div>
@endforeach

{{-- Sitebeheerders apart --}}
@if($organisatoren->where('is_sitebeheerder', true)->count() > 0)
<div class="mt-8 pt-8 border-t border-gray-300">
    <h2 class="text-lg font-semibold text-gray-600 mb-4">{{ __('Sitebeheerders (Havun)') }}</h2>
    @foreach($organisatoren->where('is_sitebeheerder', true) as $organisator)
    <div class="bg-purple-50 rounded-lg shadow mb-4 p-4">
        <div class="flex justify-between items-center">
            <div>
                <span class="text-purple-600 font-bold">👑 {{ $organisator->naam }}</span>
                <span class="text-gray-500 text-sm ml-2">{{ $organisator->email }}</span>
            </div>
            <div class="text-sm text-gray-500">
                {{ __('Laatste login') }}: {{ $organisator->laatste_login?->diffForHumans() ?? __('Nooit') }}
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
        <h2 class="text-lg font-bold text-orange-800">⚠️ {{ __('Toernooien zonder organisator') }} ({{ $toernooienZonderOrganisator->count() }})</h2>
        <div class="text-sm text-orange-600">{{ __('Deze toernooien hebben geen gekoppelde klant - mogelijk legacy data') }}</div>
    </div>
    <table class="min-w-full">
        <thead class="bg-gray-100">
            <tr>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Naam') }}</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Datum') }}</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Judoka\'s') }}</th>
                <th class="px-6 py-2 text-center text-xs font-medium text-gray-500 uppercase">{{ __('Poules') }}</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Laatst gebruikt') }}</th>
                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Acties') }}</th>
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
                    @if($toernooi->organisator)
                    <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800 text-sm">{{ __('Open') }}</a>
                    <button onclick="confirmDelete('{{ $toernooi->organisator->slug }}', '{{ $toernooi->slug }}', '{{ addslashes($toernooi->naam) }}')" class="text-red-500 hover:text-red-700 text-sm">{{ __('Verwijder') }}</button>
                    @else
                    <span class="text-gray-400 text-sm">{{ __('Geen organisator') }}</span>
                    @endif
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
function confirmDelete(orgSlug, slug, naam) {
    if (confirm(`🚨 VERWIJDER "${naam}" PERMANENT?\n\nDit verwijdert:\n• Alle judoka's\n• Alle poules en wedstrijden\n• Alle instellingen\n\nDIT KAN NIET ONGEDAAN WORDEN!`)) {
        const form = document.getElementById('delete-form');
        form.action = `/${orgSlug}/toernooi/${slug}`;
        form.submit();
    }
}
</script>
@endsection
