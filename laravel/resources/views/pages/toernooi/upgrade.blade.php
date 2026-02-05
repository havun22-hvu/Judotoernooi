@extends('layouts.app')

@section('title', 'Upgrade Toernooi - ' . $toernooi->naam)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}" class="text-blue-600 hover:underline">&larr; Terug naar toernooi</a>
    </div>

    <h1 class="text-3xl font-bold text-gray-800 mb-2">
        {{ ($isReUpgrade ?? false) ? 'Meer judoka\'s nodig?' : 'Upgrade naar Betaald' }}
    </h1>
    <p class="text-gray-600 mb-8">{{ $toernooi->naam }}</p>

    {{-- Current status --}}
    <div class="{{ ($isReUpgrade ?? false) ? 'bg-green-50 border-green-200' : 'bg-blue-50 border-blue-200' }} border rounded-lg p-6 mb-8">
        <h2 class="text-lg font-semibold {{ ($isReUpgrade ?? false) ? 'text-green-800' : 'text-blue-800' }} mb-4">
            Huidige Status: {{ ($isReUpgrade ?? false) ? 'Betaald (max ' . $status['max_judokas'] . ' judoka\'s)' : 'Gratis Tier' }}
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-600">Judoka's</span>
                <p class="text-2xl font-bold {{ ($isReUpgrade ?? false) ? 'text-green-700' : 'text-blue-700' }}">{{ $status['current_judokas'] }} / {{ $status['max_judokas'] }}</p>
            </div>
            <div>
                <span class="text-gray-600">Plaatsen over</span>
                <p class="text-2xl font-bold {{ ($isReUpgrade ?? false) ? 'text-green-700' : 'text-blue-700' }}">{{ $status['remaining_slots'] }}</p>
            </div>
            <div>
                <span class="text-gray-600">Print/Noodplan</span>
                <p class="text-2xl font-bold {{ $status['can_use_print'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $status['can_use_print'] ? 'Beschikbaar' : 'Geblokkeerd' }}
                </p>
            </div>
            <div>
                <span class="text-gray-600">Status</span>
                <p class="text-2xl font-bold {{ ($isReUpgrade ?? false) ? 'text-green-600' : 'text-orange-600' }}">
                    {{ ($isReUpgrade ?? false) ? 'Betaald' : 'Gratis' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Step indicator --}}
    <div class="flex items-center mb-8">
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full {{ $kycCompleet ? 'bg-green-500' : 'bg-blue-500' }} text-white flex items-center justify-center text-sm font-bold">
                @if($kycCompleet) ✓ @else 1 @endif
            </div>
            <span class="ml-2 font-medium {{ $kycCompleet ? 'text-green-600' : 'text-blue-600' }}">Facturatiegegevens</span>
        </div>
        <div class="flex-1 h-1 mx-4 {{ $kycCompleet ? 'bg-green-300' : 'bg-gray-300' }}"></div>
        <div class="flex items-center">
            <div class="w-8 h-8 rounded-full {{ $kycCompleet ? 'bg-blue-500' : 'bg-gray-300' }} text-white flex items-center justify-center text-sm font-bold">2</div>
            <span class="ml-2 font-medium {{ $kycCompleet ? 'text-blue-600' : 'text-gray-400' }}">Staffel kiezen & betalen</span>
        </div>
    </div>

    @if(!$kycCompleet)
    {{-- KYC Form --}}
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Stap 1: Facturatiegegevens</h2>
        <p class="text-gray-600 mb-6">Vul je organisatiegegevens in. Deze worden gebruikt voor de factuur.</p>

        <form action="{{ route('toernooi.upgrade.kyc', $toernooi->routeParams()) }}" method="POST">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="organisatie_naam" class="block text-gray-700 font-medium mb-1">Organisatie/Verenigingsnaam *</label>
                    <input type="text" name="organisatie_naam" id="organisatie_naam"
                           value="{{ old('organisatie_naam', $organisator->organisatie_naam) }}"
                           placeholder="Bijv. Judoschool Cees Veen"
                           class="w-full border rounded px-3 py-2 @error('organisatie_naam') border-red-500 @enderror" required>
                    @error('organisatie_naam')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="kvk_nummer" class="block text-gray-700 font-medium mb-1">KvK-nummer</label>
                    <input type="text" name="kvk_nummer" id="kvk_nummer"
                           value="{{ old('kvk_nummer', $organisator->kvk_nummer) }}"
                           placeholder="12345678"
                           class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label for="btw_nummer" class="block text-gray-700 font-medium mb-1">BTW-nummer</label>
                    <input type="text" name="btw_nummer" id="btw_nummer"
                           value="{{ old('btw_nummer', $organisator->btw_nummer) }}"
                           placeholder="NL123456789B01"
                           class="w-full border rounded px-3 py-2">
                </div>

                <div class="md:col-span-2">
                    <label for="straat" class="block text-gray-700 font-medium mb-1">Straat en huisnummer *</label>
                    <input type="text" name="straat" id="straat"
                           value="{{ old('straat', $organisator->straat) }}"
                           placeholder="Hoofdstraat 123"
                           class="w-full border rounded px-3 py-2 @error('straat') border-red-500 @enderror" required>
                    @error('straat')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="postcode" class="block text-gray-700 font-medium mb-1">Postcode *</label>
                    <input type="text" name="postcode" id="postcode"
                           value="{{ old('postcode', $organisator->postcode) }}"
                           placeholder="1234 AB"
                           class="w-full border rounded px-3 py-2 @error('postcode') border-red-500 @enderror" required>
                    @error('postcode')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="plaats" class="block text-gray-700 font-medium mb-1">Plaats *</label>
                    <input type="text" name="plaats" id="plaats"
                           value="{{ old('plaats', $organisator->plaats) }}"
                           placeholder="Amsterdam"
                           class="w-full border rounded px-3 py-2 @error('plaats') border-red-500 @enderror" required>
                    @error('plaats')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="land" class="block text-gray-700 font-medium mb-1">Land *</label>
                    <input type="text" name="land" id="land"
                           value="{{ old('land', $organisator->land ?? 'Nederland') }}"
                           class="w-full border rounded px-3 py-2" required>
                </div>

                <div>
                    <label for="contactpersoon" class="block text-gray-700 font-medium mb-1">Contactpersoon *</label>
                    <input type="text" name="contactpersoon" id="contactpersoon"
                           value="{{ old('contactpersoon', $organisator->contactpersoon) }}"
                           placeholder="Jan Jansen"
                           class="w-full border rounded px-3 py-2 @error('contactpersoon') border-red-500 @enderror" required>
                    @error('contactpersoon')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="telefoon" class="block text-gray-700 font-medium mb-1">Telefoonnummer</label>
                    <input type="tel" name="telefoon" id="telefoon"
                           value="{{ old('telefoon', $organisator->telefoon) }}"
                           placeholder="06-12345678"
                           class="w-full border rounded px-3 py-2">
                </div>

                <div>
                    <label for="factuur_email" class="block text-gray-700 font-medium mb-1">E-mail voor factuur *</label>
                    <input type="email" name="factuur_email" id="factuur_email"
                           value="{{ old('factuur_email', $organisator->factuur_email ?? $organisator->email) }}"
                           placeholder="factuur@judoschool.nl"
                           class="w-full border rounded px-3 py-2 @error('factuur_email') border-red-500 @enderror" required>
                    @error('factuur_email')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="website" class="block text-gray-700 font-medium mb-1">Website of Facebook</label>
                    <input type="text" name="website" id="website"
                           value="{{ old('website', $organisator->website) }}"
                           placeholder="judoschool.nl of facebook.com/judoschool"
                           class="w-full border rounded px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1">Ter verificatie van je organisatie</p>
                </div>
            </div>

            <div class="mt-6">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg">
                    Opslaan en doorgaan
                </button>
            </div>
        </form>
    </div>

    @else
    {{-- KYC Complete - Show summary --}}
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h3 class="font-semibold text-green-800 mb-2">Facturatiegegevens</h3>
                <div class="text-sm text-green-700">
                    <p><strong>{{ $organisator->organisatie_naam }}</strong></p>
                    <p>{{ $organisator->straat }}</p>
                    <p>{{ $organisator->postcode }} {{ $organisator->plaats }}</p>
                    @if($organisator->kvk_nummer)<p>KvK: {{ $organisator->kvk_nummer }}</p>@endif
                    <p>{{ $organisator->factuur_email }}</p>
                </div>
            </div>
            <a href="{{ route('toernooi.upgrade', $toernooi->routeParams()) }}?edit=1" class="text-green-600 hover:underline text-sm">Wijzigen</a>
        </div>
    </div>

    {{-- Upgrade options --}}
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Stap 2: Kies je aantal judoka's</h2>

    @if(count($upgradeOptions) === 0)
        <div class="bg-gray-100 rounded-lg p-6 text-center">
            <p class="text-gray-600">Er zijn geen upgrade opties beschikbaar. Het maximum aantal judoka's is bereikt.</p>
        </div>
    @else
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            {{-- Simple selector --}}
            <div class="flex flex-col md:flex-row md:items-end gap-6">
                <div class="flex-1">
                    <label for="tier-select" class="block text-gray-700 font-medium mb-2">Hoeveel judoka's heb je nodig?</label>
                    <select id="tier-select" class="w-full md:w-64 border-2 border-gray-300 rounded-lg px-4 py-3 text-lg focus:border-blue-500 focus:outline-none">
                        <option value="">-- Kies aantal --</option>
                        @foreach($upgradeOptions as $option)
                        <option value="{{ $option['tier'] }}" data-prijs="{{ $option['prijs'] }}" data-max="{{ $option['max'] }}">
                            Tot {{ $option['max'] }} judoka's
                        </option>
                        @endforeach
                    </select>
                </div>

                <div id="price-display" class="hidden">
                    <p class="text-gray-600 text-sm mb-1">Eenmalige kosten</p>
                    <p class="text-4xl font-bold text-blue-600">&euro;<span id="prijs-amount">0</span></p>
                </div>
            </div>

            {{-- What you get (shown once) --}}
            <div id="features-section" class="hidden mt-6 pt-6 border-t">
                <p class="text-gray-700 font-medium mb-3">Dit krijg je:</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                    <div class="flex items-center text-gray-600">
                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Tot <span id="max-judokas" class="font-semibold mx-1">0</span> judoka's
                    </div>
                    <div class="flex items-center text-gray-600">
                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Volledige Print/Noodplan
                    </div>
                    <div class="flex items-center text-gray-600">
                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Judoka's verwijderen/wijzigen
                    </div>
                </div>
            </div>

            {{-- Pay button --}}
            <div id="pay-section" class="hidden mt-6">
                <form id="upgrade-form" action="{{ route('toernooi.upgrade.start', $toernooi->routeParams()) }}" method="POST">
                    @csrf
                    <input type="hidden" name="tier" id="selected-tier" value="">
                    <button type="submit" class="w-full md:w-auto bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-8 rounded-lg text-lg">
                        Betalen via Mollie
                    </button>
                </form>
            </div>
        </div>

        {{-- Pricing info --}}
        <p class="text-sm text-gray-500 text-center">Prijzen: &euro;10 per 50 judoka's (boven de gratis 50)</p>
    @endif
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('tier-select');
    const tierInput = document.getElementById('selected-tier');
    const priceDisplay = document.getElementById('price-display');
    const prijsAmount = document.getElementById('prijs-amount');
    const maxJudokas = document.getElementById('max-judokas');
    const featuresSection = document.getElementById('features-section');
    const paySection = document.getElementById('pay-section');

    if (!select) return;

    select.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];

        if (this.value) {
            const prijs = selected.dataset.prijs;
            const max = selected.dataset.max;

            tierInput.value = this.value;
            prijsAmount.textContent = parseInt(prijs);
            maxJudokas.textContent = max;

            priceDisplay.classList.remove('hidden');
            featuresSection.classList.remove('hidden');
            paySection.classList.remove('hidden');
        } else {
            priceDisplay.classList.add('hidden');
            featuresSection.classList.add('hidden');
            paySection.classList.add('hidden');
            tierInput.value = '';
        }
    });
});
</script>
@endsection
