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

    {{-- Milestone alerts --}}
    @foreach($judokas as $judoka)
        @if($judoka->bereikteMilestones->isNotEmpty())
            @php $laatsteMilestone = $judoka->bereikteMilestones->last(); @endphp
            @if($judoka->punten_totaal == $laatsteMilestone->punten || $judoka->punten_totaal - $laatsteMilestone->punten <= 2)
                <div class="bg-yellow-50 border border-yellow-300 rounded-lg px-4 py-3 mb-2 flex items-center gap-2">
                    <span class="text-yellow-600 font-bold">&#9733;</span>
                    <span>
                        <strong>{{ $judoka->naam }}</strong> heeft {{ $judoka->punten_totaal }} punten bereikt
                        &rarr; <em>{{ $laatsteMilestone->omschrijving }}</em>
                    </span>
                    <a href="{{ route('organisator.wimpel.show', [$organisator, $judoka]) }}"
                       class="ml-auto text-blue-600 hover:text-blue-800 text-sm">Bekijk</a>
                </div>
            @endif
        @endif
    @endforeach

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
            <h2 class="text-lg font-semibold">Judoka's ({{ $judokas->count() }})</h2>
        </div>

        @if($judokas->isEmpty())
            <div class="p-6 text-center text-gray-500">
                Nog geen judoka's. Punten worden automatisch bijgeschreven na een puntencompetitie toernooi.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Geb.jaar</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Punten</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Volgende milestone</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Acties</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($judokas as $judoka)
                            <tr class="hover:bg-gray-50"
                                x-show="!zoek || '{{ strtolower($judoka->naam) }}'.includes(zoek.toLowerCase())">
                                <td class="px-4 py-3 font-medium">
                                    <a href="{{ route('organisator.wimpel.show', [$organisator, $judoka]) }}"
                                       class="text-blue-600 hover:text-blue-800">
                                        {{ $judoka->naam }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-center text-gray-600">{{ $judoka->geboortejaar }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="bg-blue-100 text-blue-800 text-sm font-bold px-3 py-1 rounded-full">
                                        {{ $judoka->punten_totaal }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 text-sm">
                                    @if($judoka->volgendeMilestone)
                                        {{ $judoka->volgendeMilestone->punten }} pt
                                        &rarr; {{ $judoka->volgendeMilestone->omschrijving }}
                                        <span class="text-xs text-gray-400">
                                            (nog {{ $judoka->volgendeMilestone->punten - $judoka->punten_totaal }})
                                        </span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('organisator.wimpel.show', [$organisator, $judoka]) }}"
                                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-medium px-3 py-1 rounded">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<script>
function wimpelPage() {
    return {
        zoek: '',
        verwerking: false,
        feedback: '',
        feedbackType: 'success',

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
