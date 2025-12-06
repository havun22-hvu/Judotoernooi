@extends('layouts.app')

@section('title', 'Mat Interface')

@section('content')
<div x-data="matInterface()" class="max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">🥋 Mat Interface</h1>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-gray-700 font-bold mb-2">Blok</label>
                <select x-model="blokId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2">
                    <option value="">Selecteer blok...</option>
                    @foreach($blokken as $blok)
                    <option value="{{ $blok->id }}">Blok {{ $blok->nummer }}
                        @if($blok->weging_gesloten) (gesloten) @endif
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-gray-700 font-bold mb-2">Mat</label>
                <select x-model="matId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2">
                    <option value="">Selecteer mat...</option>
                    @foreach($matten as $mat)
                    <option value="{{ $mat->id }}">Mat {{ $mat->nummer }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <template x-for="poule in poules" :key="poule.poule_id">
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="bg-blue-800 text-white px-6 py-3 rounded-t-lg">
                <h2 class="text-lg font-bold" x-text="poule.titel"></h2>
            </div>

            <div class="p-6">
                <template x-for="w in poule.wedstrijden" :key="w.id">
                    <div class="border-b py-4 last:border-0">
                        <div class="flex justify-between items-center">
                            <div class="flex-1">
                                <span class="text-gray-500" x-text="w.volgorde + '.'"></span>
                                <span class="font-medium text-blue-600" x-text="w.wit.naam"></span>
                                <span class="mx-2">vs</span>
                                <span class="font-medium text-red-600" x-text="w.blauw.naam"></span>
                            </div>
                            <div x-show="!w.is_gespeeld" class="flex space-x-2">
                                <button @click="registreerWinnaar(w, w.wit.id, 'wit')"
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">
                                    Wit wint
                                </button>
                                <button @click="registreerWinnaar(w, w.blauw.id, 'blauw')"
                                        class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">
                                    Blauw wint
                                </button>
                            </div>
                            <div x-show="w.is_gespeeld" class="text-green-600 font-bold">
                                ✓ Gespeeld
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <div x-show="poules.length === 0 && blokId && matId" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Geen poules op deze mat in dit blok
    </div>
</div>

<script>
function matInterface() {
    return {
        blokId: '',
        matId: '',
        poules: [],

        async laadWedstrijden() {
            if (!this.blokId || !this.matId) {
                this.poules = [];
                return;
            }

            const response = await fetch(`{{ route('toernooi.mat.wedstrijden', $toernooi) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    blok_id: this.blokId,
                    mat_id: this.matId
                })
            });

            this.poules = await response.json();
        },

        async registreerWinnaar(wedstrijd, winnaarId, kleur) {
            const response = await fetch(`{{ route('toernooi.mat.uitslag', $toernooi) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    wedstrijd_id: wedstrijd.id,
                    winnaar_id: winnaarId,
                    uitslag_type: 'beslissing'
                })
            });

            if (response.ok) {
                wedstrijd.is_gespeeld = true;
                wedstrijd.winnaar_id = winnaarId;
            }
        }
    }
}
</script>
@endsection
