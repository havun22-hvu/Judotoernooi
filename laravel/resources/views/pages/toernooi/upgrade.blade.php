@extends('layouts.app')

@section('title', 'Upgrade Toernooi - ' . $toernooi->naam)

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('toernooi.show', $toernooi) }}" class="text-blue-600 hover:underline">&larr; Terug naar toernooi</a>
    </div>

    <h1 class="text-3xl font-bold text-gray-800 mb-2">Upgrade naar Betaald</h1>
    <p class="text-gray-600 mb-8">{{ $toernooi->naam }}</p>

    {{-- Current status --}}
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
        <h2 class="text-lg font-semibold text-blue-800 mb-4">Huidige Status: Gratis Tier</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-600">Judoka's</span>
                <p class="text-2xl font-bold text-blue-700">{{ $status['current_judokas'] }} / {{ $status['max_judokas'] }}</p>
            </div>
            <div>
                <span class="text-gray-600">Plaatsen over</span>
                <p class="text-2xl font-bold text-blue-700">{{ $status['remaining_slots'] }}</p>
            </div>
            <div>
                <span class="text-gray-600">Print/Noodplan</span>
                <p class="text-2xl font-bold {{ $status['can_use_print'] ? 'text-green-600' : 'text-red-600' }}">
                    {{ $status['can_use_print'] ? 'Beschikbaar' : 'Geblokkeerd' }}
                </p>
            </div>
            <div>
                <span class="text-gray-600">Status</span>
                <p class="text-2xl font-bold text-orange-600">Gratis</p>
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

        <form action="{{ route('toernooi.upgrade.kyc', $toernooi) }}" method="POST">
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
                    <input type="url" name="website" id="website"
                           value="{{ old('website', $organisator->website) }}"
                           placeholder="https://www.judoschool.nl"
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
            <a href="{{ route('toernooi.upgrade', $toernooi) }}?edit=1" class="text-green-600 hover:underline text-sm">Wijzigen</a>
        </div>
    </div>

    {{-- Free tier limitations --}}
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-8">
        <h3 class="font-semibold text-yellow-800 mb-2">Gratis tier beperkingen:</h3>
        <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
            <li>Maximaal 50 judoka's</li>
            <li>Beperkte toegang tot Print/Noodplan functies</li>
            <li>Judoka's kunnen niet verwijderd worden</li>
        </ul>
    </div>

    {{-- Upgrade options --}}
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Stap 2: Kies je staffel</h2>
    <p class="text-gray-600 mb-6">Selecteer het aantal judoka's dat je nodig hebt. Na betaling wordt je toernooi direct ontgrendeld.</p>

    @if(count($upgradeOptions) === 0)
        <div class="bg-gray-100 rounded-lg p-6 text-center">
            <p class="text-gray-600">Er zijn geen upgrade opties beschikbaar. Het maximum aantal judoka's is bereikt.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            @foreach($upgradeOptions as $option)
            <div class="bg-white border-2 border-gray-200 rounded-lg p-6 hover:border-blue-500 transition-colors cursor-pointer upgrade-option"
                 data-tier="{{ $option['tier'] }}" data-prijs="{{ $option['prijs'] }}">
                <div class="text-center">
                    <h3 class="text-xl font-bold text-gray-800">{{ $option['label'] }}</h3>
                    <p class="text-3xl font-bold text-blue-600 mt-2">&euro;{{ number_format($option['prijs'], 2, ',', '.') }}</p>
                    <p class="text-sm text-gray-500 mt-1">eenmalig per toernooi</p>
                </div>
                <ul class="mt-4 text-sm text-gray-600 space-y-2">
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Tot {{ $option['max'] }} judoka's
                    </li>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Volledige Print/Noodplan toegang
                    </li>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        Judoka's verwijderen/wijzigen
                    </li>
                </ul>
            </div>
            @endforeach
        </div>

        {{-- Payment form --}}
        <form id="upgrade-form" action="{{ route('toernooi.upgrade.start', $toernooi) }}" method="POST" class="hidden">
            @csrf
            <input type="hidden" name="tier" id="selected-tier" value="">
        </form>

        <div id="selected-summary" class="hidden bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="font-semibold text-green-800">Geselecteerd: <span id="selected-label"></span></h3>
                    <p class="text-green-700">Prijs: &euro;<span id="selected-prijs"></span></p>
                </div>
                <button type="button" id="pay-button" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg">
                    Betalen via Mollie
                </button>
            </div>
        </div>
    @endif
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const options = document.querySelectorAll('.upgrade-option');
    const form = document.getElementById('upgrade-form');
    const tierInput = document.getElementById('selected-tier');
    const summary = document.getElementById('selected-summary');
    const labelSpan = document.getElementById('selected-label');
    const prijsSpan = document.getElementById('selected-prijs');
    const payButton = document.getElementById('pay-button');

    if (!options.length) return;

    options.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selection from all
            options.forEach(o => o.classList.remove('border-blue-500', 'bg-blue-50'));

            // Add selection to clicked
            this.classList.add('border-blue-500', 'bg-blue-50');

            // Update form
            const tier = this.dataset.tier;
            const prijs = this.dataset.prijs;
            tierInput.value = tier;
            labelSpan.textContent = tier + " judoka's";
            prijsSpan.textContent = parseFloat(prijs).toFixed(2).replace('.', ',');

            // Show summary
            summary.classList.remove('hidden');
        });
    });

    if (payButton) {
        payButton.addEventListener('click', function() {
            if (tierInput.value) {
                form.submit();
            }
        });
    }
});
</script>
@endsection
