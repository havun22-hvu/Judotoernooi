@extends('layouts.app')

@section('title', __('Eliminatie brackets'))

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">
                {{ __('Eliminatie brackets') }}
                <span class="text-base font-normal text-gray-500">
                    — {{ $modus === 'voorbereiding' ? __('Voorbereiding') : __('Wedstrijddag') }}
                </span>
            </h1>
            <p class="text-gray-600 mt-1">
                @if ($modus === 'voorbereiding')
                    {{ __('Lege bracket-templates op maat. Startposities en live komen pas in beeld op de wedstrijddag.') }}
                @else
                    {{ __('Startposities en live snapshots per eliminatie-poule. Gebruik na de weging.') }}
                @endif
            </p>
        </div>
        <a href="{{ route('toernooi.noodplan.index', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800">
            &larr; {{ __('Terug naar Noodplan') }}
        </a>
    </div>

    {{-- Leeg-op-maat invul-formulier (altijd zichtbaar) --}}
    <form method="GET" action="#"
          x-data="{ aantal: 8 }"
          @submit.prevent="window.open('{{ rtrim(route('toernooi.noodplan.bracket-leeg', array_merge($toernooi->routeParams(), ['aantal' => 0])), '0') }}' + aantal, '_blank')"
          class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <h2 class="font-bold text-blue-800 mb-2">{{ __('Leeg bracket op maat') }}</h2>
        <p class="text-sm text-blue-700 mb-3">{{ __('Print een leeg bracket-template voor het opgegeven aantal judoka\'s.') }}</p>
        <div class="flex items-center gap-3">
            <label class="text-sm text-blue-800">{{ __('Aantal judoka\'s') }}:</label>
            <input type="number" min="2" max="64" x-model.number="aantal"
                   class="w-20 border border-blue-300 rounded px-2 py-1 text-sm focus:ring-1 focus:ring-blue-500">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded text-sm font-medium">
                {{ __('Print leeg bracket') }}
            </button>
        </div>
    </form>

    {{-- Per-poule lijst (alleen op wedstrijddag) --}}
    @if ($modus === 'wedstrijddag')
        @if ($poules->isEmpty())
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center text-gray-600">
                {{ __('Geen eliminatie-poules gevonden.') }}
            </div>
        @else
            <div class="bg-white border rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="text-left px-3 py-2">{{ __('Blok') }}</th>
                            <th class="text-left px-3 py-2">{{ __('Poule') }}</th>
                            <th class="text-left px-3 py-2">{{ __('Mat') }}</th>
                            <th class="text-left px-3 py-2">{{ __('Judoka\'s') }}</th>
                            <th class="text-right px-3 py-2">{{ __('Print') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($poules as $poule)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2">{{ $poule->blok?->nummer ?? '-' }}</td>
                                <td class="px-3 py-2 font-medium">{{ $poule->getDisplayTitel() }}</td>
                                <td class="px-3 py-2">{{ $poule->mat?->nummer ?? '-' }}</td>
                                <td class="px-3 py-2">{{ $poule->judokas->count() }}</td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ route('toernooi.noodplan.bracket-startposities', array_merge($toernooi->routeParams(), ['poule' => $poule->id])) }}"
                                       target="_blank"
                                       class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 px-3 py-1 rounded text-xs mr-1">
                                        {{ __('Startposities') }}
                                    </a>
                                    <a href="{{ route('toernooi.noodplan.bracket-live', array_merge($toernooi->routeParams(), ['poule' => $poule->id])) }}"
                                       target="_blank"
                                       class="inline-block bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs">
                                        {{ __('Live') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
@endsection
