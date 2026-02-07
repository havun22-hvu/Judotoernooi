@extends('layouts.app')

@section('title', __('Bewerk :naam', ['naam' => $judoka->naam]))

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">{{ __('Judoka Bewerken') }}</h1>

    <form action="{{ route('toernooi.judoka.update', $toernooi->routeParamsWith(['judoka' => $judoka])) }}" method="POST" class="bg-white rounded-lg shadow p-6">
        @csrf
        @method('PUT')
        @if(request('filter'))
        <input type="hidden" name="filter" value="{{ request('filter') }}">
        @endif

        <div class="mb-4">
            <label for="naam" class="block text-gray-700 font-bold mb-2">{{ __('Naam') }} *</label>
            <input type="text" name="naam" id="naam" value="{{ old('naam', $judoka->naam) }}"
                   class="w-full border rounded px-3 py-2" required>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label for="geboortejaar" class="block text-gray-700 font-bold mb-2">{{ __('Geboortejaar') }} *</label>
                <input type="number" name="geboortejaar" id="geboortejaar" value="{{ old('geboortejaar', $judoka->geboortejaar) }}"
                       class="w-full border rounded px-3 py-2" required>
            </div>
            <div>
                <label for="geslacht" class="block text-gray-700 font-bold mb-2">{{ __('Geslacht') }} *</label>
                <select name="geslacht" id="geslacht" class="w-full border rounded px-3 py-2" required>
                    <option value="M" {{ $judoka->geslacht === 'M' ? 'selected' : '' }}>{{ __('Man') }}</option>
                    <option value="V" {{ $judoka->geslacht === 'V' ? 'selected' : '' }}>{{ __('Vrouw') }}</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
                <label for="band" class="block text-gray-700 font-bold mb-2">{{ __('Band') }} *</label>
                <select name="band" id="band" class="w-full border rounded px-3 py-2" required>
                    @foreach(['wit', 'geel', 'oranje', 'groen', 'blauw', 'bruin', 'zwart'] as $b)
                    <option value="{{ $b }}" {{ $judoka->band === $b ? 'selected' : '' }}>{{ ucfirst($b) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="gewicht" class="block text-gray-700 font-bold mb-2">{{ __('Gewicht (kg)') }}</label>
                <input type="number" name="gewicht" id="gewicht" value="{{ old('gewicht', $judoka->gewicht) }}"
                       class="w-full border rounded px-3 py-2" step="0.1">
            </div>
        </div>

        @php
            $terugUrl = route('toernooi.judoka.index', $toernooi->routeParams());
            if (request('filter') === 'onvolledig') {
                $terugUrl .= '#onvolledig';
            }
        @endphp
        <div class="flex justify-end space-x-4">
            <a href="{{ $terugUrl }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded"
               @if(request('filter') === 'onvolledig') onclick="sessionStorage.setItem('toonOnvolledig', 'true')" @endif>
                {{ __('Annuleren') }}
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Opslaan') }}
            </button>
        </div>
    </form>
</div>
@endsection
