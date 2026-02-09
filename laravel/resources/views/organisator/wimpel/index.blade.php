@extends('layouts.app')

@section('title', 'Wimpeltoernooi - ' . $organisator->naam)

@section('content')
<div class="max-w-6xl mx-auto" x-data="wimpelPage()">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Wimpeltoernooi</h1>
            <p class="text-gray-500">Doorlopend puntensysteem - 1 punt per gewonnen wedstrijd</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('organisator.wimpel.instellingen', $organisator) }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg text-sm">
                Instellingen
            </a>
            <a href="{{ route('organisator.dashboard', $organisator) }}"
               class="text-blue-600 hover:text-blue-800">
                &larr; Terug
            </a>
        </div>
    </div>

    {{-- Open uitreikingen (milestone bereikt, nog niet uitgereikt) --}}
    @if($openUitreikingen->isNotEmpty())
        <div class="bg-yellow-50 border border-yellow-300 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-yellow-800 mb-2">&#9733; Milestone uitreikingen</h3>
            <div class="space-y-2">
                @foreach($openUitreikingen as $uitreiking)
                    <div class="flex items-center gap-3 bg-white rounded px-3 py-2 border border-yellow-200">
                        <span class="text-yellow-600 font-bold text-lg">&#9733;</span>
                        <span class="font-medium">{{ $uitreiking->wimpelJudoka->naam }}</span>
                        <span class="text-gray-400">&mdash;</span>
                        <span class="text-yellow-700 font-medium">{{ $uitreiking->milestone->omschrijving }}</span>
                        <span class="text-xs text-gray-400">({{ $uitreiking->milestone->punten }} pt)</span>
                        <a href="{{ route('organisator.wimpel.show', [$organisator, $uitreiking->wimpelJudoka]) }}"
                           class="ml-auto text-blue-600 hover:text-blue-800 text-sm">Bekijk</a>
                    </div>
                @endforeach
            </div>
            <p class="text-xs text-yellow-600 mt-2">Uitreikingen worden door de spreker afgevinkt tijdens het toernooi</p>
        </div>
    @endif

    {{-- Punten bijschrijven --}}
    @if($onverwerkteToernooien->isNotEmpty())
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-blue-800 mb-2">Punten bijschrijven</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($onverwerkteToernooien as $toernooi)
                    <button @click="verwerkToernooi({{ $toernooi->id }}, '{{ addslashes($toernooi->naam) }}')"
                            class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-1.5 px-3 rounded"
                            :disabled="verwerking">
                        {{ $toernooi->naam }}
                        @if($toernooi->datum)
                            <span class="opacity-75">({{ $toernooi->datum->format('d-m-Y') }})</span>
                        @endif
                    </button>
                @endforeach
            </div>
            <p class="text-xs text-blue-600 mt-2">Klik op een toernooi om de gewonnen wedstrijden bij te schrijven</p>
        </div>
    @endif

    {{-- Feedback --}}
    <div x-show="feedback" x-transition x-cloak
         :class="feedbackType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'"
         class="border rounded px-4 py-3 mb-4">
        <span x-text="feedback"></span>
    </div>

    {{-- Zoek --}}
    <div class="mb-4">
        <input type="text" x-model="zoek" placeholder="Zoek judoka..."
               class="w-full md:w-72 border rounded px-3 py-2 text-sm">
    </div>

    {{-- Judoka tabel --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">Judoka's (<span x-text="gefilterd.length"></span>)</h2>
        </div>

        <template x-if="judokas.length === 0">
            <div class="p-6 text-center text-gray-500">
                Nog geen judoka's. Punten worden automatisch bijgeschreven na een puntencompetitie toernooi.
            </div>
        </template>

        <template x-if="judokas.length > 0">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th @click="sorteer('naam')" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Naam
                                <span x-show="sortKolom === 'naam'" x-text="sortRichting === 'asc' ? ' ▲' : ' ▼'"></span>
                            </th>
                            <th @click="sorteer('geboortejaar')" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Geb.jaar
                                <span x-show="sortKolom === 'geboortejaar'" x-text="sortRichting === 'asc' ? ' ▲' : ' ▼'"></span>
                            </th>
                            <th @click="sorteer('punten_totaal')" class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700">
                                Punten
                                <span x-show="sortKolom === 'punten_totaal'" x-text="sortRichting === 'asc' ? ' ▲' : ' ▼'"></span>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Volgende milestone</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <template x-for="judoka in gefilterd" :key="judoka.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium">
                                    <a :href="judoka.url" class="text-blue-600 hover:text-blue-800" x-text="judoka.naam"></a>
                                    <span x-show="judoka.is_nieuw" class="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded ml-1">NIEUW</span>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600" x-text="judoka.geboortejaar"></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="bg-blue-100 text-blue-800 text-sm font-bold px-3 py-1 rounded-full" x-text="judoka.punten_totaal"></span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-sm">
                                    <template x-if="judoka.volgende">
                                        <span>
                                            <span x-text="judoka.volgende.punten + ' pt'"></span>
                                            &rarr; <span x-text="judoka.volgende.omschrijving"></span>
                                            <span class="text-xs text-gray-400" x-text="'(nog ' + (judoka.volgende.punten - judoka.punten_totaal) + ')'"></span>
                                        </span>
                                    </template>
                                    <template x-if="!judoka.volgende">
                                        <span class="text-gray-400">-</span>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-right flex items-center justify-end gap-2">
                                    <template x-if="judoka.is_nieuw">
                                        <button @click="bevestigJudoka(judoka.id)"
                                                class="bg-green-500 hover:bg-green-600 text-white text-xs font-medium px-3 py-1 rounded"
                                                :disabled="verwerking">
                                            &#10003; Bevestigd
                                        </button>
                                    </template>
                                    <a :href="judoka.url"
                                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium px-3 py-1 rounded">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</div>

<script>
function wimpelPage() {
    return {
        zoek: '',
        verwerking: false,
        feedback: '',
        feedbackType: 'success',
        sortKolom: 'punten_totaal',
        sortRichting: 'desc',
        judokas: @json($judokas->map(fn($j) => [
            'id' => $j->id,
            'naam' => $j->naam,
            'geboortejaar' => $j->geboortejaar,
            'punten_totaal' => $j->punten_totaal,
            'is_nieuw' => $j->is_nieuw,
            'url' => route('organisator.wimpel.show', [$organisator, $j]),
            'volgende' => $j->volgendeMilestone ? [
                'punten' => $j->volgendeMilestone->punten,
                'omschrijving' => $j->volgendeMilestone->omschrijving,
            ] : null,
        ])->values()),

        get gefilterd() {
            let lijst = this.judokas;

            if (this.zoek) {
                const z = this.zoek.toLowerCase();
                lijst = lijst.filter(j => j.naam.toLowerCase().includes(z));
            }

            const kolom = this.sortKolom;
            const richting = this.sortRichting === 'asc' ? 1 : -1;

            return [...lijst].sort((a, b) => {
                let va = a[kolom];
                let vb = b[kolom];
                if (typeof va === 'string') {
                    return va.localeCompare(vb, 'nl') * richting;
                }
                return ((va ?? 0) - (vb ?? 0)) * richting;
            });
        },

        sorteer(kolom) {
            if (this.sortKolom === kolom) {
                this.sortRichting = this.sortRichting === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortKolom = kolom;
                this.sortRichting = kolom === 'naam' ? 'asc' : 'desc';
            }
        },

        async bevestigJudoka(judokaId) {
            try {
                const response = await fetch(`{{ url($organisator->slug . '/wimpeltoernooi') }}/${judokaId}/bevestig`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();
                if (data.success) {
                    location.reload();
                }
            } catch (e) {
                this.feedback = 'Verbindingsfout';
                this.feedbackType = 'error';
            }
        },

        async verwerkToernooi(toernooiId, naam) {
            if (!confirm(`Punten bijschrijven van "${naam}"?`)) return;

            this.verwerking = true;
            this.feedback = '';

            try {
                const response = await fetch('{{ route("organisator.wimpel.verwerk", $organisator) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ toernooi_id: toernooiId }),
                });

                const data = await response.json();

                if (data.success) {
                    this.feedback = data.message;
                    this.feedbackType = 'success';
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.feedback = data.error || 'Er ging iets mis';
                    this.feedbackType = 'error';
                }
            } catch (e) {
                this.feedback = 'Verbindingsfout';
                this.feedbackType = 'error';
            }

            this.verwerking = false;
        }
    }
}
</script>
@endsection
