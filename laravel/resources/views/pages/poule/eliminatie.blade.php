@extends('layouts.app')

@section('title', 'Eliminatie - ' . $poule->titel)

@section('content')
<div class="mb-6">
    <div class="flex justify-between items-center">
        <div>
            <a href="{{ route('toernooi.poule.index', $toernooi->routeParams()) }}" class="text-blue-600 hover:underline text-sm">&larr; Terug naar poules</a>
            <h1 class="text-3xl font-bold text-gray-800">Eliminatie Bracket ({{ $poule->judokas->count() }} judoka's)</h1>
            <p class="text-gray-600">#{{ $poule->nummer }} {{ $poule->leeftijdsklasse }} / {{ $poule->gewichtsklasse }} kg</p>
        </div>
        <div class="flex gap-2">
            @if(!$heeftEliminatie)
            <button onclick="genereerBracket()" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                Genereer Bracket
            </button>
            @else
            <button onclick="if(confirm('Dit verwijdert de huidige bracket en maakt een nieuwe. Doorgaan?')) genereerBracket()" class="bg-orange-600 hover:bg-orange-700 text-white font-bold py-2 px-4 rounded">
                Hergeneer Bracket
            </button>
            @endif
        </div>
    </div>
</div>

<!-- Toast notification -->
<div id="toast" class="fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 z-50">
    <span id="toast-message"></span>
</div>

@if(!$heeftEliminatie)
    <!-- Geen bracket, toon judoka lijst -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-bold mb-4">Deelnemers ({{ $poule->judokas->count() }})</h2>
        <p class="text-gray-600 mb-4">Klik op "Genereer Bracket" om de eliminatie bracket te maken.</p>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
            @foreach($poule->judokas as $judoka)
            <div class="p-2 bg-gray-50 rounded border">
                <span class="font-medium">{{ $judoka->naam }}</span>
                <span class="text-xs text-gray-500 block">{{ $judoka->club?->naam }} - {{ \App\Enums\Band::stripKyu($judoka->band ?? '') }}</span>
            </div>
            @endforeach
        </div>
    </div>
@else
    <!-- Bracket weergave -->
    <div class="space-y-8">
        <!-- Hoofdboom (Groep A) -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4 text-blue-800">Hoofdboom (Groep A)</h2>
            <div class="bracket-container overflow-x-auto">
                <div class="bracket flex gap-8 min-w-max">
                    @foreach($bracket['hoofdboom'] as $ronde => $wedstrijden)
                    <div class="bracket-round">
                        <h3 class="text-sm font-semibold text-gray-600 mb-2 text-center">{{ ucfirst(str_replace('_', ' ', $ronde)) }}</h3>
                        <div class="flex flex-col justify-around h-full gap-4">
                            @foreach($wedstrijden as $wedstrijd)
                            <div class="bracket-match bg-gray-50 rounded border-2 {{ $wedstrijd->is_gespeeld ? 'border-green-300' : 'border-gray-200' }}"
                                 data-wedstrijd-id="{{ $wedstrijd->id }}"
                                 data-is-gespeeld="{{ $wedstrijd->is_gespeeld ? '1' : '0' }}">
                                <!-- Wit -->
                                <div class="bracket-judoka flex items-center justify-between p-2 border-b {{ $wedstrijd->winnaar_id == $wedstrijd->judoka_wit_id ? 'bg-green-100 font-bold' : '' }}"
                                     data-judoka-id="{{ $wedstrijd->judoka_wit_id }}"
                                     onclick="selectWinnaar({{ $wedstrijd->id }}, {{ $wedstrijd->judoka_wit_id ?? 'null' }})">
                                    <span class="text-sm truncate max-w-32">
                                        @if($wedstrijd->judokaWit)
                                            {{ $wedstrijd->judokaWit->naam }}
                                        @else
                                            <span class="text-gray-400 italic">TBD</span>
                                        @endif
                                    </span>
                                    @if($wedstrijd->winnaar_id == $wedstrijd->judoka_wit_id)
                                        <span class="text-green-600 text-xs">W</span>
                                    @endif
                                </div>
                                <!-- Blauw -->
                                <div class="bracket-judoka flex items-center justify-between p-2 {{ $wedstrijd->winnaar_id == $wedstrijd->judoka_blauw_id ? 'bg-green-100 font-bold' : '' }}"
                                     data-judoka-id="{{ $wedstrijd->judoka_blauw_id }}"
                                     onclick="selectWinnaar({{ $wedstrijd->id }}, {{ $wedstrijd->judoka_blauw_id ?? 'null' }})">
                                    <span class="text-sm truncate max-w-32">
                                        @if($wedstrijd->judokaBlauw)
                                            {{ $wedstrijd->judokaBlauw->naam }}
                                        @else
                                            <span class="text-gray-400 italic">TBD</span>
                                        @endif
                                    </span>
                                    @if($wedstrijd->winnaar_id == $wedstrijd->judoka_blauw_id)
                                        <span class="text-green-600 text-xs">W</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if($bracket['herkansing']->count() > 0)
        <!-- Herkansing (Groep B) -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4 text-orange-800">Herkansing (Groep B) - {{ $poule->judokas->count() - 2 }} judoka's</h2>
            <div class="bracket-container overflow-x-auto">
                <div class="bracket flex gap-8 min-w-max">
                    @foreach($bracket['herkansing'] as $ronde => $wedstrijden)
                    <div class="bracket-round">
                        <h3 class="text-sm font-semibold text-gray-600 mb-2 text-center">{{ ucfirst(str_replace('_', ' ', $ronde)) }}</h3>
                        <div class="flex flex-col justify-around h-full gap-4">
                            @foreach($wedstrijden as $wedstrijd)
                            <div class="bracket-match bg-orange-50 rounded border-2 {{ $wedstrijd->is_gespeeld ? 'border-green-300' : 'border-orange-200' }}"
                                 data-wedstrijd-id="{{ $wedstrijd->id }}"
                                 data-is-gespeeld="{{ $wedstrijd->is_gespeeld ? '1' : '0' }}">
                                <!-- Wit -->
                                <div class="bracket-judoka flex items-center justify-between p-2 border-b {{ $wedstrijd->winnaar_id == $wedstrijd->judoka_wit_id ? 'bg-green-100 font-bold' : '' }}"
                                     data-judoka-id="{{ $wedstrijd->judoka_wit_id }}"
                                     onclick="selectWinnaar({{ $wedstrijd->id }}, {{ $wedstrijd->judoka_wit_id ?? 'null' }})">
                                    <span class="text-sm truncate max-w-32">
                                        @if($wedstrijd->judokaWit)
                                            {{ $wedstrijd->judokaWit->naam }}
                                        @else
                                            <span class="text-gray-400 italic">TBD</span>
                                        @endif
                                    </span>
                                    @if($wedstrijd->winnaar_id == $wedstrijd->judoka_wit_id)
                                        <span class="text-green-600 text-xs">W</span>
                                    @endif
                                </div>
                                <!-- Blauw -->
                                <div class="bracket-judoka flex items-center justify-between p-2 {{ $wedstrijd->winnaar_id == $wedstrijd->judoka_blauw_id ? 'bg-green-100 font-bold' : '' }}"
                                     data-judoka-id="{{ $wedstrijd->judoka_blauw_id }}"
                                     onclick="selectWinnaar({{ $wedstrijd->id }}, {{ $wedstrijd->judoka_blauw_id ?? 'null' }})">
                                    <span class="text-sm truncate max-w-32">
                                        @if($wedstrijd->judokaBlauw)
                                            {{ $wedstrijd->judokaBlauw->naam }}
                                        @else
                                            <span class="text-gray-400 italic">TBD</span>
                                        @endif
                                    </span>
                                    @if($wedstrijd->winnaar_id == $wedstrijd->judoka_blauw_id)
                                        <span class="text-green-600 text-xs">W</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        @if($bracket['brons']->count() > 0)
        <!-- Brons wedstrijden -->
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold mb-4 text-yellow-700">Strijd om Brons</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($bracket['brons'] as $index => $wedstrijd)
                <div class="bracket-match bg-yellow-50 rounded border-2 {{ $wedstrijd->is_gespeeld ? 'border-green-300' : 'border-yellow-300' }}"
                     data-wedstrijd-id="{{ $wedstrijd->id }}"
                     data-is-gespeeld="{{ $wedstrijd->is_gespeeld ? '1' : '0' }}">
                    <div class="text-center text-xs text-yellow-700 font-semibold py-1 border-b">Brons {{ $index + 1 }}</div>
                    <!-- Wit -->
                    <div class="bracket-judoka flex items-center justify-between p-2 border-b {{ $wedstrijd->winnaar_id == $wedstrijd->judoka_wit_id ? 'bg-green-100 font-bold' : '' }}"
                         data-judoka-id="{{ $wedstrijd->judoka_wit_id }}"
                         onclick="selectWinnaar({{ $wedstrijd->id }}, {{ $wedstrijd->judoka_wit_id ?? 'null' }})">
                        <span class="text-sm">
                            @if($wedstrijd->judokaWit)
                                {{ $wedstrijd->judokaWit->naam }}
                            @else
                                <span class="text-gray-400 italic">Verliezer halve finale</span>
                            @endif
                        </span>
                        @if($wedstrijd->winnaar_id == $wedstrijd->judoka_wit_id)
                            <span class="text-green-600">🥉</span>
                        @endif
                    </div>
                    <!-- Blauw -->
                    <div class="bracket-judoka flex items-center justify-between p-2 {{ $wedstrijd->winnaar_id == $wedstrijd->judoka_blauw_id ? 'bg-green-100 font-bold' : '' }}"
                         data-judoka-id="{{ $wedstrijd->judoka_blauw_id }}"
                         onclick="selectWinnaar({{ $wedstrijd->id }}, {{ $wedstrijd->judoka_blauw_id ?? 'null' }})">
                        <span class="text-sm">
                            @if($wedstrijd->judokaBlauw)
                                {{ $wedstrijd->judokaBlauw->naam }}
                            @else
                                <span class="text-gray-400 italic">Winnaar herkansing</span>
                            @endif
                        </span>
                        @if($wedstrijd->winnaar_id == $wedstrijd->judoka_blauw_id)
                            <span class="text-green-600">🥉</span>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
@endif

<style>
.bracket-match {
    width: 180px;
    cursor: pointer;
    transition: all 0.2s;
}
.bracket-match:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.bracket-judoka:hover {
    background-color: #e0f2fe;
}
.bracket-round {
    display: flex;
    flex-direction: column;
}
/* Bracket connector lines - simplified for now */
.bracket-round:not(:last-child) .bracket-match::after {
    content: '';
    position: absolute;
    right: -16px;
    top: 50%;
    width: 16px;
    height: 2px;
    background: #d1d5db;
}
</style>

<script>
const csrfToken = '{{ csrf_token() }}';
const genereerUrl = '{{ route('toernooi.poule.eliminatie.genereer', $toernooi->routeParamsWith(['poule' => $poule])) }}';
const uitslagUrl = '{{ route('toernooi.poule.eliminatie.uitslag', $toernooi->routeParamsWith(['poule' => $poule])) }}';

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    toast.className = toast.className.replace(/bg-\w+-600/g, '');
    toast.classList.add(type === 'success' ? 'bg-green-600' : 'bg-red-600');
    toastMessage.textContent = message;
    toast.classList.remove('translate-x-full');
    setTimeout(() => toast.classList.add('translate-x-full'), 3000);
}

async function genereerBracket() {
    try {
        const response = await fetch(genereerUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (data.success) {
            showToast(data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Fout bij genereren bracket', 'error');
        console.error(error);
    }
}

async function selectWinnaar(wedstrijdId, judokaId) {
    if (!judokaId) {
        showToast('Kan geen winnaar selecteren - judoka niet bekend', 'error');
        return;
    }

    // Check if match already has both participants
    const match = document.querySelector(`[data-wedstrijd-id="${wedstrijdId}"]`);
    const judokas = match.querySelectorAll('.bracket-judoka[data-judoka-id]');
    let hasWit = false, hasBlauw = false;
    judokas.forEach(el => {
        if (el.dataset.judokaId && el.dataset.judokaId !== 'null') {
            if (el.classList.contains('border-b')) hasWit = true;
            else hasBlauw = true;
        }
    });

    if (!hasWit || !hasBlauw) {
        showToast('Wacht tot beide judoka\'s bekend zijn', 'error');
        return;
    }

    try {
        const response = await fetch(uitslagUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                wedstrijd_id: wedstrijdId,
                winnaar_id: judokaId,
                uitslag_type: 'ippon'
            })
        });
        const data = await response.json();
        if (data.success) {
            showToast('Uitslag opgeslagen');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        showToast('Fout bij opslaan uitslag', 'error');
        console.error(error);
    }
}
</script>
@endsection
