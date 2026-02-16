@extends('layouts.app')

@section('title', __('Activiteiten Log'))

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">{{ __('Activiteiten Log') }}</h1>
        <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:underline">
            &larr; {{ __('Terug naar overzicht') }}
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Actie') }}</label>
            <select name="actie" class="border-gray-300 rounded-md text-sm">
                <option value="">{{ __('Alle acties') }}</option>
                @foreach($acties as $actie)
                    <option value="{{ $actie }}" @selected(request('actie') === $actie)>
                        {{ ucfirst(str_replace('_', ' ', $actie)) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Model') }}</label>
            <select name="model_type" class="border-gray-300 rounded-md text-sm">
                <option value="">{{ __('Alle types') }}</option>
                @foreach($modelTypes as $type)
                    <option value="{{ $type }}" @selected(request('model_type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Zoeken') }}</label>
            <input type="text" name="zoek" value="{{ request('zoek') }}" placeholder="{{ __('Zoek in beschrijving...') }}"
                   class="border-gray-300 rounded-md text-sm w-full">
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700">
                {{ __('Filteren') }}
            </button>
            @if(request()->hasAny(['actie', 'model_type', 'zoek']))
                <a href="{{ route('toernooi.activiteiten', $toernooi->routeParams()) }}"
                   class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm hover:bg-gray-300">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>

    {{-- Results --}}
    @if($logs->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            {{ __('Nog geen activiteiten gelogd.') }}
        </div>
    @else
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Datum/tijd') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Actie') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Beschrijving') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Wie') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Interface') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" x-data>
                    @foreach($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                            {{ $log->created_at->format('d-m H:i:s') }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                @switch($log->actie)
                                    @case('verplaats_judoka')
                                    @case('naar_wachtruimte')
                                    @case('verwijder_uit_poule')
                                        bg-yellow-100 text-yellow-800
                                        @break
                                    @case('registreer_uitslag')
                                    @case('registreer_gewicht')
                                        bg-green-100 text-green-800
                                        @break
                                    @case('meld_af')
                                    @case('markeer_afwezig')
                                    @case('verwijder_poule')
                                    @case('verwijder_toernooi')
                                        bg-red-100 text-red-800
                                        @break
                                    @case('reset_alles')
                                    @case('reset_blok')
                                    @case('reset_categorie')
                                        bg-orange-100 text-orange-800
                                        @break
                                    @default
                                        bg-blue-100 text-blue-800
                                @endswitch
                            ">
                                {{ $log->actie_naam }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            {{ $log->beschrijving }}
                            @if($log->model_type)
                                <span class="text-gray-400 text-xs">({{ $log->model_type }} #{{ $log->model_id }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                            {{ $log->actor_naam }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                            {{ $log->interface ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            @if($log->properties)
                                @php $props = $log->properties; @endphp
                                <div class="flex flex-wrap gap-1.5">
                                    @if(!empty($props['blok']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-purple-100 text-purple-700 text-xs font-medium">B{{ $props['blok'] }}</span>
                                    @endif
                                    @if(!empty($props['mat']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-100 text-indigo-700 text-xs font-medium">M{{ $props['mat'] }}</span>
                                    @endif
                                    @if(isset($props['score_wit']) && isset($props['score_blauw']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs">{{ $props['score_wit'] ?? 0 }}-{{ $props['score_blauw'] ?? 0 }}</span>
                                    @endif
                                    @if(!empty($props['groep']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-orange-100 text-orange-700 text-xs">{{ $props['groep'] }}</span>
                                    @endif
                                    @if(!empty($props['ronde']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-600 text-xs">{{ str_replace('_', ' ', $props['ronde']) }}</span>
                                    @endif
                                    @if(!empty($props['positie']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-blue-100 text-blue-700 text-xs">{{ $props['positie'] }}</span>
                                    @endif
                                    @if(!empty($props['is_correctie']))
                                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-red-100 text-red-700 text-xs">correctie</span>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    @endif
</div>
@endsection
