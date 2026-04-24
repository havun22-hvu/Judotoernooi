{{--
    Location Autocomplete Component using OpenStreetMap Nominatim
    Usage: <x-location-autocomplete name="locatie" :value="$toernooi->locatie" />
--}}
@props(['name' => 'locatie', 'value' => '', 'placeholder' => null])

<div x-data="locationAutocomplete" data-initial-query="{{ addslashes($value) }}" class="relative">
    <div class="flex gap-2">
        <div class="flex-1 relative">
            <input type="text"
                   name="{{ $name }}"
                   id="{{ $name }}"
                   x-model="query"
                   @input.debounce.300ms="search()"
                   @focus="onFocus()"
                   @click.outside="hideResults()"
                   placeholder="{{ $placeholder ?? __('Zoek adres...') }}"
                   autocomplete="off"
                   {{ $attributes->merge(['class' => 'w-full border rounded px-3 py-2 pr-10']) }}>
            {{-- Loading spinner --}}
            <div x-show="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
                <svg class="animate-spin h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>
        {{-- Route button --}}
        <a x-show="hasQuery"
           x-cloak
           :href="routeUrl"
           target="_blank"
           class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded flex items-center gap-1 text-sm whitespace-nowrap"
           title="{{ __('Route plannen') }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            {{ __('Route') }}
        </a>
    </div>

    {{-- Autocomplete results --}}
    <div x-show="hasResults"
         x-cloak
         class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-60 overflow-y-auto">
        <template x-for="(result, index) in results" :key="index">
            <button type="button"
                    @click="selectResult(result)"
                    class="w-full px-4 py-2 text-left hover:bg-blue-50 border-b last:border-b-0 text-sm">
                <span x-text="result.display_name" class="block truncate"></span>
            </button>
        </template>
    </div>

    {{-- No results message --}}
    <div x-show="noResults"
         x-cloak
         class="absolute z-50 w-full mt-1 bg-white border rounded-lg shadow-lg p-3 text-sm text-gray-500">
        {{ __('Geen resultaten gevonden') }}
    </div>
</div>
