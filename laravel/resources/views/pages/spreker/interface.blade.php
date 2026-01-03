<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#1e40af">
    <link rel="manifest" href="/manifest-spreker.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192x192.png">
    <link rel="apple-touch-icon" href="/icon-192x192.png">
    <title>Spreker Interface - {{ $toernooi->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        body { overscroll-behavior: none; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Standalone Header -->
    <header class="bg-blue-800 text-white px-4 py-3 flex items-center justify-between shadow-lg sticky top-0 z-50">
        <div>
            <h1 class="text-lg font-bold">📢 Spreker Interface</h1>
            <p class="text-blue-200 text-sm">{{ $toernooi->naam }}</p>
        </div>
        <div class="text-2xl font-mono" id="clock"></div>
    </header>

    <main class="p-3">
    @php $pwaApp = 'spreker'; @endphp
<div x-data="sprekerInterface()">
    <div class="flex justify-end items-center mb-4 gap-2">
        <button
            @click="toonGeschiedenis = !toonGeschiedenis"
            class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2"
        >
            <span>📋</span> Vorige
        </button>
        <div class="text-xs text-gray-500">
            Auto-refresh 10s
        </div>
    </div>

    <!-- Geschiedenis van afgeroepen poules (opgeslagen in localStorage) -->
    <div x-show="toonGeschiedenis" x-cloak class="mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4">
        <div class="flex justify-between items-center mb-3">
            <span class="text-gray-700 font-bold">📋 Eerder afgeroepen (vandaag)</span>
            <button @click="toonGeschiedenis = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <template x-if="geschiedenis.length === 0">
            <p class="text-gray-500 text-sm">Nog geen prijsuitreikingen vandaag</p>
        </template>
        <template x-if="geschiedenis.length > 0">
            <div class="grid gap-2 max-h-64 overflow-y-auto">
                <template x-for="item in geschiedenis" :key="item.id + '-' + item.tijd">
                    <div class="flex justify-between items-center bg-white px-3 py-2 rounded border text-sm">
                        <span>
                            <span :class="item.type === 'eliminatie' ? 'text-purple-600' : 'text-green-600'" class="font-medium" x-text="item.naam"></span>
                        </span>
                        <span class="text-gray-400" x-text="item.tijd"></span>
                    </div>
                </template>
            </div>
        </template>
    </div>

    <!-- TERUG SECTIE: Recent afgeroepen poules -->
    @if($afgeroepen->isNotEmpty())
    <div class="mb-6 bg-orange-50 border border-orange-200 rounded-lg p-4">
        <div class="flex items-center gap-2 mb-3">
            <span class="text-orange-700 font-bold">⚠️ AL AFGEROEPEN - per ongeluk? Klik om terug te zetten:</span>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach($afgeroepen as $poule)
            <button
                onclick="zetTerug({{ $poule->id }}, this)"
                class="bg-orange-100 hover:bg-green-200 text-orange-800 px-3 py-2 rounded-lg text-sm font-medium flex items-center gap-2 transition-colors border border-orange-300"
            >
                <span>↩️</span>
                <span class="line-through opacity-70">
                    @if($poule->type === 'eliminatie')
                        Elim. {{ $poule->nummer }} - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                    @else
                        Poule {{ $poule->nummer }} - {{ $poule->leeftijdsklasse }} {{ $poule->gewichtsklasse }}
                    @endif
                </span>
                <span class="text-orange-500 text-xs">({{ $poule->afgeroepen_at->format('H:i') }})</span>
            </button>
            @endforeach
        </div>
    </div>
    @endif

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
                @php
                    $pouleNaam = $poule->is_eliminatie
                        ? "Elim. {$poule->nummer} - {$poule->leeftijdsklasse} {$poule->gewichtsklasse}"
                        : "Poule {$poule->nummer} - {$poule->leeftijdsklasse} {$poule->gewichtsklasse}";
                    $pouleType = $poule->is_eliminatie ? 'eliminatie' : 'poule';
                @endphp
                <button
                    @click="markeerAfgeroepen({{ $poule->id }}, '{{ addslashes($pouleNaam) }}', '{{ $pouleType }}')"
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
// Terug functie - zet afgeroepen poule terug naar klaar
async function zetTerug(pouleId, button) {
    try {
        button.disabled = true;
        button.innerHTML = '⏳ Bezig...';

        const response = await fetch('{{ route('toernooi.spreker.terug', $toernooi) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ poule_id: pouleId })
        });

        const data = await response.json();
        if (data.success) {
            // Pagina herladen om poule weer te tonen
            location.reload();
        } else {
            alert('Fout: ' + (data.message || 'Onbekende fout'));
            button.disabled = false;
            button.innerHTML = '↩️ Terug';
        }
    } catch (err) {
        alert('Fout: ' + err.message);
        button.disabled = false;
    }
}

function sprekerInterface() {
    const STORAGE_KEY = 'spreker_geschiedenis_{{ $toernooi->id }}';
    const vandaag = new Date().toDateString();

    return {
        toonGeschiedenis: false,
        geschiedenis: [],

        init() {
            // Laad geschiedenis uit localStorage
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                const data = JSON.parse(stored);
                // Check of het van vandaag is
                if (data.datum === vandaag) {
                    this.geschiedenis = data.items || [];
                } else {
                    // Nieuwe dag, wis geschiedenis
                    this.geschiedenis = [];
                }
            }

            // Voeg bestaande afgeroepen poules uit database toe (als nog niet in geschiedenis)
            @php
                $dbAfgeroepenData = $toernooi->poules()
                    ->whereNotNull('afgeroepen_at')
                    ->whereDate('afgeroepen_at', today())
                    ->get()
                    ->map(function($p) {
                        return [
                            'id' => $p->id,
                            'naam' => $p->type === 'eliminatie'
                                ? "Elim. {$p->nummer} - {$p->leeftijdsklasse} {$p->gewichtsklasse}"
                                : "Poule {$p->nummer} - {$p->leeftijdsklasse} {$p->gewichtsklasse}",
                            'type' => $p->type === 'eliminatie' ? 'eliminatie' : 'poule',
                            'tijd' => $p->afgeroepen_at->format('H:i')
                        ];
                    });
            @endphp
            const dbAfgeroepen = @json($dbAfgeroepenData);

            dbAfgeroepen.forEach(item => {
                // Alleen toevoegen als nog niet in geschiedenis
                if (!this.geschiedenis.find(g => g.id === item.id && g.tijd === item.tijd)) {
                    this.geschiedenis.push(item);
                }
            });

            // Sorteer op tijd (nieuwste eerst)
            this.geschiedenis.sort((a, b) => b.tijd.localeCompare(a.tijd));
            this.saveGeschiedenis();
        },

        saveGeschiedenis() {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                datum: vandaag,
                items: this.geschiedenis
            }));
        },

        addToGeschiedenis(pouleId, naam, type, tijd) {
            // Voeg toe aan begin (nieuwste eerst)
            this.geschiedenis.unshift({ id: pouleId, naam, type, tijd });
            this.saveGeschiedenis();
        },

        async markeerAfgeroepen(pouleId, pouleNaam, pouleType) {
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
                    // Voeg toe aan geschiedenis
                    const tijd = new Date().toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
                    this.addToGeschiedenis(pouleId, pouleNaam, pouleType, tijd);

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

// Clock
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent =
        now.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
}
updateClock();
setInterval(updateClock, 1000);
</script>
    </main>

    @include('partials.pwa-mobile', ['pwaApp' => 'spreker'])
</body>
</html>
