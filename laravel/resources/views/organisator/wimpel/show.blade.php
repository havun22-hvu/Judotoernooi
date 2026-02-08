@extends('layouts.app')

@section('title', $wimpelJudoka->naam . ' - Wimpeltoernooi')

@section('content')
<div class="max-w-4xl mx-auto" x-data="judokaDetail()">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                {{ $wimpelJudoka->naam }}
                @if($wimpelJudoka->is_nieuw)
                    <span class="bg-orange-500 text-white text-xs font-bold px-1.5 py-0.5 rounded ml-1 align-middle">NIEUW</span>
                @endif
            </h1>
            <p class="text-gray-500">Geboortejaar: {{ $wimpelJudoka->geboortejaar }}</p>
        </div>
        <a href="{{ route('organisator.wimpel.index', $organisator) }}"
           class="text-blue-600 hover:text-blue-800">
            &larr; Terug naar overzicht
        </a>
    </div>

    {{-- Punten overzicht --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex items-center gap-6 mb-4">
            <div class="text-center">
                <div class="text-4xl font-bold text-blue-600" x-text="puntenTotaal">{{ $wimpelJudoka->punten_totaal }}</div>
                <div class="text-sm text-gray-500">punten totaal</div>
            </div>

            @php
                $volgende = $wimpelJudoka->getEerstvolgeneMilestone();
            @endphp
            @if($volgende)
                <div class="flex-1">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Volgende: {{ $volgende->omschrijving }}</span>
                        <span>{{ $wimpelJudoka->punten_totaal }} / {{ $volgende->punten }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3">
                        <div class="bg-blue-600 h-3 rounded-full transition-all"
                             style="width: {{ min(100, ($wimpelJudoka->punten_totaal / $volgende->punten) * 100) }}%"></div>
                    </div>
                    <div class="text-xs text-gray-400 mt-1">
                        Nog {{ $volgende->punten - $wimpelJudoka->punten_totaal }} punten
                    </div>
                </div>
            @endif
        </div>

        {{-- Bereikte milestones --}}
        @php $bereikt = $wimpelJudoka->getBereikteMilestones(); @endphp
        @if($bereikt->isNotEmpty())
            <div class="border-t pt-4">
                <h3 class="text-sm font-semibold text-gray-600 mb-2">Bereikte milestones</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($bereikt as $ms)
                        <span class="bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full">
                            &#10003; {{ $ms->punten }} pt &mdash; {{ $ms->omschrijving }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Handmatige aanpassing --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-3">Punten aanpassen</h2>

        {{-- Feedback --}}
        <div x-show="feedback" x-transition x-cloak
             :class="feedbackType === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700'"
             class="border rounded px-4 py-3 mb-3">
            <span x-text="feedback"></span>
        </div>

        <form @submit.prevent="aanpassen()" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Punten (+/-)</label>
                <input type="number" x-model="aantalPunten" required
                       class="w-24 border rounded px-3 py-2" placeholder="5">
            </div>
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Notitie (optioneel)</label>
                <input type="text" x-model="notitie"
                       class="w-full border rounded px-3 py-2" placeholder="bijv. Correctie vorig toernooi">
            </div>
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                    :disabled="saving">
                Aanpassen
            </button>
        </form>
    </div>

    {{-- Puntenhistorie --}}
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b">
            <h2 class="text-lg font-semibold">Puntenhistorie</h2>
        </div>

        @if($wimpelJudoka->puntenLog->isEmpty())
            <div class="p-6 text-center text-gray-500">Nog geen puntenhistorie.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Datum</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Toernooi</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Punten</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notitie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($wimpelJudoka->puntenLog as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $log->created_at?->format('d-m-Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $log->toernooi?->naam ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="{{ $log->punten >= 0 ? 'text-green-600' : 'text-red-600' }} font-bold">
                                        {{ $log->punten >= 0 ? '+' : '' }}{{ $log->punten }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($log->type === 'automatisch')
                                        <span class="bg-blue-100 text-blue-700 text-xs px-2 py-0.5 rounded">auto</span>
                                    @else
                                        <span class="bg-gray-100 text-gray-700 text-xs px-2 py-0.5 rounded">handmatig</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $log->notitie ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<script>
function judokaDetail() {
    return {
        puntenTotaal: {{ $wimpelJudoka->punten_totaal }},
        aantalPunten: '',
        notitie: '',
        saving: false,
        feedback: '',
        feedbackType: 'success',

        async aanpassen() {
            if (!this.aantalPunten || this.aantalPunten == 0) return;

            const punten = parseInt(this.aantalPunten);
            const actie = punten > 0 ? `+${punten} punten bijschrijven` : `${punten} punten aftrekken`;
            if (!confirm(`${actie}?`)) return;

            this.saving = true;
            try {
                const res = await fetch('{{ route("organisator.wimpel.aanpassen", [$organisator, $wimpelJudoka]) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        punten: punten,
                        notitie: this.notitie || null,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    this.puntenTotaal = data.punten_totaal;
                    this.aantalPunten = '';
                    this.notitie = '';
                    this.showFeedback('Punten aangepast', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    this.showFeedback('Fout bij aanpassen', 'error');
                }
            } catch (e) {
                this.showFeedback('Verbindingsfout', 'error');
            }
            this.saving = false;
        },

        showFeedback(msg, type) {
            this.feedback = msg;
            this.feedbackType = type;
            setTimeout(() => this.feedback = '', 3000);
        }
    }
}
</script>
@endsection
