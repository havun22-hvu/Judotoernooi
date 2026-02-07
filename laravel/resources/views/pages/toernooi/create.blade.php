@extends('layouts.app')

@section('title', __('Nieuw Toernooi'))

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">{{ __('Nieuw Toernooi Aanmaken') }}</h1>

    <form action="{{ route('toernooi.store', ['organisator' => $organisator]) }}" method="POST" class="bg-white rounded-lg shadow p-6">
        @csrf

        @if($templates->isNotEmpty())
        <div class="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <label for="template_id" class="block text-gray-700 font-bold mb-2">{{ __('Template gebruiken') }}</label>
            <select name="template_id" id="template_id" class="w-full border rounded px-3 py-2">
                <option value="">-- {{ __('Geen template (leeg toernooi)') }} --</option>
                @foreach($templates as $template)
                <option value="{{ $template->id }}" {{ old('template_id') == $template->id ? 'selected' : '' }}>
                    {{ $template->naam }}
                    @if($template->beschrijving) - {{ Str::limit($template->beschrijving, 40) }}@endif
                    @if($template->max_judokas) (max {{ $template->max_judokas }} judoka's)@endif
                </option>
                @endforeach
            </select>
            <p class="text-sm text-gray-600 mt-1">{{ __('Een template kopieert alle instellingen zoals categorieën, gewichtsklassen en betaalinstellingen.') }}</p>
        </div>
        @endif

        <div class="mb-4">
            <label for="naam" class="block text-gray-700 font-bold mb-2">{{ __('Naam Toernooi') }} *</label>
            <input type="text" name="naam" id="naam" value="{{ old('naam') }}" placeholder="Bijv. Judo Toernooi 2025"
                   class="w-full border rounded px-3 py-2 @error('naam') border-red-500 @enderror" required>
            @error('naam')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="datum" class="block text-gray-700 font-bold mb-2">{{ __('Datum') }} *</label>
            <input type="date" name="datum" id="datum" value="{{ old('datum', date('Y-m-d')) }}"
                   class="w-full border rounded px-3 py-2 @error('datum') border-red-500 @enderror" required>
            @error('datum')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-4">
            <label for="organisatie" class="block text-gray-700 font-bold mb-2">{{ __('Organisatie') }}</label>
            <input type="text" name="organisatie" id="organisatie" value="{{ old('organisatie') }}" placeholder="{{ __('Naam van de organiserende club') }}"
                   class="w-full border rounded px-3 py-2">
        </div>

        <div class="mb-6">
            <label for="locatie" class="block text-gray-700 font-bold mb-2">{{ __('Locatie') }}</label>
            <x-location-autocomplete name="locatie" :value="old('locatie')" placeholder="{{ __('Zoek sporthal of adres...') }}" />
            <p class="text-gray-500 text-sm mt-1">{{ __('Typ om te zoeken, klik op Route voor navigatie') }}</p>
        </div>

        <div class="flex justify-end space-x-4">
            <a href="{{ route('organisator.dashboard', ['organisator' => Auth::guard('organisator')->user()->slug]) }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                {{ __('Annuleren') }}
            </a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Toernooi Aanmaken') }}
            </button>
        </div>
    </form>
</div>
@endsection
