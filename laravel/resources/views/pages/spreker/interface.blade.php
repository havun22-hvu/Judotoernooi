@extends('layouts.app')

@section('title', 'Spreker Interface')

@section('content')
<div x-data="sprekerInterface()">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">📢 Spreker Interface</h1>
        <div class="text-sm text-gray-600">
            Auto-refresh elke 10 seconden
        </div>
    </div>

    @if($klarePoules->isEmpty())
    <div class="bg-white rounded-lg shadow p-12 text-center">
        <div class="text-6xl mb-4">🎙️</div>
        <h2 class="text-2xl font-bold text-gray-600 mb-2">Wachten op uitslagen...</h2>
        <p class="text-gray-500">Afgeronde poules verschijnen hier automatisch</p>
    </div>
    @else
    <div class="space-y-6">
        @foreach($klarePoules as $poule)
        <div class="bg-white rounded-lg shadow overflow-hidden" id="poule-{{ $poule->id }}">
            <!-- Header -->
            <div class="{{ $poule->is_eliminatie ? 'bg-purple-700' : 'bg-green-700' }} text-white px-4 py-3 flex justify-between items-center">
                <div>
                    <div class="font-bold text-lg">
                        @if($poule->is_eliminatie)
                            Eliminatie - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                        @else
                            Poule {{ $poule->nummer }} - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                        @endif
                    </div>
                    <div class="{{ $poule->is_eliminatie ? 'text-purple-200' : 'text-green-200' }} text-sm">
                        Blok {{ $poule->blok?->nummer ?? '?' }} - Mat {{ $poule->mat?->nummer ?? '?' }} | Klaar: {{ $poule->spreker_klaar->format('H:i') }}
                    </div>
                </div>
                <button
                    @click="markeerAfgeroepen({{ $poule->id }})"
                    class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded font-bold flex items-center gap-2"
                >
                    ✓ Afgerond
                </button>
            </div>

            @if($poule->is_eliminatie)
            <!-- ELIMINATIE: Medaille winnaars -->
            <div class="p-4">
                <div class="grid gap-3">
                    @foreach($poule->standings as $standing)
                    @php $plaats = $standing['plaats']; @endphp
                    <div class="flex items-center gap-3 p-3 rounded-lg
                        @if($plaats === 1) bg-gradient-to-r from-yellow-100 to-yellow-200 border-2 border-yellow-400
                        @elseif($plaats === 2) bg-gradient-to-r from-gray-100 to-gray-200 border-2 border-gray-400
                        @else bg-gradient-to-r from-orange-100 to-orange-200 border-2 border-orange-400
                        @endif">
                        <div class="text-3xl">
                            @if($plaats === 1) 🥇
                            @elseif($plaats === 2) 🥈
                            @else 🥉
                            @endif
                        </div>
                        <div>
                            <div class="font-bold text-lg">{{ $standing['judoka']->naam }}</div>
                            <div class="text-sm text-gray-600">{{ $standing['judoka']->club?->naam ?? '-' }}</div>
                        </div>
                        <div class="ml-auto text-2xl font-bold
                            @if($plaats === 1) text-yellow-700
                            @elseif($plaats === 2) text-gray-700
                            @else text-orange-700
                            @endif">
                            {{ $plaats }}e
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            <!-- POULE: Resultaten tabel -->
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr class="bg-gray-200 border-b-2 border-gray-400">
                            <th class="px-3 py-2 text-left font-bold text-gray-700">Naam</th>
                            <th class="px-2 py-2 text-center font-bold text-gray-700 w-12">WP</th>
                            <th class="px-2 py-2 text-center font-bold text-gray-700 w-12">JP</th>
                            <th class="px-2 py-2 text-center font-bold text-gray-700 w-12">#</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($poule->standings as $index => $standing)
                        @php $plaats = $index + 1; @endphp
                        <tr class="border-b last:border-0">
                            <td class="px-3 py-2">
                                <span class="font-bold">{{ $standing['judoka']->naam }}</span>
                                <span class="text-gray-500 text-xs">({{ $standing['judoka']->club?->naam ?? '-' }})</span>
                            </td>
                            <td class="px-2 py-2 text-center font-bold bg-blue-50 text-blue-800">{{ $standing['wp'] }}</td>
                            <td class="px-2 py-2 text-center bg-blue-50 text-blue-800">{{ $standing['jp'] }}</td>
                            <td class="px-2 py-2 text-center font-bold text-lg
                                @if($plaats === 1) bg-yellow-400 text-yellow-900
                                @elseif($plaats === 2) bg-gray-300 text-gray-800
                                @elseif($plaats === 3) bg-orange-300 text-orange-900
                                @else bg-yellow-50
                                @endif">
                                {{ $plaats }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>

<script>
function sprekerInterface() {
    return {
        async markeerAfgeroepen(pouleId) {
            try {
                const response = await fetch('{{ route('toernooi.spreker.afgeroepen', $toernooi) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ poule_id: pouleId })
                });

                const data = await response.json();
                if (data.success) {
                    // Remove poule from view with animation
                    const element = document.getElementById('poule-' + pouleId);
                    if (element) {
                        element.style.transition = 'opacity 0.3s, transform 0.3s';
                        element.style.opacity = '0';
                        element.style.transform = 'translateX(100px)';
                        setTimeout(() => element.remove(), 300);
                    }
                }
            } catch (err) {
                alert('Fout: ' + err.message);
            }
        }
    }
}

// Auto-refresh elke 10 seconden
setTimeout(function() {
    location.reload();
}, 10000);
</script>
@endsection
