    @php
        $pwaApp = 'mat';
        // API URLs - different for device-bound vs admin access
        if (isset($toegang)) {
            $wedstrijdenUrl = route('mat.wedstrijden.device', [
                'organisator' => $toernooi->organisator->slug,
                'toernooi' => $toernooi->slug,
                'toegang' => $toegang->id,
            ]);
            $uitslagUrl = route('mat.uitslag.device', [
                'organisator' => $toernooi->organisator->slug,
                'toernooi' => $toernooi->slug,
                'toegang' => $toegang->id,
            ]);
            $huidigeWedstrijdUrl = route('mat.huidige-wedstrijd.device', [
                'organisator' => $toernooi->organisator->slug,
                'toernooi' => $toernooi->slug,
                'toegang' => $toegang->id,
            ]);
            $pouleKlaarUrl = route('mat.poule-klaar.device', [
                'organisator' => $toernooi->organisator->slug,
                'toernooi' => $toernooi->slug,
                'toegang' => $toegang->id,
            ]);
            $bracketHtmlUrl = route('mat.bracket-html.device', [
                'organisator' => $toernooi->organisator->slug,
                'toernooi' => $toernooi->slug,
                'toegang' => $toegang->id,
            ]);
        } else {
            $wedstrijdenUrl = route('toernooi.mat.wedstrijden', $toernooi->routeParams());
            $uitslagUrl = route('toernooi.mat.uitslag', $toernooi->routeParams());
            $huidigeWedstrijdUrl = route('toernooi.mat.huidige-wedstrijd', $toernooi->routeParams());
            $pouleKlaarUrl = route('toernooi.mat.poule-klaar', $toernooi->routeParams());
            $bracketHtmlUrl = route('toernooi.mat.bracket-html', $toernooi->routeParams());
        }
    @endphp
<div id="mat-interface" x-data="matInterface()" x-init="init()">
    <!-- Build: v2026.02.10-D (Blade bracket) -->
    <!-- Huidige selectie + Legenda -->
    <div class="flex items-center justify-between mb-1" x-show="blokId && matId">
        <!-- Legenda links met uitleg -->
        <div class="flex items-center gap-3 text-xs">
            <span class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-green-500"></span>
                <span class="text-gray-600">{{ __('Speelt nu') }}</span>
            </span>
            <span class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-yellow-400"></span>
                <span class="text-gray-600">{{ __('Staat klaar') }}</span>
            </span>
            <span class="flex items-center gap-1">
                <span class="w-3 h-3 rounded bg-blue-400"></span>
                <span class="text-gray-600">{{ __('Gereed maken') }}</span>
            </span>
            <span class="text-gray-400 ml-2">|</span>
            <span class="text-gray-400">{{ __('Dubbelklik op wedstrijd om klaar te zetten') }}</span>
        </div>
        <!-- Blok/Mat selectie + update knop rechts -->
        <div class="text-sm text-gray-600 flex items-center gap-3">
            <span class="font-bold">Blok <span x-text="blokkenData.find(b => b.id == blokId)?.nummer"></span></span>
            &bull;
            <span class="font-bold">Mat <span x-text="mattenData.find(m => m.id == matId)?.nummer"></span></span>
            <a href="#blok-mat-keuze" class="text-purple-600 hover:underline">(wijzig)</a>
            <button @click="refreshAll()" class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs font-medium flex items-center gap-1" :class="{ 'animate-spin': isRefreshing }">
                <svg x-show="!isRefreshing" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span x-show="isRefreshing">...</span>
                <span x-show="!isRefreshing">Update</span>
            </button>
        </div>
    </div>

    <template x-for="poule in poules" :key="poule.poule_id">
        <div class="bg-white rounded-lg shadow mb-3 overflow-hidden">
            <!-- Header -->
            <div :class="poule.type === 'eliminatie' ? 'bg-purple-700' : 'bg-green-700'" class="text-white px-3 py-1.5 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold">
                        <span x-text="'P#' + poule.poule_nummer + ' ' + (poule.type === 'eliminatie' ? __eliminatie : __poule) + ' - ' + poule.leeftijdsklasse + ' ' + poule.gewichtsklasse + ' | Blok ' + poule.blok_nummer + ' - Mat ' + poule.mat_nummer"></span>
                        (<span x-text="poule.judoka_count"></span> judoka's, <span x-text="poule.wedstrijden.length"></span>w)
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Geen wedstrijden: toon waarschuwing -->
                    <div x-show="poule.wedstrijden.length === 0" class="bg-red-500 text-white px-3 py-1 rounded text-sm font-medium">
                        ⚠ Geen wedstrijden
                    </div>

                    <!-- Afgerond: toon klaar tijdstip -->
                    <div x-show="poule.spreker_klaar" class="bg-white px-3 py-1 rounded text-sm font-bold" :class="poule.type === 'eliminatie' ? 'text-purple-700' : 'text-green-700'">
                        ✓ {{ __('Klaar om:') }} <span x-text="poule.spreker_klaar_tijd"></span>
                    </div>
                    <!-- Barrage knop: toon als er een 3-weg gelijkspel is -->
                    <button
                        x-show="heeftBarrageNodig(poule) && !poule.spreker_klaar"
                        @click="maakBarrage(poule)"
                        class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-1 rounded text-sm font-bold"
                        title="3+ judoka's met gelijke stand - maak barrage poule"
                    >
                        ⚔ Barrage
                    </button>

                    <!-- Nog niet klaar maar wel afgerond: toon knop -->
                    <button
                        x-show="isPouleAfgerond(poule) && !poule.spreker_klaar && !heeftBarrageNodig(poule)"
                        @click="markeerKlaar(poule)"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm font-bold animate-pulse"
                    >
                        ✓ Afronden
                    </button>
                </div>
            </div>

            <!-- ELIMINATIE WEERGAVE - Drag & Drop Bracket met A/B Tabs -->
            <template x-if="poule.type === 'eliminatie'">
                <div class="p-1" x-data="{ activeTab: poule.groep_filter || 'A' }">
                    <!-- Tabs + Swap Ruimte -->
                    <div class="flex mb-1 border-b border-gray-200 justify-between">
                        <div class="flex">
                            <button x-show="!poule.groep_filter || poule.groep_filter === 'A'"
                                    @click="activeTab = 'A'"
                                    :class="activeTab === 'A' ? 'border-purple-600 text-purple-700 bg-purple-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="px-4 py-1 text-xs font-bold border-b-2 transition-colors">
                                {{ __('Groep A (Hoofdboom)') }} <span x-text="'(' + poule.judoka_count + ')'"></span>
                            </button>
                            <template x-if="heeftHerkansing(poule) && (!poule.groep_filter || poule.groep_filter === 'B')">
                                <button @click="activeTab = 'B'"
                                        :class="activeTab === 'B' ? 'border-purple-600 text-purple-700 bg-purple-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="px-4 py-1 text-xs font-bold border-b-2 transition-colors">
                                    {{ __('Groep B (Herkansing)') }} <span x-text="'(' + (poule.judoka_count - 2) + ')'"></span>
                                </button>
                            </template>
                        </div>
                        <!-- Swap Ruimte - alleen zichtbaar VOOR eerste wedstrijd (seeding fase) -->
                        <div x-show="!isBracketLocked(poule)" class="flex items-center gap-2 px-2">
                            <span class="text-sm font-medium text-gray-600">
                                Swap:
                            </span>
                            <div class="flex flex-wrap gap-1 min-w-[200px] max-w-[400px] min-h-[32px] border-2 border-dashed rounded-lg px-2 py-1 bg-orange-50 border-orange-400 bracket-drop bracket-swap"
                                 :id="'swap-ruimte-' + poule.poule_id"
                                 data-drop-handler="dropInSwap"
                                 :data-poule-id="poule.poule_id">
                                <template x-for="judoka in getSwapJudokas(poule.poule_id)" :key="judoka.id">
                                    <div class="bg-orange-500 text-white text-sm px-2 py-1 rounded cursor-move shadow-sm hover:bg-orange-600 bracket-judoka"
                                         :title="judoka.naam"
                                         :data-drag="JSON.stringify({judokaId: judoka.id, judokaNaam: judoka.naam, fromSwap: true, pouleId: poule.poule_id, pouleIsLocked: false})"
                                         x-text="judoka.naam">
                                    </div>
                                </template>
                                <span x-show="getSwapJudokas(poule.poule_id).length === 0" class="text-sm italic py-1 text-orange-400">{{ __('Sleep judoka hierheen') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Groep A - Hoofdboom -->
                    <div x-show="activeTab === 'A'">
                        <div class="flex justify-between items-center flex-wrap gap-1">
                            <div class="flex items-center gap-1">
                                <button @click="laadBracketHtml(poule.poule_id, 'A')"
                                        class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-600 hover:bg-yellow-300">
                                    🔄 {{ __('Herlaad') }}
                                </button>
                                <button @click="debugSlots = !debugSlots; laadBracketHtml(poule.poule_id, 'A')"
                                        class="text-xs px-2 py-1 rounded hover:bg-yellow-300"
                                        :class="debugSlots ? 'bg-yellow-200 text-yellow-800' : 'bg-gray-100 text-gray-600'">
                                    #{{ __('Nrs') }}
                                </button>
                            </div>
                            <span class="text-gray-400">{{ __('Dubbelklik op wedstrijd om klaar te zetten') }}</span>
                            <div class="text-sm text-gray-600 cursor-pointer hover:text-gray-800 bracket-drop bracket-delete"
                                 data-drop-handler="verwijderJudoka">
                                🗑️ {{ __('verwijder') }}
                            </div>
                        </div>
                        <div class="bracket-container overflow-x-auto pb-2"
                             :id="'bracket-container-' + poule.poule_id + '-A'"
                             x-init="$nextTick(() => laadBracketHtml(poule.poule_id, 'A'))">
                            <div class="text-gray-400 text-sm py-4">{{ __('Bracket laden...') }}</div>
                        </div>
                    </div>

                    <!-- Groep B - Herkansing -->
                    <div x-show="activeTab === 'B'">
                        <div class="flex justify-between items-center flex-wrap gap-1">
                            <div class="flex items-center gap-1">
                                <button @click="laadBracketHtml(poule.poule_id, 'B')"
                                        class="text-xs px-2 py-1 rounded bg-gray-100 text-gray-600 hover:bg-yellow-300">
                                    🔄 {{ __('Herlaad') }}
                                </button>
                                <button @click="debugSlots = !debugSlots; laadBracketHtml(poule.poule_id, 'B')"
                                        class="text-xs px-2 py-1 rounded hover:bg-yellow-300"
                                        :class="debugSlots ? 'bg-yellow-200 text-yellow-800' : 'bg-gray-100 text-gray-600'">
                                    #{{ __('Nrs') }}
                                </button>
                            </div>
                            <span class="text-gray-400">{{ __('Dubbelklik op wedstrijd om klaar te zetten') }}</span>
                            <div class="text-sm text-gray-600 cursor-pointer hover:text-gray-800 bracket-drop bracket-delete"
                                 data-drop-handler="verwijderJudoka">
                                🗑️ {{ __('verwijder') }}
                            </div>
                        </div>
                        <div class="bracket-container overflow-x-auto pb-2"
                             :id="'bracket-container-' + poule.poule_id + '-B'"
                             x-init="$nextTick(() => { if (heeftHerkansing(poule)) laadBracketHtml(poule.poule_id, 'B') })">
                            <div class="text-gray-400 text-sm py-4">{{ __('Bracket laden...') }}</div>
                        </div>
                    </div>

                </div>
            </template>

            <!-- POULE WEERGAVE (Matrix Table) -->
            <template x-if="poule.type !== 'eliminatie'">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-200 border-b-2 border-gray-400">
                                <th class="px-2 py-1 text-left font-bold text-gray-700 sticky left-0 bg-gray-200 min-w-[240px]">{{ __('Naam') }}</th>
                                <template x-for="(w, idx) in poule.wedstrijden" :key="'h-' + idx">
                                    <th class="px-0 py-1 text-center font-bold w-14 border-l border-gray-300 cursor-pointer select-none transition-colors"
                                        :class="getWedstrijdKleurClass(poule, w, idx)"
                                        @click="toggleVolgendeWedstrijd(poule, w)"
                                        :title="getWedstrijdTitel(poule, w, idx)"
                                        colspan="2">
                                        <div class="text-xs font-bold" x-text="(idx + 1)"></div>
                                    </th>
                                </template>
                                <template x-if="poule.is_punten_competitie">
                                    <th class="px-1 py-1 text-center font-bold text-gray-700 bg-green-100 border-l-2 border-green-300 w-10 text-xs">W</th>
                                </template>
                                <template x-if="!poule.is_punten_competitie">
                                    <th class="px-1 py-1 text-center font-bold text-gray-700 bg-blue-100 border-l-2 border-blue-300 w-10 text-xs">WP</th>
                                </template>
                                <template x-if="!poule.is_punten_competitie">
                                    <th class="px-1 py-1 text-center font-bold text-gray-700 bg-blue-100 w-10 text-xs">JP</th>
                                </template>
                                <template x-if="!poule.is_punten_competitie">
                                    <th class="px-1 py-1 text-center font-bold text-gray-700 bg-yellow-100 border-l-2 border-yellow-300 w-8 text-xs">#</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(judoka, jIdx) in poule.judokas" :key="judoka.id">
                                <tr class="border-b">
                                    <!-- Nr + Naam -->
                                    <td class="px-2 py-1 font-medium sticky left-0 bg-white border-r border-gray-200 text-sm">
                                        <span class="font-bold" x-text="(jIdx + 1) + '. ' + judoka.naam"></span>
                                        <span class="text-gray-500 text-xs" x-text="judoka.club ? ' (' + judoka.club + ')' : ''"></span>
                                    </td>

                                    <!-- Wedstrijd cellen -->
                                    <template x-for="(w, wIdx) in poule.wedstrijden" :key="'c-' + judoka.id + '-' + wIdx">
                                        <td class="px-0 py-0.5 text-center border-l"
                                            :class="speeltInWedstrijd(judoka.id, w) ? 'border-gray-200 bg-white' : 'bg-gray-600 border-gray-500'"
                                            colspan="2">
                                            <!-- Alleen inputs tonen als judoka in deze wedstrijd speelt -->
                                            <div x-show="speeltInWedstrijd(judoka.id, w)" class="flex justify-center gap-0">
                                                <!-- WP -->
                                                <input
                                                    type="text"
                                                    inputmode="numeric"
                                                    maxlength="1"
                                                    class="w-5 text-center border border-gray-300 rounded-sm text-xs py-0.5 font-bold"
                                                    :class="getWpClass(getWP(w, judoka.id))"
                                                    :value="getWP(w, judoka.id)"
                                                    :disabled="!isInvoerToegestaan(w)"
                                                    @input="updateWP(w, judoka.id, $event.target.value)"
                                                    @blur="saveScore(w, poule)"
                                                >
                                                <!-- JP met dropdown -->
                                                <select
                                                    class="w-7 text-center border border-gray-300 rounded-sm text-xs py-0.5 appearance-none bg-white"
                                                    :class="!isInvoerToegestaan(w) ? 'bg-gray-100 cursor-not-allowed' : ''"
                                                    :value="getJP(w, judoka.id)"
                                                    :disabled="!isInvoerToegestaan(w)"
                                                    @change="updateJP(w, judoka.id, $event.target.value); saveScore(w, poule)"
                                                >
                                                    <option value=""></option>
                                                    <option value="0">0</option>
                                                    <option value="5">5</option>
                                                    <option value="7">7</option>
                                                    <option value="10">10</option>
                                                </select>
                                            </div>
                                        </td>
                                    </template>

                                    <!-- Punten competitie: alleen aantal wins -->
                                    <template x-if="poule.is_punten_competitie">
                                        <td class="px-0.5 py-0.5 text-center font-bold bg-green-50 border-l-2 border-green-300 text-green-800 text-xs"
                                            x-text="getTotaalWins(poule, judoka.id)"></td>
                                    </template>
                                    <!-- Normale poule: WP + JP + plaats -->
                                    <template x-if="!poule.is_punten_competitie">
                                        <td class="px-0.5 py-0.5 text-center font-bold bg-blue-50 border-l-2 border-blue-300 text-blue-800 text-xs"
                                            x-text="getTotaalWP(poule, judoka.id)"></td>
                                    </template>
                                    <template x-if="!poule.is_punten_competitie">
                                        <td class="px-0.5 py-0.5 text-center font-bold bg-blue-50 text-blue-800 text-xs"
                                            x-text="getTotaalJP(poule, judoka.id)"></td>
                                    </template>
                                    <template x-if="!poule.is_punten_competitie">
                                        <td class="px-0.5 py-0.5 text-center font-bold border-l-2 border-yellow-300 text-xs"
                                            :class="isPouleAfgerond(poule) ? getPlaatsClass(getPlaats(poule, judoka.id)) : 'bg-yellow-50'"
                                            x-text="isPouleAfgerond(poule) ? getPlaats(poule, judoka.id) : '-'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </template>

    <div x-show="poules.length === 0 && blokId && matId" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        {{ __('Geen poules op deze mat in dit blok') }}
    </div>

    <!-- Blok/Mat keuze onderaan -->
    <div id="blok-mat-keuze" class="bg-gray-100 rounded-lg p-4 mt-8 border border-gray-300">
        <h3 class="text-sm font-bold text-gray-600 mb-3">{{ __('Blok & Mat selectie') }}</h3>
        <div class="flex gap-4">
            <div class="w-40">
                <label class="block text-gray-600 text-sm mb-1">{{ __('Blok') }}</label>
                <select x-model="blokId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">{{ __('Selecteer...') }}</option>
                    @foreach($blokken as $blok)
                    <option value="{{ $blok->id }}">{{ __('Blok') }} {{ $blok->nummer }}
                        @if($blok->weging_gesloten) ({{ __('gesloten') }}) @endif
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="block text-gray-600 text-sm mb-1">{{ __('Mat') }}</label>
                <select x-model="matId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">{{ __('Selecteer...') }}</option>
                    @foreach($matten as $mat)
                    <option value="{{ $mat->id }}">Mat {{ $mat->nummer }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<script>
// === TRANSLATION STRINGS ===
const __eliminatie = @json(__('Eliminatie'));
const __poule = @json(__('Poule'));
const __finale = @json(__('Finale'));
const __brons = @json(__('Brons'));
const __correctieWinnaarWijzigen = @json(__('CORRECTIE: Winnaar wijzigen?'));
const __wasNietWinnaar = @json(__(':naam was niet de winnaar van deze wedstrijd.'));
const __wilJeAlsWinnaarInstellen = @json(__('Wil je :naam als nieuwe winnaar instellen?'));
const __oudeWinnaarVerwijderd = @json(__('De oude winnaar wordt uit de volgende ronde verwijderd en de B-groep wordt aangepast'));
const __bracketVergrendeld = @json(__('BRACKET VERGRENDELD'));
const __probeertTeVerwijderen = @json(__('Je probeert :naam te verwijderen.'));
const __ditKanAlleenDoorAdmin = @json(__('Dit kan alleen door een admin.'));
const __voerAdminWachtwoordIn = @json(__('Voer het admin wachtwoord in:'));
const __onjuistWachtwoord = @json(__('Onjuist wachtwoord! Verwijdering geannuleerd.'));

// === SWAP RUIMTE VOOR SEEDING ===
// Tijdelijke opslag voor judoka's tijdens het seeden
window.swapRuimte = {}; // { pouleId: [{ id, naam }, ...] }

// Haal swap judoka's op voor een poule
window.getSwapJudokas = function(pouleId) {
    return window.swapRuimte[pouleId] || [];
};

// Voeg judoka toe aan swap ruimte
window.addToSwap = function(pouleId, judoka) {
    if (!window.swapRuimte[pouleId]) {
        window.swapRuimte[pouleId] = [];
    }
    // Voorkom duplicaten
    if (!window.swapRuimte[pouleId].find(j => j.id === judoka.id)) {
        window.swapRuimte[pouleId].push(judoka);
    }
};

// Verwijder judoka uit swap ruimte
window.removeFromSwap = function(pouleId, judokaId) {
    if (window.swapRuimte[pouleId]) {
        window.swapRuimte[pouleId] = window.swapRuimte[pouleId].filter(j => j.id !== judokaId);
    }
};

// Drop handler voor swap ruimte
window.dropInSwap = async function(event, pouleId, isLocked = false) {
    event.preventDefault();
    const data = JSON.parse(event.dataTransfer.getData('text/plain'));


    // Als bracket locked is, vraag admin wachtwoord
    if (isLocked) {
        const wachtwoord = prompt(
            '🔒 BRACKET VERGRENDELD\n\n' +
            `Je wilt ${data.judokaNaam || 'deze judoka'} naar de swap verplaatsen.\n` +
            'Dit kan alleen door een admin.\n\n' +
            'Voer het admin wachtwoord in:'
        );

        if (!wachtwoord) {
            return; // Geannuleerd
        }

        const adminWachtwoord = '{{ $toernooi->admin_wachtwoord ?? "admin123" }}';
        if (wachtwoord !== adminWachtwoord) {
            alert('❌ Onjuist wachtwoord!\n\nActie geannuleerd.');
            return;
        }
    }

    // Voeg toe aan swap ruimte
    window.addToSwap(pouleId, { id: data.judokaId, naam: data.judokaNaam });

    // Verwijder uit huidige wedstrijd
    if (data.wedstrijdId && data.positie) {
        try {
            const response = await fetch(`{{ route('toernooi.mat.verwijder-judoka', $toernooi->routeParams()) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    wedstrijd_id: data.wedstrijdId,
                    positie: data.positie,
                    alleen_positie: !isLocked  // Bij locked: ook uitslag resetten
                })
            });
            await response.json();
        } catch (err) {
            console.error('Fout bij verwijderen:', err);
        }
    }

    // Refresh display
    Alpine.evaluate(document.getElementById('mat-interface'), 'laadWedstrijden()');
};

// Clean drag image: toon judoka naam als witte chip tijdens slepen
window.setCleanDragImage = function(event, naam) {
    const ghost = document.createElement('div');
    ghost.textContent = naam;
    ghost.style.cssText = 'position:fixed;top:-1000px;left:-1000px;padding:2px 8px;background:#fff;border:1px solid #9ca3af;border-radius:4px;font-size:12px;white-space:nowrap;z-index:9999;';
    document.body.appendChild(ghost);
    event.dataTransfer.setDragImage(ghost, 0, 0);
    requestAnimationFrame(() => ghost.remove());
};

// Escape HTML to prevent XSS in innerHTML
window.escapeHtml = function(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
};

// Global drop handler - plaats judoka in slot
window.dropJudoka = async function(event, targetWedstrijdId, positie, pouleId = null, huidigeBewoner = null) {
    event.preventDefault();

    // Prevent parallel drops (race condition guard)
    if (window._isDroppingJudoka) return false;
    window._isDroppingJudoka = true;
    const data = JSON.parse(event.dataTransfer.getData('text/plain'));

    // Voeg target info toe aan data voor seeding logica
    if (pouleId) data.pouleId = pouleId;
    if (huidigeBewoner && huidigeBewoner.id !== data.judokaId) {
        data.targetHuidigeJudoka = huidigeBewoner;
    }

    // Check of we in seeding-fase zijn (geen wedstrijden gespeeld in deze poule)
    const isLocked = data.pouleIsLocked === true;
    const naam = data.judokaNaam || 'Deze judoka';

    // Check 1: Dezelfde wedstrijd?
    if (data.wedstrijdId == targetWedstrijdId) {
        if (data.positie === positie) {
            return false; // Teruggezet op oude plek
        }
        // Zelfde wedstrijd maar andere positie = blokkeer
        alert(
            `❌ GEBLOKKEERD: Kan niet verplaatsen binnen dezelfde wedstrijd!\n\n` +
            `${naam} staat al in deze wedstrijd.`
        );
        return false;
    }

    // Check 1b: Slot validatie ALTIJD (niet alleen bij locked!)
    // Winnaar mag alleen naar de correcte positie (wit of blauw) in de volgende ronde
    if (data.volgendeWedstrijdId && data.volgendeWedstrijdId == targetWedstrijdId && data.winnaarNaarSlot) {
        if (data.winnaarNaarSlot !== positie) {
            const juistePositie = data.winnaarNaarSlot === 'wit' ? 'WIT (boven)' : 'BLAUW (onder)';
            const gekozenPositie = positie === 'wit' ? 'WIT (boven)' : 'BLAUW (onder)';
            alert(
                `❌ VERKEERDE POSITIE!\n\n` +
                `${naam} moet op ${juistePositie} staan, niet op ${gekozenPositie}.`
            );
            return false;
        }
    }

    // Check 2: Winnaar doorschuiven naar VERKEERDE positie = GEBLOKKEERD (met uitzonderingen)
    if (data.volgendeWedstrijdId && data.isGespeeld && data.isWinnaar) {
        // Dit is een winnaar die doorschuift - strikt checken
        if (data.volgendeWedstrijdId == targetWedstrijdId) {
            // Juiste wedstrijd, maar check positie
            if (data.winnaarNaarSlot && data.winnaarNaarSlot !== positie) {
                const juistePositie = data.winnaarNaarSlot === 'wit' ? 'WIT (boven)' : 'BLAUW (onder)';
                const gekozenPositie = positie === 'wit' ? 'WIT (boven)' : 'BLAUW (onder)';
                alert(
                    `❌ VERKEERDE POSITIE!\n\n` +
                    `${naam} moet op ${juistePositie} staan, niet op ${gekozenPositie}.\n\n` +
                    `Zet de winnaar EERST op de juiste plek.\n` +
                    `Swappen binnen de ronde kan daarna (met admin wachtwoord).`
                );
                return false;
            }
            // Juiste wedstrijd EN juiste positie - doorgaan!
        } else {
            // Verkeerde wedstrijd - blokkeer alleen als NIET naar wit slot van volgende ronde
            // (Dit staat toe dat winnaars naar (2) rondes gaan zelfs als volgendeWedstrijdId niet perfect matcht)
            alert(
                `❌ VERKEERDE WEDSTRIJD!\n\n` +
                `${naam} moet naar een andere wedstrijd in het schema.\n\n` +
                `Zet de winnaar EERST op de juiste plek.`
            );
            return false;
        }
    }

    // Slot validatie ALTIJD als dit een winnaar-doorschuif is (naar volgende ronde)
    // Seeding is BINNEN dezelfde ronde, niet naar volgende ronde - dus daar geldt geen slot validatie
    const isWinnaarDoorschuifPoging = data.volgendeWedstrijdId && String(data.volgendeWedstrijdId) === String(targetWedstrijdId);

    if (isWinnaarDoorschuifPoging) {
        // Dit is een winnaar-doorschuif naar de volgende ronde - slot validatie is VERPLICHT
        if (data.winnaarNaarSlot && data.winnaarNaarSlot !== positie) {
            const juistePositie = data.winnaarNaarSlot === 'wit' ? 'WIT (boven)' : 'BLAUW (onder)';
            const gekozenPositie = positie === 'wit' ? 'WIT (boven)' : 'BLAUW (onder)';
            alert(
                `❌ GEBLOKKEERD: Verkeerde positie!\n\n` +
                `${naam} moet op ${juistePositie} staan, niet op ${gekozenPositie}.`
            );
            return false;
        }
    } else if (isLocked && data.volgendeWedstrijdId) {
        alert(
            `❌ GEBLOKKEERD: Verkeerde wedstrijd!\n\n` +
            `${naam} kan alleen naar wedstrijd ${data.volgendeWedstrijdId}, niet naar ${targetWedstrijdId}.\n` +
            `Sleep naar het juiste vak.`
        );
        return false;
    }

    // ============================================================
    // ADMIN WACHTWOORD VOOR CORRECTIES/SWAPS
    // Alleen voor legitieme wijzigingen die wel op juiste plek zijn
    // ============================================================

    if (isLocked) {
        // Check of dit een normale winnaar-doorschuif is (toegestaan zonder wachtwoord)
        const isNormaleWinnaarDoorschuif = data.volgendeWedstrijdId == targetWedstrijdId &&
                                            (!data.isGespeeld || data.isWinnaar);

        if (!isNormaleWinnaarDoorschuif) {
            // Bepaal of dit een correctie is (wedstrijd gespeeld, dit is niet de winnaar)
            const isCorrectiePoging = data.isGespeeld && !data.isWinnaar;
            // Of een vrije plaatsing (geen volgendeWedstrijdId)
            const isVrijePlaatsing = !data.volgendeWedstrijdId;

            const wachtwoord = prompt(
                '🔒 BRACKET VERGRENDELD\n\n' +
                (isCorrectiePoging
                    ? `CORRECTIE: ${naam} was niet de winnaar.\nWil je de uitslag corrigeren?\n\n`
                    : isVrijePlaatsing
                        ? `PLAATSING: ${naam} handmatig plaatsen.\n\n`
                        : 'De bracket is vastgezet na de eerste wedstrijd.\n') +
                'Alleen een admin kan wijzigingen maken.\n\n' +
                'Voer het admin wachtwoord in:'
            );

            if (!wachtwoord) {
                return false; // Geannuleerd
            }

            // Check wachtwoord
            const adminWachtwoord = '{{ $toernooi->admin_wachtwoord ?? "admin123" }}';
            if (wachtwoord !== adminWachtwoord) {
                alert('❌ Onjuist wachtwoord!\n\nWijziging geannuleerd.');
                return false;
            }

            // Admin geautoriseerd
            data.isAdminOverride = true;

            // Zet correctie flag als dit een correctie is
            if (isCorrectiePoging) {
                data.isCorrectie = true;
            }
        }
    }

    // Check 3: Extra logging (checks al gedaan boven)
    // Check 3: Validatie volgendeWedstrijdId + winnaarNaarSlot
    if (isLocked && data.volgendeWedstrijdId) {
        // Checks al gedaan boven - hier alleen logging
        // Validatie passed - juiste wedstrijd en positie

        // Check 2c: Als wedstrijd AL gespeeld is en dit is NIET de winnaar = CORRECTIE
        if (!data.isAdminOverride && data.isGespeeld && !data.isWinnaar) {
            if (!confirm(
                `⚠️ ${__correctieWinnaarWijzigen}\n\n` +
                `${__wasNietWinnaar.replace(':naam', naam)}\n\n` +
                `${__wilJeAlsWinnaarInstellen.replace(':naam', naam)}\n` +
                `(${__oudeWinnaarVerwijderd})`
            )) {
                return false; // Gebruiker annuleerde
            }
            // Gebruiker bevestigde - markeer als correctie
            data.isCorrectie = true;
        }
    }

    try {
        // Bepaal of dit een SEEDING (move binnen ronde) of WEDSTRIJD WINNEN (copy naar volgende ronde) is
        // Seeding = target is NIET de volgende_wedstrijd_id
        // Winnen = target IS de volgende_wedstrijd_id
        const isWinnaarDoorschuif = data.volgendeWedstrijdId && data.volgendeWedstrijdId == targetWedstrijdId;
        const isSeeding = !isWinnaarDoorschuif && !isLocked;

        if (isSeeding) {
            // SEEDING MODE: Verplaatsen binnen zelfde ronde (MOVE)
            if (data.targetHuidigeJudoka) {
                window.addToSwap(data.pouleId, {
                    id: data.targetHuidigeJudoka.id,
                    naam: data.targetHuidigeJudoka.naam
                });
            }

            // Als judoka uit swap komt, verwijder uit swap
            if (data.fromSwap && data.pouleId) {
                window.removeFromSwap(data.pouleId, data.judokaId);
            }

            // Verwijder judoka uit oude plek (MOVE, niet COPY)
            if (data.wedstrijdId && data.positie && !data.fromSwap) {
                await fetch(`{{ route('toernooi.mat.verwijder-judoka', $toernooi->routeParams()) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        wedstrijd_id: data.wedstrijdId,
                        positie: data.positie,
                        alleen_positie: true
                    })
                });
            }
        } else {
            // WEDSTRIJD WINNEN: Doorschuiven naar volgende ronde (COPY)
            // WINNAAR DOORSCHUIF: Judoka blijft in vorige ronde met groene stip
        }

        const requestBody = {
            wedstrijd_id: targetWedstrijdId,
            judoka_id: data.judokaId,
            positie: positie,
            bron_wedstrijd_id: data.wedstrijdId || null,
            is_correctie: data.isCorrectie || false
        };

        const response = await fetch(`{{ route('toernooi.mat.plaats-judoka', $toernooi->routeParams()) }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(requestBody)
        });

        const result = await response.json();

        if (!response.ok) {
            // Toon foutmelding - actie geblokkeerd
            alert('❌ GEBLOKKEERD:\n\n' + (result.error || 'Onbekende fout'));
            return false;
        }

        // Toon correcties aan admin als die er zijn
        if (result.correcties && result.correcties.length > 0) {
            alert('✅ Automatische correcties uitgevoerd:\n\n• ' + result.correcties.join('\n• '));
        }

        // Update bracket slots via pure DOM (geen Alpine re-render!)
        const matEl = document.getElementById('mat-interface');
        if (matEl && result.updated_slots) {
            const comp = Alpine.$data(matEl);
            if (comp) {
                comp.updateAlleBracketSlots(result.updated_slots, pouleId);
            }
        }

        return true; // Succes

    } catch (err) {
        console.error('Drop error:', err);
        alert('❌ Fout bij plaatsen: ' + err.message);
        // Bij fout: location.reload() — zelfde als andere pages
        location.reload();
        return false;
    } finally {
        window._isDroppingJudoka = false;
    }
};

// Global drop handler - medaille plaatsing (goud/zilver)
window.dropOpMedaille = async function(event, finaleId, medaille, pouleId) {
    event.preventDefault();
    if (!finaleId) {
        alert('❌ Geen finale gevonden!');
        return;
    }

    const data = JSON.parse(event.dataTransfer.getData('text/plain'));
    const naam = data.judokaNaam || 'Deze judoka';

    // Check of judoka uit de finale komt
    if (data.wedstrijdId != finaleId) {
        alert(`❌ ${naam} komt niet uit de finale!\n\nAlleen finalisten kunnen op goud/zilver geplaatst worden.`);
        return;
    }

    // Bepaal winnaar op basis van medaille keuze
    // Goud = deze judoka wint, Zilver = andere judoka wint
    const winnaarId = medaille === 'goud' ? data.judokaId : null;

    try {
        const response = await fetch(`{{ route('toernooi.mat.finale-uitslag', $toernooi->routeParams()) }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                wedstrijd_id: finaleId,
                winnaar_id: winnaarId,
                geplaatste_judoka_id: data.judokaId,
                medaille: medaille  // 'goud' of 'zilver'
            })
        });

        const result = await response.json();

        if (!response.ok) {
            alert('❌ Fout:\n\n' + (result.error || 'Onbekende fout'));
            return;
        }

    } catch (err) {
        console.error('Medaille drop error:', err);
        alert('❌ Fout bij medaille plaatsing: ' + err.message);
        location.reload();
    }
};

// Global drop handler - verwijder judoka uit slot
window.verwijderJudoka = async function(event) {
    event.preventDefault();
    const data = JSON.parse(event.dataTransfer.getData('text/plain'));
    const isLocked = data.pouleIsLocked === true;
    const naam = data.judokaNaam || 'Deze judoka';

    // Als bracket locked is, vraag admin wachtwoord
    if (isLocked) {
        const wachtwoord = prompt(
            `🔒 ${__bracketVergrendeld}\n\n` +
            `${__probeertTeVerwijderen.replace(':naam', naam)}\n` +
            `${__ditKanAlleenDoorAdmin}\n\n` +
            `${__voerAdminWachtwoordIn}`
        );

        if (!wachtwoord) {
            return; // Geannuleerd
        }

        const adminWachtwoord = '{{ $toernooi->admin_wachtwoord ?? "admin123" }}';
        if (wachtwoord !== adminWachtwoord) {
            alert(`❌ ${__onjuistWachtwoord}`);
            return;
        }
    }

    try {
        const response = await fetch(`{{ route('toernooi.mat.verwijder-judoka', $toernooi->routeParams()) }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                wedstrijd_id: data.wedstrijdId,
                judoka_id: data.judokaId
            })
        });

        if (!response.ok) {
            alert('❌ Fout bij verwijderen');
            location.reload();
        }
    } catch (err) {
        console.error('Verwijder error:', err);
        location.reload();
    }
};

// Global double-click handler - beurtaanduiding in eliminatie bracket
// Triggert hetzelfde 3-kleuren systeem als bij poules (groen/geel/blauw)
window.dblClickBracket = function(wedstrijdId, pouleId) {
    const el = document.getElementById('mat-interface');
    if (!el) return;
    const comp = Alpine.$data(el);
    if (!comp) return;
    const poule = comp.poules.find(p => p.poule_id === pouleId);
    if (!poule) return;
    const wedstrijd = poule.wedstrijden.find(w => w.id === wedstrijdId);
    if (!wedstrijd) return;
    comp.toggleVolgendeWedstrijd(poule, wedstrijd);
};

function matInterface() {
    const urlParams = new URLSearchParams(window.location.search);
    const blokNummer = urlParams.get('blok');
    const blokkenData = @json($blokken->map(fn($b) => ['id' => $b->id, 'nummer' => $b->nummer]));
    const mattenData = @json($matten->map(fn($m) => ['id' => $m->id, 'nummer' => $m->nummer]));
    const voorgeselecteerdBlok = blokNummer ? blokkenData.find(b => b.nummer == blokNummer) : null;
    const isDeviceBound = {{ isset($isDeviceBound) && $isDeviceBound ? 'true' : 'false' }};
    const gebondenMatNummer = {{ isset($matNummer) && $matNummer ? $matNummer : 'null' }};

    // LocalStorage key voor dit toernooi (include mat for device-bound)
    const storageKey = isDeviceBound && gebondenMatNummer
        ? 'mat_interface_{{ $toernooi->id }}_mat_' + gebondenMatNummer
        : 'mat_interface_{{ $toernooi->id }}';

    // Laad laatst geselecteerde blok/mat uit localStorage
    const opgeslagen = JSON.parse(localStorage.getItem(storageKey) || '{}');

    // Validate saved blok/mat still exist (may have been deleted)
    let savedBlokId = opgeslagen.blokId;
    let savedMatId = opgeslagen.matId;

    // Check if saved blok still exists
    if (savedBlokId && !blokkenData.find(b => b.id == savedBlokId)) {
        console.log('[Mat] Saved blok', savedBlokId, 'no longer exists, clearing');
        savedBlokId = '';
        savedMatId = '';
        localStorage.removeItem(storageKey);
    }
    // Check if saved mat still exists
    if (savedMatId && !mattenData.find(m => m.id == savedMatId)) {
        console.log('[Mat] Saved mat', savedMatId, 'no longer exists, clearing');
        savedMatId = '';
        localStorage.removeItem(storageKey);
    }

    // For device-bound: find mat by gebonden nummer and pre-select it
    const gebondenMat = isDeviceBound && gebondenMatNummer
        ? mattenData.find(m => m.nummer == gebondenMatNummer)
        : null;
    const forcedMatId = gebondenMat ? String(gebondenMat.id) : null;

    return {
        blokId: savedBlokId || (voorgeselecteerdBlok ? String(voorgeselecteerdBlok.id) : ''),
        matId: forcedMatId || savedMatId || '',
        poules: [],
        matSelectie: null,  // Mat-level wedstrijd selectie {actieve_wedstrijd_id, volgende_wedstrijd_id, gereedmaken_wedstrijd_id}
        blokkenData,
        mattenData,
        isDeviceBound,
        gebondenMatNummer,
        debugSlots: false,  // Toggle om slot nummers te tonen
        isRefreshing: false, // Loading state voor update knop
        _isLoadingWedstrijden: false, // Guard tegen dubbele API calls
        _pendingReload: false, // Herlaad na afloop van huidige load

        init() {
            console.log('[Mat] Build v2026.02.10-D (Blade bracket)');
            // Device-bound: always pre-select the bound mat (but allow switching)
            if (isDeviceBound && forcedMatId && !savedMatId) {
                this.matId = forcedMatId;
            }

            // Als we opgeslagen waardes hebben, gebruik die
            if (this.blokId && this.matId) {
                this.laadWedstrijden();
            } else if (this.blokId && @json($matten->count()) > 0) {
                // Fallback: eerste mat selecteren (of gebonden mat)
                this.matId = forcedMatId || '{{ $matten->first()?->id }}';
                this.laadWedstrijden();
            }
        },

        // Sla selectie op in localStorage
        opslaanSelectie() {
            localStorage.setItem(storageKey, JSON.stringify({
                blokId: this.blokId,
                matId: this.matId
            }));
        },

        // Helper voor swap ruimte - haalt judoka's uit window.swapRuimte
        getSwapJudokas(pouleId) {
            return window.getSwapJudokas(pouleId);
        },

        // Check of bracket locked is (minimaal 1 wedstrijd gespeeld)
        isBracketLocked(poule) {
            if (poule.type !== 'eliminatie') return true;
            return poule.wedstrijden.some(w => w.is_gespeeld === true);
        },

        // Refresh alles: herlaad data van server + check voor app update
        async refreshAll() {
            this.isRefreshing = true;
            try {
                // 1. Herlaad wedstrijden data
                await this.laadWedstrijden();

                // 2. Check voor nieuwe versie (force reload van cache)
                if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                    navigator.serviceWorker.controller.postMessage({ type: 'SKIP_WAITING' });
                }

                // Kort wachten zodat gebruiker feedback ziet
                await new Promise(r => setTimeout(r, 300));
            } finally {
                this.isRefreshing = false;
            }
        },

        async laadWedstrijden() {
            if (this._isLoadingWedstrijden) {
                // Er loopt al een load - markeer dat we opnieuw moeten laden
                this._pendingReload = true;
                return;
            }
            if (!this.blokId || !this.matId) {
                this.poules = [];
                return;
            }
            this._isLoadingWedstrijden = true;
            this._pendingReload = false;

            // Sla selectie op voor volgende keer
            this.opslaanSelectie();

            try {
                const apiUrl = `{{ $wedstrijdenUrl }}`;
                console.log('[Mat] API URL:', apiUrl);
                console.log('[Mat] Loading wedstrijden for blok:', this.blokId, 'mat:', this.matId);
                const response = await fetch(apiUrl, {
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

                console.log('[Mat] Response status:', response.status);

                if (!response.ok) {
                    let errorMsg = 'Fout bij laden wedstrijden: ' + response.status;
                    try {
                        const errorData = await response.json();
                        errorMsg = errorData.error || errorMsg;
                        // If blok/mat no longer exists, clear selection
                        if (errorData.invalid_blok || errorData.invalid_mat) {
                            console.log('[Mat] Invalid blok/mat, clearing selection');
                            this.blokId = '';
                            this.matId = '';
                            localStorage.removeItem(storageKey);
                        }
                    } catch (e) {
                        console.error('[Mat] Error response:', await response.text());
                    }
                    alert(errorMsg);
                    this.poules = [];
                    return;
                }

                const data = await response.json();
                // New API format: {mat: {...}, poules: [...]}
                this.matSelectie = data.mat || null;
                const pouleData = data.poules || data;  // Fallback for old API format
                console.log('[Mat] Loaded', pouleData.length, 'poules, mat selectie:', this.matSelectie);

            // Swap ruimte wordt NIET meer automatisch gecleared
            // Admin kan judoka's in swap houden ook na lock

            // Initialize scores from existing data
            this.poules = pouleData.map(poule => {
                poule.wedstrijden = poule.wedstrijden.map(w => {
                    w.wpScores = {};
                    w.jpScores = {};

                    // Initialize from saved scores (only for poule matches with both judokas)
                    if (w.wit && w.blauw && (w.is_gespeeld || w.score_wit || w.score_blauw)) {
                        // Parse WP from winner
                        if (w.winnaar_id === w.wit.id) {
                            w.wpScores[w.wit.id] = 2;
                            w.wpScores[w.blauw.id] = 0;
                        } else if (w.winnaar_id === w.blauw.id) {
                            w.wpScores[w.wit.id] = 0;
                            w.wpScores[w.blauw.id] = 2;
                        } else if (w.is_gespeeld && !w.winnaar_id) {
                            // Gelijkspel: beide krijgen 1 WP
                            w.wpScores[w.wit.id] = 1;
                            w.wpScores[w.blauw.id] = 1;
                        }

                        // Parse JP from scores
                        if (w.score_wit) w.jpScores[w.wit.id] = parseInt(w.score_wit) || 0;
                        if (w.score_blauw) w.jpScores[w.blauw.id] = parseInt(w.score_blauw) || 0;
                    }

                    return w;
                });
                return poule;
            });
            // Initialize SortableJS on bracket after render (all devices)
            // + beurtaanduiding kleuren toepassen op bestaande bracket DOM
            this.$nextTick(() => {
                window.initBracketSortable?.();
                this.applyBeurtaanduiding();
            });
            } catch (err) {
                console.error('[Mat] Exception loading wedstrijden:', err);
                alert('Fout bij laden: ' + err.message);
                this.poules = [];
            } finally {
                this._isLoadingWedstrijden = false;
                // Als er tijdens het laden een nieuwe request binnenkwam, herlaad nu
                if (this._pendingReload) {
                    this._pendingReload = false;
                    this.laadWedstrijden();
                }
            }
        },

        // Laad bracket HTML via AJAX endpoint (server-rendered Blade partial)
        async laadBracketHtml(pouleId, groep) {
            const container = document.getElementById('bracket-container-' + pouleId + '-' + groep);
            if (!container) return;

            try {
                const response = await fetch(`{{ $bracketHtmlUrl }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ poule_id: pouleId, groep: groep, debug_slots: this.debugSlots })
                });

                if (!response.ok) {
                    console.error('[Bracket] Fout bij laden HTML:', response.status);
                    container.innerHTML = '<div class="text-red-500 text-sm py-2">Fout bij laden bracket</div>';
                    return;
                }

                const html = await response.text();
                container.innerHTML = html;

                // Initialiseer SortableJS op de nieuwe DOM elementen
                this.$nextTick(() => window.initBracketSortable?.());

                // Pas beurtaanduiding kleuren toe
                this.applyBeurtaanduiding();
            } catch (err) {
                console.error('[Bracket] Exception bij laden:', err);
                container.innerHTML = '<div class="text-red-500 text-sm py-2">Fout bij laden bracket</div>';
            }
        },

        // Update een individueel bracket slot na succesvolle drop (pure DOM update)
        updateBracketSlot(wedstrijdId, positie, judoka, isWinnaar, extraDragData = {}) {
            const slotEl = document.getElementById('slot-' + wedstrijdId + '-' + positie);
            if (!slotEl) return;

            // Update bewoner data attribute
            slotEl.dataset.bewoner = judoka ? JSON.stringify({id: judoka.id, naam: judoka.naam}) : 'null';

            if (judoka) {
                const dragData = JSON.stringify({
                    judokaId: judoka.id,
                    wedstrijdId: wedstrijdId,
                    judokaNaam: judoka.naam,
                    volgendeWedstrijdId: extraDragData.volgendeWedstrijdId || null,
                    winnaarNaarSlot: extraDragData.winnaarNaarSlot || null,
                    pouleIsLocked: extraDragData.pouleIsLocked || false,
                    pouleId: extraDragData.pouleId || null,
                    positie: positie,
                    isWinnaar: !!isWinnaar,
                    isGespeeld: extraDragData.isGespeeld || false,
                });
                const escapedDrag = dragData.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
                slotEl.innerHTML = `<div class="w-full h-full px-1 flex items-center cursor-pointer hover:bg-green-50 bracket-judoka" data-drag="${escapedDrag}"><span class="truncate">${window.escapeHtml(judoka.naam)}</span>${isWinnaar ? '<span class="inline-block w-2 h-2 bg-green-500 rounded-full ml-1 flex-shrink-0" title="Winnaar"></span>' : ''}</div>`;
            } else {
                slotEl.innerHTML = '';
            }
        },

        // Update ALLE bracket slots vanuit server response (updated_slots)
        updateAlleBracketSlots(updatedSlots, pouleId) {
            if (!updatedSlots || !Array.isArray(updatedSlots)) return;

            updatedSlots.forEach(slot => {
                this.updateBracketSlot(
                    slot.wedstrijd_id,
                    slot.positie,
                    slot.judoka,
                    slot.is_winnaar,
                    {
                        pouleId: pouleId,
                        isGespeeld: slot.is_gespeeld,
                        volgendeWedstrijdId: slot.volgende_wedstrijd_id || null,
                        winnaarNaarSlot: slot.winnaar_naar_slot || null,
                        pouleIsLocked: slot.poule_is_locked || false,
                    }
                );
            });

            // Herinitialiseer SortableJS na DOM updates
            this.$nextTick(() => window.initBracketSortable?.());
        },

        // Pas beurtaanduiding kleuren toe op bracket potjes (groen/geel/blauw)
        applyBeurtaanduiding() {
            if (!this.matSelectie) return;

            const actieveId = this.matSelectie.actieve_wedstrijd_id;
            const volgendeId = this.matSelectie.volgende_wedstrijd_id;
            const gereedmakenId = this.matSelectie.gereedmaken_wedstrijd_id;

            // Reset alle beurtaanduiding
            document.querySelectorAll('.bracket-potje .drop-slot').forEach(el => {
                el.style.border = '';
                el.style.backgroundColor = '';
            });

            // Actief (groen)
            if (actieveId) {
                const witSlot = document.getElementById('slot-' + actieveId + '-wit');
                const blauwSlot = document.getElementById('slot-' + actieveId + '-blauw');
                if (witSlot) { witSlot.style.border = '2px solid #16a34a'; witSlot.style.backgroundColor = '#dcfce7'; }
                if (blauwSlot) { blauwSlot.style.border = '2px solid #16a34a'; blauwSlot.style.backgroundColor = '#dcfce7'; }
            }

            // Volgende (geel)
            if (volgendeId) {
                const witSlot = document.getElementById('slot-' + volgendeId + '-wit');
                const blauwSlot = document.getElementById('slot-' + volgendeId + '-blauw');
                if (witSlot) { witSlot.style.border = '2px solid #ca8a04'; witSlot.style.backgroundColor = '#fef9c3'; }
                if (blauwSlot) { blauwSlot.style.border = '2px solid #ca8a04'; blauwSlot.style.backgroundColor = '#fef9c3'; }
            }

            // Gereedmaken (blauw)
            if (gereedmakenId) {
                const witSlot = document.getElementById('slot-' + gereedmakenId + '-wit');
                const blauwSlot = document.getElementById('slot-' + gereedmakenId + '-blauw');
                if (witSlot) { witSlot.style.border = '2px solid #2563eb'; witSlot.style.backgroundColor = '#dbeafe'; }
                if (blauwSlot) { blauwSlot.style.border = '2px solid #2563eb'; blauwSlot.style.backgroundColor = '#dbeafe'; }
            }
        },

        // Check of judoka in deze wedstrijd speelt (== voor type-onafhankelijk)
        speeltInWedstrijd(judokaId, wedstrijd) {
            return (wedstrijd.wit && wedstrijd.wit.id == judokaId) || (wedstrijd.blauw && wedstrijd.blauw.id == judokaId);
        },

        // Getters voor reactieve waarden
        getWP(wedstrijd, judokaId) {
            const val = wedstrijd.wpScores?.[judokaId];
            return val !== undefined ? val : '';
        },

        getJP(wedstrijd, judokaId) {
            const val = wedstrijd.jpScores?.[judokaId];
            return val !== undefined ? val : '';
        },

        getWpClass(wp) {
            wp = parseInt(wp);
            if (wp === 2) return 'bg-green-200 text-green-800';
            if (wp === 0) return 'bg-red-200 text-red-800';
            return 'bg-white';
        },

        getPlaatsClass(plaats) {
            if (plaats === 1) return 'bg-yellow-400 text-yellow-900';  // Goud
            if (plaats === 2) return 'bg-gray-300 text-gray-800';       // Zilver
            if (plaats === 3) return 'bg-orange-300 text-orange-900';   // Brons
            return 'bg-yellow-50';
        },

        // Check of punten invoer is toegestaan voor deze wedstrijd
        // Alleen toegestaan voor: groen (actief), of niet-gekleurde wedstrijden
        isInvoerToegestaan(wedstrijd) {
            const matActieveId = this.matSelectie?.actieve_wedstrijd_id;
            const matVolgendeId = this.matSelectie?.volgende_wedstrijd_id;
            const matGereedmakenId = this.matSelectie?.gereedmaken_wedstrijd_id;

            // Geel of blauw = niet toegestaan
            if (matVolgendeId && wedstrijd.id === matVolgendeId) return false;
            if (matGereedmakenId && wedstrijd.id === matGereedmakenId) return false;

            // Groen of niet-gekleurd = toegestaan
            return true;
        },

        updateWP(wedstrijd, judokaId, value) {
            if (!wedstrijd.wit || !wedstrijd.blauw) return;

            // Extra check (inputs zijn al disabled, maar voor zekerheid)
            if (!this.isInvoerToegestaan(wedstrijd)) return;

            // Leeg veld = verwijder waarde (voor delete/backspace)
            if (value === '' || value === null || value === undefined) {
                const newScores = { ...wedstrijd.wpScores };
                delete newScores[judokaId];
                wedstrijd.wpScores = newScores;
                return;
            }

            const wp = parseInt(value) || 0;

            // Alleen de ingevulde waarde zetten, GEEN auto-fill van tegenstander
            // Auto-fill gaat alleen via JP invoer
            wedstrijd.wpScores = { ...wedstrijd.wpScores, [judokaId]: wp };
        },

        updateJP(wedstrijd, judokaId, value) {
            if (!wedstrijd.wit || !wedstrijd.blauw) return;

            // Extra check (inputs zijn al disabled, maar voor zekerheid)
            if (!this.isInvoerToegestaan(wedstrijd)) return;

            const opponentId = wedstrijd.wit.id == judokaId ? wedstrijd.blauw.id : wedstrijd.wit.id;

            // Blanco = reset alles
            if (value === '' || value === null || value === undefined) {
                wedstrijd.wpScores = {};
                wedstrijd.jpScores = {};
                return;
            }

            const jp = parseInt(value);

            // Reassign objects voor Alpine reactivity
            wedstrijd.jpScores = { ...wedstrijd.jpScores, [judokaId]: jp };

            if (jp === 0) {
                // JP=0 betekent gelijkspel: beide WP=1, beide JP=0
                wedstrijd.wpScores = { ...wedstrijd.wpScores, [judokaId]: 1, [opponentId]: 1 };
                wedstrijd.jpScores = { ...wedstrijd.jpScores, [opponentId]: 0 };
            } else if (jp > 0) {
                // JP > 0: winnaar krijgt WP=2, verliezer WP=0 en JP=0
                wedstrijd.wpScores = { ...wedstrijd.wpScores, [judokaId]: 2, [opponentId]: 0 };
                wedstrijd.jpScores = { ...wedstrijd.jpScores, [opponentId]: 0 };
            }
        },

        async saveScore(wedstrijd, poule) {
            // Skip if no judokas (eliminatie TBD)
            if (!wedstrijd.wit || !wedstrijd.blauw) {
                return;
            }

            // Determine winner based on WP
            let winnaarId = null;
            if (wedstrijd.wpScores[wedstrijd.wit.id] === 2) {
                winnaarId = wedstrijd.wit.id;
            } else if (wedstrijd.wpScores[wedstrijd.blauw.id] === 2) {
                winnaarId = wedstrijd.blauw.id;
            }

            // Save to backend
            await fetch(`{{ $uitslagUrl }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    wedstrijd_id: wedstrijd.id,
                    winnaar_id: winnaarId,
                    score_wit: wedstrijd.jpScores[wedstrijd.wit.id] !== undefined ? String(wedstrijd.jpScores[wedstrijd.wit.id]) : '',
                    score_blauw: wedstrijd.jpScores[wedstrijd.blauw.id] !== undefined ? String(wedstrijd.jpScores[wedstrijd.blauw.id]) : '',
                    uitslag_type: 'punten'
                })
            });

            const wasGespeeld = wedstrijd.is_gespeeld;

            // BELANGRIJK: Check VOOR is_gespeeld update of dit de actieve (groene) wedstrijd was op MAT niveau
            const matActieveId = this.matSelectie?.actieve_wedstrijd_id;
            const matVolgendeId = this.matSelectie?.volgende_wedstrijd_id;
            const matGereedmakenId = this.matSelectie?.gereedmaken_wedstrijd_id;
            const wasActief = matActieveId === wedstrijd.id;

            wedstrijd.is_gespeeld = !!(winnaarId || (wedstrijd.jpScores[wedstrijd.wit.id] !== undefined && wedstrijd.jpScores[wedstrijd.blauw.id] !== undefined));
            wedstrijd.winnaar_id = winnaarId;

            // Auto-advance: als groene wedstrijd klaar → doorschuiven (geel→groen, blauw→geel)
            // Dit werkt nu op MAT niveau met 3 kleuren
            if (!wasGespeeld && wedstrijd.is_gespeeld && wasActief) {
                // Doorschuiven: geel→groen, blauw→geel, blauw=null
                await this.setWedstrijdStatus(matVolgendeId || null, matGereedmakenId || null, null);
            }
        },

        getTotaalWins(poule, judokaId) {
            let wins = 0;
            poule.wedstrijden.forEach(w => {
                if (this.speeltInWedstrijd(judokaId, w)) {
                    const wp = parseInt(w.wpScores?.[judokaId]) || 0;
                    if (wp === 2) wins++;
                }
            });
            return wins;
        },

        getTotaalWP(poule, judokaId) {
            let totaal = 0;
            poule.wedstrijden.forEach(w => {
                if (this.speeltInWedstrijd(judokaId, w)) {
                    totaal += parseInt(w.wpScores?.[judokaId]) || 0;
                }
            });
            return totaal;
        },

        getTotaalJP(poule, judokaId) {
            let totaal = 0;
            poule.wedstrijden.forEach(w => {
                if (this.speeltInWedstrijd(judokaId, w)) {
                    totaal += parseInt(w.jpScores?.[judokaId]) || 0;
                }
            });
            return totaal;
        },

        getPlaats(poule, judokaId) {
            const wedstrijden = poule.wedstrijden;

            // Helper: check of A heeft gewonnen van B
            const heeftGewonnenVan = (aId, bId) => {
                for (const w of wedstrijden) {
                    const isMatch = (w.wit?.id === aId && w.blauw?.id === bId)
                                 || (w.wit?.id === bId && w.blauw?.id === aId);
                    if (isMatch && w.winnaar_id === aId) return true;
                }
                return false;
            };

            // Bereken standings voor alle judoka's
            const allStandings = poule.judokas.map(j => ({
                id: j.id,
                wp: this.getTotaalWP(poule, j.id),
                jp: this.getTotaalJP(poule, j.id)
            }));

            // Check of gevraagde judoka afwezig is (0 WP én 0 JP)
            const judokaStanding = allStandings.find(s => s.id === judokaId);
            if (judokaStanding && judokaStanding.wp === 0 && judokaStanding.jp === 0) {
                return '-'; // Afwezige judoka krijgt geen plaats
            }

            // Filter afwezige judoka's uit (0 WP én 0 JP)
            const standings = allStandings.filter(s => s.wp > 0 || s.jp > 0);

            // Sorteer op WP (desc), dan JP (desc)
            standings.sort((a, b) => {
                if (b.wp !== a.wp) return b.wp - a.wp;
                if (b.jp !== a.jp) return b.jp - a.jp;
                return 0;
            });

            // Groepeer judoka's met gelijke WP+JP
            const groups = [];
            let currentGroup = [];
            for (let i = 0; i < standings.length; i++) {
                if (i === 0 || (standings[i].wp === standings[i-1].wp && standings[i].jp === standings[i-1].jp)) {
                    currentGroup.push(standings[i]);
                } else {
                    groups.push(currentGroup);
                    currentGroup = [standings[i]];
                }
            }
            if (currentGroup.length > 0) groups.push(currentGroup);

            // Bepaal posities per groep
            let plaats = 1;
            const posities = {}; // judokaId -> positie

            for (const group of groups) {
                if (group.length === 1) {
                    // Enige in groep = duidelijke positie
                    posities[group[0].id] = plaats;
                } else if (group.length === 2) {
                    // 2 judoka's: head-to-head bepaalt
                    const [a, b] = group;
                    if (heeftGewonnenVan(a.id, b.id)) {
                        posities[a.id] = plaats;
                        posities[b.id] = plaats + 1;
                    } else if (heeftGewonnenVan(b.id, a.id)) {
                        posities[b.id] = plaats;
                        posities[a.id] = plaats + 1;
                    } else {
                        // Geen wedstrijd of gelijk: gedeelde positie
                        posities[a.id] = plaats;
                        posities[b.id] = plaats;
                    }
                } else {
                    // 3+ judoka's: check of iemand van ALLE anderen in de groep heeft gewonnen
                    const sorted = [...group];

                    // Tel voor elke judoka hoeveel h2h wins BINNEN deze groep
                    for (const j of sorted) {
                        j.h2hWins = group.filter(other =>
                            other.id !== j.id && heeftGewonnenVan(j.id, other.id)
                        ).length;
                    }

                    // Check of er een duidelijke winnaar is (heeft van ALLE anderen in groep gewonnen)
                    const maxWins = group.length - 1;
                    const winnaar = sorted.find(j => j.h2hWins === maxWins);

                    if (winnaar) {
                        // Duidelijke winnaar binnen groep
                        posities[winnaar.id] = plaats;

                        // Rest van de groep: check recursief of gedeeld
                        const rest = sorted.filter(j => j.id !== winnaar.id);
                        if (rest.length === 1) {
                            posities[rest[0].id] = plaats + 1;
                        } else {
                            // Meerdere overblijvers: geef gedeelde positie
                            for (const r of rest) {
                                posities[r.id] = plaats + 1;
                            }
                        }
                    } else {
                        // Geen duidelijke winnaar (carrousel): iedereen gedeelde positie
                        // Dit betekent barrage nodig
                        for (const j of group) {
                            posities[j.id] = plaats;
                        }
                    }
                }
                plaats += group.length;
            }

            return posities[judokaId] || standings.length;
        },

        isPouleAfgerond(poule) {
            if (poule.wedstrijden.length === 0) return false;

            // Eliminatie: check finale + brons wedstrijden
            if (poule.type === 'eliminatie') {
                return this.isEliminatieAfgerond(poule);
            }

            // Poule: alle wedstrijden moeten gespeeld zijn
            // is_gespeeld = true (kan gelijkspel zijn, dan winnaar_id = null)
            return poule.wedstrijden.every(w => w.is_gespeeld);
        },

        // Check of eliminatie bracket volledig is afgerond
        // Afgerond = finale gespeeld (met winnaar) + alle brons wedstrijden gespeeld (met winnaar)
        // ROBUUST: gebruik winnaar_id check, niet alleen is_gespeeld
        isEliminatieAfgerond(poule) {
            // Split mat: only check the group visible on this mat
            if (poule.groep_filter === 'A') {
                const finale = poule.wedstrijden.find(w => w.groep === 'A' && w.ronde === 'finale');
                return finale && !!finale.winnaar_id;
            }
            if (poule.groep_filter === 'B') {
                const bWedstrijden = poule.wedstrijden.filter(w => w.groep === 'B');
                return bWedstrijden.length > 0 && bWedstrijden.every(w => w.is_gespeeld);
            }

            // No split: check all (finale + brons)
            const finale = poule.wedstrijden.find(w => w.groep === 'A' && w.ronde === 'finale');
            if (!finale || !finale.winnaar_id) return false;

            // Check brons wedstrijden (b_halve_finale_2)
            const bronsWedstrijden = poule.wedstrijden.filter(w =>
                w.ronde === 'b_halve_finale_2' || w.ronde === 'b_brons' || w.ronde === 'b_finale'
            );

            // Als er brons wedstrijden zijn, moeten die ook winnaar hebben
            if (bronsWedstrijden.length > 0) {
                return bronsWedstrijden.every(w => w.winnaar_id);
            }

            // Geen brons wedstrijden (kleine bracket) = alleen finale nodig
            return true;
        },

        async markeerKlaar(poule) {
            try {
                const response = await fetch(`{{ $pouleKlaarUrl }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        poule_id: poule.poule_id
                    })
                });

                const data = await response.json();
                if (data.success) {
                    poule.spreker_klaar = true;
                    // Get current time
                    const now = new Date();
                    poule.spreker_klaar_tijd = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                }
            } catch (err) {
                alert('Fout bij markeren: ' + err.message);
            }
        },

        // Detecteer of er een barrage nodig is (3+ judoka's met gelijke WP+JP die cirkel vormen)
        heeftBarrageNodig(poule) {
            if (poule.type === 'eliminatie' || poule.type === 'barrage') return false;
            if (poule.is_punten_competitie) return false;
            if (!this.isPouleAfgerond(poule)) return false;

            const barrageJudokas = this.getBarrageJudokas(poule);
            return barrageJudokas.length >= 3;
        },

        // Vind judoka's die barrage moeten spelen (gelijke WP+JP met cirkel-verliezen)
        getBarrageJudokas(poule) {
            const allStandings = poule.judokas.map(j => ({
                id: j.id,
                naam: j.naam,
                wp: this.getTotaalWP(poule, j.id),
                jp: this.getTotaalJP(poule, j.id)
            }));

            // Filter afwezige judoka's (0 WP én 0 JP)
            const standings = allStandings.filter(s => s.wp > 0 || s.jp > 0);

            // Groepeer op WP+JP
            const groups = {};
            standings.forEach(s => {
                const key = `${s.wp}-${s.jp}`;
                if (!groups[key]) groups[key] = [];
                groups[key].push(s);
            });

            // Zoek groepen met 3+ judoka's
            for (const key in groups) {
                const group = groups[key];
                if (group.length >= 3) {
                    // Check of ze een cirkel vormen (ieder 1x gewonnen van 1 ander in groep)
                    if (this.isCircleResult(poule, group)) {
                        return group;
                    }
                }
            }

            return [];
        },

        // Check of judoka's een cirkel vormen (A->B, B->C, C->A)
        isCircleResult(poule, judokas) {
            const ids = judokas.map(j => j.id);
            const wins = {}; // wins[a] = [b, c] betekent a heeft gewonnen van b en c

            // Tel wins binnen de groep
            ids.forEach(id => wins[id] = []);

            for (const w of poule.wedstrijden) {
                if (!w.is_gespeeld || !w.winnaar_id) continue;

                const witId = w.wit?.id;
                const blauwId = w.blauw?.id;

                if (ids.includes(witId) && ids.includes(blauwId)) {
                    const winnerId = w.winnaar_id;
                    const loserId = winnerId === witId ? blauwId : witId;
                    if (wins[winnerId]) wins[winnerId].push(loserId);
                }
            }

            // Cirkel = ieder heeft precies 1 win EN 1 loss binnen de groep
            // (bij 3 judoka's: ieder 1 win, 1 loss)
            const n = ids.length;
            const expectedWins = n >= 3 ? 1 : 0; // Bij 3 judoka's: 1 win elk

            // Meer algemeen: check of niemand duidelijk wint (geen judoka heeft van ALLE anderen gewonnen)
            for (const id of ids) {
                if (wins[id].length >= n - 1) {
                    // Deze judoka heeft van alle anderen gewonnen, geen barrage nodig
                    return false;
                }
            }

            // Niemand heeft van iedereen gewonnen = cirkel/gelijkspel
            return true;
        },

        async maakBarrage(poule) {
            const barrageJudokas = this.getBarrageJudokas(poule);
            if (barrageJudokas.length < 3) {
                alert('Geen barrage nodig');
                return;
            }

            const namen = barrageJudokas.map(j => j.naam).join(', ');
            if (!confirm(`Barrage maken voor: ${namen}?`)) return;

            try {
                const response = await fetch(`{{ route('toernooi.mat.barrage', $toernooi->routeParams()) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        poule_id: poule.poule_id,
                        judoka_ids: barrageJudokas.map(j => j.id)
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert(`Barrage poule #${data.barrage_poule.nummer} aangemaakt op dezelfde mat!`);
                    // Herlaad wedstrijden om barrage te tonen
                    this.laadWedstrijden();
                } else {
                    alert('Fout: ' + (data.error || 'Onbekende fout'));
                }
            } catch (err) {
                alert('Fout bij maken barrage: ' + err.message);
            }
        },

        // Bepaal huidige (groen), volgende (geel) en gereedmaken (blauw) wedstrijd - NU OP MAT NIVEAU
        // Groen = mat.actieve_wedstrijd_id (1 per mat, ongeacht poules) - Speelt nu
        // Geel = mat.volgende_wedstrijd_id (1 per mat, ongeacht poules) - Staat klaar
        // Blauw = mat.gereedmaken_wedstrijd_id (1 per mat, ongeacht poules) - Gereed maken
        // Helper: check of wedstrijd nog te spelen is (geen winnaar)
        // ROBUUST: alleen wedstrijden MET winnaar zijn echt gespeeld
        // Consistent met PHP Wedstrijd::isNogTeSpelen()
        isNogTeSpelen(w) {
            // Wedstrijd is nog te spelen als:
            // - niet gespeeld (!is_gespeeld), OF
            // - gespeeld maar geen winnaar (lopende wedstrijd)
            return !w.is_gespeeld || !w.winnaar_id;
        },

        // Helper: check of wedstrijd ECHT gespeeld is (met winnaar)
        // Consistent met PHP Wedstrijd::isEchtGespeeld()
        isEchtGespeeld(w) {
            return w.is_gespeeld && w.winnaar_id;
        },

        getHuidigeEnVolgende(poule) {
            const wedstrijden = poule.wedstrijden;
            if (!wedstrijden || wedstrijden.length === 0) return { huidige: null, volgende: null, gereedmaken: null };

            // MAT-niveau: haal actieve/volgende/gereedmaken van matSelectie
            const matActieveId = this.matSelectie?.actieve_wedstrijd_id;
            const matVolgendeId = this.matSelectie?.volgende_wedstrijd_id;
            const matGereedmakenId = this.matSelectie?.gereedmaken_wedstrijd_id;

            // ROBUUST: gebruik isNogTeSpelen (check op winnaar_id, niet is_gespeeld)
            // Huidige (groen) = als mat's actieve wedstrijd in DEZE poule zit EN nog geen winnaar heeft
            let huidige = null;
            if (matActieveId) {
                huidige = wedstrijden.find(w => w.id === matActieveId && this.isNogTeSpelen(w));
            }

            // Volgende (geel) = als mat's volgende wedstrijd in DEZE poule zit EN nog geen winnaar heeft
            let volgende = null;
            if (matVolgendeId) {
                volgende = wedstrijden.find(w => w.id === matVolgendeId && this.isNogTeSpelen(w));
            }

            // Gereedmaken (blauw) = als mat's gereedmaken wedstrijd in DEZE poule zit EN nog geen winnaar heeft
            let gereedmaken = null;
            if (matGereedmakenId) {
                gereedmaken = wedstrijden.find(w => w.id === matGereedmakenId && this.isNogTeSpelen(w));
            }

            return { huidige, volgende, gereedmaken };
        },

        // CSS class voor wedstrijd header - DIRECT check op matSelectie IDs
        // Geen omweg via getHuidigeEnVolgende voor betere reactivity
        getWedstrijdKleurClass(poule, wedstrijd, idx) {
            // Check of wedstrijd echt gespeeld is (met winnaar)
            if (wedstrijd.is_gespeeld && wedstrijd.winnaar_id) {
                return 'bg-gray-300 text-gray-600'; // Gespeeld
            }

            // Direct check op matSelectie IDs (geen find() nodig)
            const matActieveId = this.matSelectie?.actieve_wedstrijd_id;
            const matVolgendeId = this.matSelectie?.volgende_wedstrijd_id;
            const matGereedmakenId = this.matSelectie?.gereedmaken_wedstrijd_id;

            if (matActieveId && wedstrijd.id === matActieveId) {
                return 'bg-green-500 text-white cursor-pointer'; // Speelt nu (groen)
            }
            if (matVolgendeId && wedstrijd.id === matVolgendeId) {
                return 'bg-yellow-400 text-yellow-900 cursor-pointer'; // Staat klaar (geel)
            }
            if (matGereedmakenId && wedstrijd.id === matGereedmakenId) {
                return 'bg-blue-400 text-white cursor-pointer'; // Gereed maken (blauw)
            }

            // Gespeeld maar zonder winnaar (incompleet) - grijs maar klikbaar
            if (wedstrijd.is_gespeeld) {
                return 'bg-gray-200 text-gray-500 cursor-pointer';
            }

            return 'bg-gray-200 text-gray-700 cursor-pointer'; // Nog niet aan de beurt
        },

        // Tooltip voor wedstrijd header
        getWedstrijdTitel(poule, wedstrijd, idx) {
            const { huidige, volgende, gereedmaken } = this.getHuidigeEnVolgende(poule);

            if (wedstrijd.is_gespeeld) return 'Gespeeld';
            if (huidige && wedstrijd.id === huidige.id) {
                return 'Speelt nu - klik om te stoppen (geel→groen, blauw→geel)';
            }
            if (volgende && wedstrijd.id === volgende.id) {
                return 'Staat klaar - klik om te deselecteren (blauw→geel)';
            }
            if (gereedmaken && wedstrijd.id === gereedmaken.id) {
                return 'Gereed maken - klik om te deselecteren';
            }
            return 'Klik om te selecteren';
        },

        // Toggle wedstrijd selectie (groen/geel/blauw systeem) - MAT NIVEAU
        // Documentatie: MAT-WEDSTRIJD-SELECTIE.md
        // - 1 groen, 1 geel en 1 blauw per mat (ongeacht aantal poules)
        // - Groen = speelt nu, Geel = staat klaar, Blauw = gereed maken
        async toggleVolgendeWedstrijd(poule, wedstrijd) {
            // === VALIDATIE ===
            if (!wedstrijd || !wedstrijd.id) {
                console.error('[Mat] Ongeldige wedstrijd:', wedstrijd);
                return;
            }

            if (!this.matId) {
                console.error('[Mat] Geen mat geselecteerd');
                alert('Selecteer eerst een mat.');
                return;
            }

            // Niet toestaan voor echt gespeelde wedstrijden (met winnaar)
            if (wedstrijd.is_gespeeld && wedstrijd.winnaar_id) {
                console.log('[Mat] Wedstrijd', wedstrijd.id, 'heeft al een winnaar, skip');
                return;
            }

            // === HUIDIGE STATUS OPHALEN ===
            // Initialiseer matSelectie als het null is
            if (!this.matSelectie) {
                console.warn('[Mat] matSelectie is null, initialiseren...');
                this.matSelectie = {
                    actieve_wedstrijd_id: null,
                    volgende_wedstrijd_id: null,
                    gereedmaken_wedstrijd_id: null
                };
            }

            const matActieveId = this.matSelectie.actieve_wedstrijd_id || null;
            const matVolgendeId = this.matSelectie.volgende_wedstrijd_id || null;
            const matGereedmakenId = this.matSelectie.gereedmaken_wedstrijd_id || null;

            console.log('[Mat] Toggle wedstrijd', wedstrijd.id, '- Huidige status:', {
                groen: matActieveId,
                geel: matVolgendeId,
                blauw: matGereedmakenId
            });

            // === BEPAAL OF WEDSTRIJD AL GESELECTEERD IS ===
            const isGroen = wedstrijd.id === matActieveId;
            const isGeel = wedstrijd.id === matVolgendeId;
            const isBlauw = wedstrijd.id === matGereedmakenId;

            // === ACTIE BEPALEN ===
            let nieuweGroen = matActieveId;
            let nieuweGeel = matVolgendeId;
            let nieuweBlauw = matGereedmakenId;

            if (isGroen) {
                // DESELECTEER GROEN - vraag bevestiging, dan doorschuiven
                if (!confirm('Groene wedstrijd stoppen?\n\nGeel → Groen, Blauw → Geel')) {
                    return;
                }
                nieuweGroen = matVolgendeId;  // geel → groen
                nieuweGeel = matGereedmakenId; // blauw → geel
                nieuweBlauw = null;            // blauw = null
                console.log('[Mat] Groen gedeselecteerd, doorschuiven');
            }
            else if (isGeel) {
                // DESELECTEER GEEL - blauw schuift door
                nieuweGeel = matGereedmakenId; // blauw → geel
                nieuweBlauw = null;             // blauw = null
                console.log('[Mat] Geel gedeselecteerd, blauw → geel');
            }
            else if (isBlauw) {
                // DESELECTEER BLAUW - geen doorschuiving
                nieuweBlauw = null;
                console.log('[Mat] Blauw gedeselecteerd');
            }
            else {
                // NIEUWE SELECTIE - wedstrijd is nog niet geselecteerd
                if (!matActieveId) {
                    // Geen groen → wordt groen
                    nieuweGroen = wedstrijd.id;
                    console.log('[Mat] Wedstrijd', wedstrijd.id, '→ GROEN');
                }
                else if (!matVolgendeId) {
                    // Wel groen, geen geel → wordt geel
                    nieuweGeel = wedstrijd.id;
                    console.log('[Mat] Wedstrijd', wedstrijd.id, '→ GEEL');
                }
                else if (!matGereedmakenId) {
                    // Wel groen + geel, geen blauw → wordt blauw
                    nieuweBlauw = wedstrijd.id;
                    console.log('[Mat] Wedstrijd', wedstrijd.id, '→ BLAUW');
                }
                else {
                    // Alle slots bezet
                    alert('Alle slots zijn bezet (groen + geel + blauw).\n\nKlik op een gekleurde wedstrijd om te deselecteren.');
                    return;
                }
            }

            // === OPSLAAN ===
            console.log('[Mat] Nieuwe status:', { groen: nieuweGroen, geel: nieuweGeel, blauw: nieuweBlauw });
            await this.setWedstrijdStatus(nieuweGroen, nieuweGeel, nieuweBlauw);
        },

        // Helper: update actieve, volgende en gereedmaken wedstrijd op MAT niveau
        // Returns true bij succes, false bij fout
        async setWedstrijdStatus(actieveId, volgendeId, gereedmakenId) {
            // === VALIDATIE ===
            if (!this.matId) {
                console.error('[Mat] setWedstrijdStatus: geen matId!');
                alert('Geen mat geselecteerd. Refresh de pagina.');
                return false;
            }

            console.log('[Mat] setWedstrijdStatus:', {
                mat_id: this.matId,
                groen: actieveId,
                geel: volgendeId,
                blauw: gereedmakenId
            });

            // === OPTIMISTIC UPDATE - direct lokaal bijwerken voor snelle UI ===
            const oudeSelectie = { ...this.matSelectie };
            this.matSelectie = {
                actieve_wedstrijd_id: actieveId || null,
                volgende_wedstrijd_id: volgendeId || null,
                gereedmaken_wedstrijd_id: gereedmakenId || null
            };
            // Force re-render VOOR server call (snelle feedback)
            this.poules = [...this.poules];
            // Beurtaanduiding kleuren direct toepassen op bracket DOM
            this.$nextTick(() => this.applyBeurtaanduiding());

            try {
                const url = `{{ $huidigeWedstrijdUrl }}`;
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

                if (!csrfToken) {
                    throw new Error('CSRF token niet gevonden. Refresh de pagina.');
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        mat_id: this.matId,
                        actieve_wedstrijd_id: actieveId || null,
                        volgende_wedstrijd_id: volgendeId || null,
                        gereedmaken_wedstrijd_id: gereedmakenId || null
                    })
                });

                if (!response.ok) {
                    let errorMsg = 'Server fout';
                    try {
                        const errorData = await response.json();
                        errorMsg = errorData.error || errorData.message || errorMsg;
                    } catch (e) {}
                    throw new Error(errorMsg);
                }

                const data = await response.json();
                if (data.success && data.mat) {
                    // Server bevestigd - sync met server response
                    console.log('[Mat] Server bevestigd:', data.mat);
                    this.matSelectie = {
                        actieve_wedstrijd_id: data.mat.actieve_wedstrijd_id || null,
                        volgende_wedstrijd_id: data.mat.volgende_wedstrijd_id || null,
                        gereedmaken_wedstrijd_id: data.mat.gereedmaken_wedstrijd_id || null
                    };
                    // Nogmaals re-render met server data + beurtaanduiding
                    this.poules = [...this.poules];
                    this.$nextTick(() => this.applyBeurtaanduiding());
                    return true;
                } else {
                    throw new Error(data.error || 'Onbekende server response');
                }
            } catch (err) {
                // === ROLLBACK bij fout ===
                console.error('[Mat] setWedstrijdStatus FOUT:', err.message);
                this.matSelectie = oudeSelectie;
                this.poules = [...this.poules];
                alert('Fout bij opslaan selectie:\n' + err.message);
                return false;
            }
        },



        // Check of poule herkansing (groep B) heeft
        heeftHerkansing(poule) {
            return poule.wedstrijden.some(w => w.groep === 'B');
        },

    }
}

// Clock
function updateClock() {
    const clockEl = document.getElementById('clock');
    if (!clockEl) return;
    const now = new Date();
    clockEl.textContent = now.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
}
updateClock();
setInterval(updateClock, 1000);

// Reverb push events → refresh bracket/poule data (vervangt 30sec polling)
['mat-score-update', 'mat-beurt-update', 'mat-poule-klaar'].forEach(evt => {
    window.addEventListener(evt, () => {
        const el = document.getElementById('mat-interface');
        if (el) Alpine.evaluate(el, 'laadWedstrijden()');
    });
});

// Bracket update via Reverb: herlaad bracket HTML (niet hele poule data)
window.addEventListener('mat-bracket-update', (e) => {
    const el = document.getElementById('mat-interface');
    if (!el) return;
    const comp = Alpine.$data(el);
    if (!comp) return;

    // Herlaad bracket HTML voor de gewijzigde poule
    const pouleId = e.detail?.poule_id;
    if (pouleId) {
        comp.laadBracketHtml(pouleId, 'A');
        comp.laadBracketHtml(pouleId, 'B');
    } else {
        // Fallback: herlaad alle brackets
        comp.poules.forEach(p => {
            if (p.type === 'eliminatie') {
                comp.laadBracketHtml(p.poule_id, 'A');
                if (comp.heeftHerkansing(p)) comp.laadBracketHtml(p.poule_id, 'B');
            }
        });
    }
});

// ========================================
// SortableJS voor ALLE devices (PC + tablet) - bracket DnD
// DOM revert ALTIJD - updates komen via updateBracketSlot() na API response
// ========================================
window.initBracketSortable = function() {
    if (typeof Sortable === 'undefined') return;

    const matEl = document.getElementById('mat-interface');
    if (!matEl) return;

    // Destroy previous instances
    matEl.querySelectorAll('[data-sortable-bracket]').forEach(el => {
        if (el._sortable) el._sortable.destroy();
        el.removeAttribute('data-sortable-bracket');
    });

    const containers = matEl.querySelectorAll('.bracket-drop');

    containers.forEach(container => {
        container.setAttribute('data-sortable-bracket', '1');
        const pouleId = container.getAttribute('data-poule-id');
        if (!pouleId) return;

        container._sortable = new Sortable(container, {
            group: {
                name: 'bracket-' + pouleId,
                pull: 'clone',
                put: true
            },
            sort: false,
            animation: 0,
            delay: 150,
            delayOnTouchOnly: true,
            draggable: '.bracket-judoka',
            ghostClass: 'opacity-50',

            onEnd: async function(evt) {
                // ALTIJD DOM reverten - updates komen via updateBracketSlot
                if (evt.clone) evt.clone.remove();
                if (evt.from !== evt.to && evt.item.parentNode === evt.to) {
                    evt.from.appendChild(evt.item);
                }

                const dragAttr = evt.item.getAttribute('data-drag');
                if (!dragAttr || evt.from === evt.to) return;

                const target = evt.to;
                const handler = target.getAttribute('data-drop-handler');
                if (!handler) return;

                // Bouw fakeEvent voor bestaande handlers
                const fakeEvent = {
                    preventDefault() {},
                    dataTransfer: { getData() { return dragAttr; } }
                };

                try {
                    if (handler === 'dropJudoka') {
                        const wId = parseInt(target.getAttribute('data-wedstrijd-id'), 10);
                        const pos = target.getAttribute('data-positie');
                        const pId = parseInt(target.getAttribute('data-poule-id'), 10);
                        let bew = null;
                        try { const b = target.getAttribute('data-bewoner'); if (b && b !== 'null') bew = JSON.parse(b); } catch(e) {}
                        await window.dropJudoka(fakeEvent, wId, pos, pId, bew);
                    } else if (handler === 'dropInSwap') {
                        await window.dropInSwap(fakeEvent, parseInt(target.getAttribute('data-poule-id'), 10), false);
                    } else if (handler === 'dropOpMedaille') {
                        const fId = target.getAttribute('data-finale-id');
                        await window.dropOpMedaille(fakeEvent, fId === 'null' ? null : parseInt(fId, 10), target.getAttribute('data-medaille'), parseInt(target.getAttribute('data-poule-id'), 10));
                    } else if (handler === 'verwijderJudoka') {
                        await window.verwijderJudoka(fakeEvent);
                    }
                } catch(err) {
                    console.error('[DnD] Drop error:', err);
                }
            }
        });
    });
};
</script>
