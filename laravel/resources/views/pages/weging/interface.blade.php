@extends('layouts.app')

@section('title', 'Weging Interface')

@section('content')
<div x-data="wegingInterface()" class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">⚖️ Weging Interface</h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="mb-4">
            <label class="block text-gray-700 font-bold mb-2">Zoek Judoka</label>
            <input type="text" x-model="zoekterm" @input.debounce.300ms="zoekJudoka()"
                   placeholder="Naam invoeren..."
                   class="w-full border rounded px-4 py-3 text-lg">
        </div>

        <div x-show="resultaten.length > 0" class="border rounded max-h-64 overflow-y-auto">
            <template x-for="judoka in resultaten" :key="judoka.id">
                <div @click="selecteerJudoka(judoka)"
                     class="p-3 hover:bg-blue-50 cursor-pointer border-b last:border-0">
                    <div class="font-medium" x-text="judoka.naam"></div>
                    <div class="text-sm text-gray-600">
                        <span x-text="judoka.club || 'Geen club'"></span> -
                        <span x-text="judoka.gewichtsklasse + ' kg'"></span>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div x-show="geselecteerd" class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4" x-text="geselecteerd?.naam"></h2>

        <div class="grid grid-cols-2 gap-4 mb-6">
            <div>
                <div class="text-gray-600">Club</div>
                <div class="font-medium" x-text="geselecteerd?.club || '-'"></div>
            </div>
            <div>
                <div class="text-gray-600">Gewichtsklasse</div>
                <div class="font-medium" x-text="(geselecteerd?.gewichtsklasse || '-') + ' kg'"></div>
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 font-bold mb-2">Gewogen Gewicht (kg)</label>
            <input type="number" x-model="gewicht" step="0.1" min="15" max="150"
                   class="w-full border rounded px-4 py-3 text-2xl text-center"
                   placeholder="0.0">
        </div>

        <div x-show="melding" :class="meldingType === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
             class="p-4 rounded mb-4" x-text="melding"></div>

        <button @click="registreerGewicht()"
                :disabled="!gewicht"
                class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white font-bold py-4 rounded text-xl">
            Registreer Gewicht
        </button>
    </div>
</div>

<script>
function wegingInterface() {
    return {
        zoekterm: '',
        resultaten: [],
        geselecteerd: null,
        gewicht: '',
        melding: '',
        meldingType: 'success',

        async zoekJudoka() {
            if (this.zoekterm.length < 2) {
                this.resultaten = [];
                return;
            }

            const response = await fetch(`{{ route('toernooi.judoka.zoek', $toernooi) }}?q=${encodeURIComponent(this.zoekterm)}`);
            this.resultaten = await response.json();
        },

        selecteerJudoka(judoka) {
            this.geselecteerd = judoka;
            this.resultaten = [];
            this.zoekterm = '';
            this.gewicht = '';
            this.melding = '';
        },

        async registreerGewicht() {
            if (!this.geselecteerd || !this.gewicht) return;

            const response = await fetch(`/toernooi/{{ $toernooi->id }}/weging/${this.geselecteerd.id}/registreer`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ gewicht: this.gewicht })
            });

            const data = await response.json();

            if (data.success) {
                if (data.binnen_klasse) {
                    this.melding = `Gewicht ${this.gewicht} kg geregistreerd!`;
                    this.meldingType = 'success';
                } else {
                    this.melding = `Let op: ${data.opmerking} Alternatief: ${data.alternatieve_poule}`;
                    this.meldingType = 'error';
                }
            } else {
                this.melding = 'Fout bij registreren';
                this.meldingType = 'error';
            }
        }
    }
}
</script>
@endsection
