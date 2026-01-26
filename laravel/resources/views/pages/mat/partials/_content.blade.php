    @php $pwaApp = 'mat'; @endphp
<div x-data="matInterface()" x-init="init()">
    <!-- Huidige selectie -->
    <div class="flex items-center justify-end mb-1" x-show="blokId && matId">
        <div class="text-sm text-gray-600">
            <span class="font-bold">Blok <span x-text="blokkenData.find(b => b.id == blokId)?.nummer"></span></span>
            &bull;
            <span class="font-bold">Mat <span x-text="mattenData.find(m => m.id == matId)?.nummer"></span></span>
            <a href="#blok-mat-keuze" class="ml-2 text-purple-600 hover:underline">(wijzig)</a>
        </div>
    </div>

    <template x-for="poule in poules" :key="poule.poule_id">
        <div class="bg-white rounded-lg shadow mb-3 overflow-hidden">
            <!-- Header -->
            <div :class="poule.type === 'eliminatie' ? 'bg-purple-700' : 'bg-green-700'" class="text-white px-3 py-1.5 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-bold">
                        <span x-text="(poule.type === 'eliminatie' ? 'Eliminatie' : 'Poule ' + poule.poule_nummer) + ' - ' + poule.leeftijdsklasse + ' ' + poule.gewichtsklasse + ' | Blok ' + poule.blok_nummer + ' - Mat ' + poule.mat_nummer"></span>
                        <span x-show="poule.type === 'eliminatie'">(<span x-text="poule.judoka_count"></span> judoka's)</span>
                    </h2>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Geen wedstrijden: toon waarschuwing -->
                    <div x-show="poule.wedstrijden.length === 0" class="bg-red-500 text-white px-3 py-1 rounded text-sm font-medium">
                        ⚠ Geen wedstrijden
                    </div>

                    <!-- Afgerond: toon klaar tijdstip -->
                    <div x-show="poule.spreker_klaar" class="bg-white px-3 py-1 rounded text-sm font-bold" :class="poule.type === 'eliminatie' ? 'text-purple-700' : 'text-green-700'">
                        ✓ Klaar om: <span x-text="poule.spreker_klaar_tijd"></span>
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
                <div class="p-1" x-data="{ activeTab: 'A' }">
                    <!-- Tabs + Swap Ruimte -->
                    <div class="flex mb-1 border-b border-gray-200 justify-between">
                        <div class="flex">
                            <button @click="activeTab = 'A'"
                                    :class="activeTab === 'A' ? 'border-purple-600 text-purple-700 bg-purple-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="px-4 py-1 text-xs font-bold border-b-2 transition-colors">
                                Groep A (Hoofdboom) <span x-text="'(' + poule.judoka_count + ')'"></span>
                            </button>
                            <template x-if="heeftHerkansing(poule)">
                                <button @click="activeTab = 'B'"
                                        :class="activeTab === 'B' ? 'border-purple-600 text-purple-700 bg-purple-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="px-4 py-1 text-xs font-bold border-b-2 transition-colors">
                                    Groep B (Herkansing) <span x-text="'(' + (poule.judoka_count - 2) + ')'"></span>
                                </button>
                            </template>
                        </div>
                        <!-- Swap Ruimte - alleen zichtbaar VOOR eerste wedstrijd (seeding fase) -->
                        <div x-show="!isBracketLocked(poule)" class="flex items-center gap-2 px-2">
                            <span class="text-sm font-medium text-gray-600">
                                Swap:
                            </span>
                            <div class="flex flex-wrap gap-1 min-w-[200px] max-w-[400px] min-h-[32px] border-2 border-dashed rounded-lg px-2 py-1 bg-orange-50 border-orange-400"
                                 :id="'swap-ruimte-' + poule.poule_id"
                                 ondragover="event.preventDefault(); this.classList.add('bg-orange-200', 'border-orange-600')"
                                 ondragleave="this.classList.remove('bg-orange-200', 'border-orange-600')"
                                 :ondrop="'this.classList.remove(\'bg-orange-200\', \'border-orange-600\'); window.dropInSwap(event, ' + poule.poule_id + ', false)'">
                                <template x-for="judoka in getSwapJudokas(poule.poule_id)" :key="judoka.id">
                                    <div class="bg-orange-500 text-white text-sm px-2 py-1 rounded cursor-move shadow-sm hover:bg-orange-600"
                                         draggable="true"
                                         :title="judoka.naam"
                                         :ondragstart="'window.startDragFromSwap(event, ' + JSON.stringify(judoka) + ', ' + poule.poule_id + ')'"
                                         x-text="judoka.naam">
                                    </div>
                                </template>
                                <span x-show="getSwapJudokas(poule.poule_id).length === 0" class="text-sm italic py-1 text-orange-400">Sleep judoka hierheen</span>
                            </div>
                        </div>
                    </div>

                    <!-- Groep A - Hoofdboom -->
                    <div x-show="activeTab === 'A'">
                        <div class="flex justify-between items-center">
                            <button @click="debugSlots = !debugSlots; $nextTick(() => poules = [...poules])"
                                    :class="debugSlots ? 'bg-yellow-200 text-yellow-800' : 'bg-gray-100 text-gray-600'"
                                    class="text-xs px-2 py-1 rounded hover:bg-yellow-300">
                                🔢 <span x-text="debugSlots ? 'Slots AAN' : 'Slots UIT'"></span>
                            </button>
                            <div class="text-sm text-gray-600 cursor-pointer hover:text-gray-800"
                                 ondragover="event.preventDefault(); this.classList.add('text-red-600','font-bold')"
                                 ondragleave="this.classList.remove('text-red-600','font-bold')"
                                 ondrop="this.classList.remove('text-red-600','font-bold'); window.verwijderJudoka(event)">
                                🗑️ verwijder
                            </div>
                        </div>
                        <div class="bracket-container overflow-x-auto pb-2" x-html="renderBracket(poule, 'A')"></div>
                    </div>

                    <!-- Groep B - Herkansing -->
                    <div x-show="activeTab === 'B'">
                        <div class="flex justify-between items-center">
                            <button @click="debugSlots = !debugSlots; $nextTick(() => poules = [...poules])"
                                    :class="debugSlots ? 'bg-yellow-200 text-yellow-800' : 'bg-gray-100 text-gray-600'"
                                    class="text-xs px-2 py-1 rounded hover:bg-yellow-300">
                                🔢 <span x-text="debugSlots ? 'Slots AAN' : 'Slots UIT'"></span>
                            </button>
                            <div class="text-sm text-gray-600 cursor-pointer hover:text-gray-800"
                                 ondragover="event.preventDefault(); this.classList.add('text-red-600','font-bold')"
                                 ondragleave="this.classList.remove('text-red-600','font-bold')"
                                 ondrop="this.classList.remove('text-red-600','font-bold'); window.verwijderJudoka(event)">
                                🗑️ verwijder
                            </div>
                        </div>
                        <div class="overflow-x-auto pb-2" x-html="renderBracket(poule, 'B')"></div>
                    </div>

                </div>
            </template>

            <!-- POULE WEERGAVE (Matrix Table) -->
            <template x-if="poule.type !== 'eliminatie'">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-gray-200 border-b-2 border-gray-400">
                                <th class="px-2 py-1 text-left font-bold text-gray-700 sticky left-0 bg-gray-200 min-w-[240px]">Naam</th>
                                <template x-for="(w, idx) in poule.wedstrijden" :key="'h-' + idx">
                                    <th class="px-0 py-1 text-center font-bold w-14 border-l border-gray-300 cursor-pointer select-none transition-colors"
                                        :class="getWedstrijdKleurClass(poule, w, idx)"
                                        @click="toggleVolgendeWedstrijd(poule, w)"
                                        :title="getWedstrijdTitel(poule, w, idx)"
                                        colspan="2">
                                        <div class="text-xs font-bold" x-text="(idx + 1)"></div>
                                    </th>
                                </template>
                                <th class="px-1 py-1 text-center font-bold text-gray-700 bg-blue-100 border-l-2 border-blue-300 w-10 text-xs">WP</th>
                                <th class="px-1 py-1 text-center font-bold text-gray-700 bg-blue-100 w-10 text-xs">JP</th>
                                <th class="px-1 py-1 text-center font-bold text-gray-700 bg-yellow-100 border-l-2 border-yellow-300 w-8 text-xs">#</th>
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
                                                    @input="updateWP(w, judoka.id, $event.target.value)"
                                                    @blur="saveScore(w, poule)"
                                                >
                                                <!-- JP met dropdown -->
                                                <select
                                                    class="w-7 text-center border border-gray-300 rounded-sm text-xs py-0.5 appearance-none bg-white"
                                                    :value="getJP(w, judoka.id)"
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

                                    <!-- Totaal WP -->
                                    <td class="px-0.5 py-0.5 text-center font-bold bg-blue-50 border-l-2 border-blue-300 text-blue-800 text-xs"
                                        x-text="getTotaalWP(poule, judoka.id)"></td>
                                    <!-- Totaal JP -->
                                    <td class="px-0.5 py-0.5 text-center font-bold bg-blue-50 text-blue-800 text-xs"
                                        x-text="getTotaalJP(poule, judoka.id)"></td>
                                    <!-- Plaats - alleen tonen na alle wedstrijden -->
                                    <td class="px-0.5 py-0.5 text-center font-bold border-l-2 border-yellow-300 text-xs"
                                        :class="isPouleAfgerond(poule) ? getPlaatsClass(getPlaats(poule, judoka.id)) : 'bg-yellow-50'"
                                        x-text="isPouleAfgerond(poule) ? getPlaats(poule, judoka.id) : '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </template>

    <div x-show="poules.length === 0 && blokId && matId" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        Geen poules op deze mat in dit blok
    </div>

    <!-- Blok/Mat keuze onderaan -->
    <div id="blok-mat-keuze" class="bg-gray-100 rounded-lg p-4 mt-8 border border-gray-300">
        <h3 class="text-sm font-bold text-gray-600 mb-3">Blok & Mat selectie</h3>
        <div class="flex gap-4">
            <div class="w-40">
                <label class="block text-gray-600 text-sm mb-1">Blok</label>
                <select x-model="blokId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">Selecteer...</option>
                    @foreach($blokken as $blok)
                    <option value="{{ $blok->id }}">Blok {{ $blok->nummer }}
                        @if($blok->weging_gesloten) (gesloten) @endif
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="w-40">
                <label class="block text-gray-600 text-sm mb-1">Mat</label>
                <select x-model="matId" @change="laadWedstrijden()" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">Selecteer...</option>
                    @foreach($matten as $mat)
                    <option value="{{ $mat->id }}">Mat {{ $mat->nummer }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<script>
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

    console.log('DROP IN SWAP:', data, 'isLocked:', isLocked);

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
            console.log('Verwijder response:', await response.json());
        } catch (err) {
            console.error('Fout bij verwijderen:', err);
        }
    }

    // Refresh display
    Alpine.evaluate(document.querySelector('[x-data]'), 'laadWedstrijden()');
};

// Start drag vanuit swap ruimte
window.startDragFromSwap = function(event, judoka, pouleId) {
    event.dataTransfer.setData('text/plain', JSON.stringify({
        judokaId: judoka.id,
        judokaNaam: judoka.naam,
        fromSwap: true,
        pouleId: pouleId,
        pouleIsLocked: false // Swap is alleen actief tijdens seeding
    }));
};

// Global drop handler - plaats judoka in slot
// Als judoka vanuit een vorige wedstrijd komt, stuur bron_wedstrijd_id mee
// Check of positie correct is volgens schema, anders waarschuwing
// pouleId en huidigeBewoner zijn optioneel voor seeding functionaliteit
window.dropJudoka = async function(event, targetWedstrijdId, positie, pouleId = null, huidigeBewoner = null) {
    event.preventDefault();
    const data = JSON.parse(event.dataTransfer.getData('text/plain'));

    // Voeg target info toe aan data voor seeding logica
    if (pouleId) data.pouleId = pouleId;
    if (huidigeBewoner && huidigeBewoner.id !== data.judokaId) {
        data.targetHuidigeJudoka = huidigeBewoner;
    }

    // DEBUG: Log alle drag data
    console.log('=== DROP JUDOKA DEBUG ===');
    console.log('Target wedstrijd:', targetWedstrijdId, 'Positie:', positie);
    console.log('Huidige bewoner:', huidigeBewoner);
    console.log('Drag data:', JSON.stringify(data, null, 2));

    // Check of we in seeding-fase zijn (geen wedstrijden gespeeld in deze poule)
    // data.pouleIsLocked wordt meegegeven vanuit de drag source
    const isLocked = data.pouleIsLocked === true;
    const naam = data.judokaNaam || 'Deze judoka';
    console.log('isLocked:', isLocked);

    // Check 1: Dezelfde wedstrijd?
    console.log('Check 1: wedstrijdId', data.wedstrijdId, '==', targetWedstrijdId, '?', data.wedstrijdId == targetWedstrijdId);
    if (data.wedstrijdId == targetWedstrijdId) {
        if (data.positie === positie) {
            // Zelfde wedstrijd EN zelfde positie = teruggezet op oude plek, negeren
            console.log('Judoka teruggezet op oude plek, geen actie nodig');
            return;
        }
        // Zelfde wedstrijd maar andere positie = blokkeer
        alert(
            `❌ GEBLOKKEERD: Kan niet verplaatsen binnen dezelfde wedstrijd!\n\n` +
            `${naam} staat al in deze wedstrijd.`
        );
        return;
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
            return;
        }
    }

    // Check 2: Winnaar doorschuiven naar VERKEERDE positie = GEBLOKKEERD (met uitzonderingen)
    // Alleen strikt checken als wedstrijd al gespeeld is EN dit de winnaar is
    console.log('Check 2: volgendeWedstrijdId=', data.volgendeWedstrijdId, 'target=', targetWedstrijdId, 'slot=', data.winnaarNaarSlot, 'positie=', positie);

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
                return;
            }
            // Juiste wedstrijd EN juiste positie - doorgaan!
            console.log('Check 2 PASSED: winnaar naar juiste plek');
        } else {
            // Verkeerde wedstrijd - blokkeer alleen als NIET naar wit slot van volgende ronde
            // (Dit staat toe dat winnaars naar (2) rondes gaan zelfs als volgendeWedstrijdId niet perfect matcht)
            alert(
                `❌ VERKEERDE WEDSTRIJD!\n\n` +
                `${naam} moet naar een andere wedstrijd in het schema.\n\n` +
                `Zet de winnaar EERST op de juiste plek.`
            );
            return;
        }
    } else if (data.volgendeWedstrijdId && !data.isGespeeld) {
        // Wedstrijd nog niet gespeeld - soepeler checken, sta doorschuiven toe
        console.log('Check 2 SOEPEL: wedstrijd nog niet gespeeld, doorschuiven toegestaan');
    }

    // ============================================================
    // BLOKKEER-CHECKS VOOR ADMIN WACHTWOORD
    // Voorkomt dat we om wachtwoord vragen voor ongeldige acties
    // ============================================================

    console.log('=== BLOKKEER-CHECKS ===');
    console.log('isLocked:', isLocked);
    console.log('volgendeWedstrijdId:', data.volgendeWedstrijdId, 'type:', typeof data.volgendeWedstrijdId);
    console.log('targetWedstrijdId:', targetWedstrijdId, 'type:', typeof targetWedstrijdId);
    console.log('winnaarNaarSlot:', data.winnaarNaarSlot);
    console.log('positie:', positie);

    // Slot validatie ALTIJD als dit een winnaar-doorschuif is (naar volgende ronde)
    // Seeding is BINNEN dezelfde ronde, niet naar volgende ronde - dus daar geldt geen slot validatie
    const isWinnaarDoorschuifPoging = data.volgendeWedstrijdId && String(data.volgendeWedstrijdId) === String(targetWedstrijdId);

    if (isWinnaarDoorschuifPoging) {
        // Dit is een winnaar-doorschuif naar de volgende ronde - slot validatie is VERPLICHT
        if (data.winnaarNaarSlot && data.winnaarNaarSlot !== positie) {
            console.log('BLOKKADE: Verkeerde positie!', data.winnaarNaarSlot, '!==', positie);
            const juistePositie = data.winnaarNaarSlot === 'wit' ? 'WIT (boven)' : 'BLAUW (onder)';
            const gekozenPositie = positie === 'wit' ? 'WIT (boven)' : 'BLAUW (onder)';
            alert(
                `❌ GEBLOKKEERD: Verkeerde positie!\n\n` +
                `${naam} moet op ${juistePositie} staan, niet op ${gekozenPositie}.`
            );
            return;
        }
        console.log('WINNAAR-DOORSCHUIF: slot validatie passed');
    } else if (isLocked && data.volgendeWedstrijdId) {
        // Locked bracket, verkeerde wedstrijd → BLOKKEER
        console.log('BLOKKADE: Verkeerde wedstrijd!', data.volgendeWedstrijdId, '!==', targetWedstrijdId);
        alert(
            `❌ GEBLOKKEERD: Verkeerde wedstrijd!\n\n` +
            `${naam} kan alleen naar wedstrijd ${data.volgendeWedstrijdId}, niet naar ${targetWedstrijdId}.\n` +
            `Sleep naar het juiste vak.`
        );
        return;
    } else {
        console.log('SEEDING MODE: vrij verplaatsen binnen ronde');
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
                return; // Geannuleerd
            }

            // Check wachtwoord
            const adminWachtwoord = '{{ $toernooi->admin_wachtwoord ?? "admin123" }}';
            if (wachtwoord !== adminWachtwoord) {
                alert('❌ Onjuist wachtwoord!\n\nWijziging geannuleerd.');
                return;
            }

            // Admin geautoriseerd - sta wijziging toe
            console.log('ADMIN OVERRIDE - wijziging toegestaan, isCorrectie:', isCorrectiePoging);
            data.isAdminOverride = true;

            // Zet correctie flag als dit een correctie is
            if (isCorrectiePoging) {
                data.isCorrectie = true;
            }
        }
    }

    // Check 3: Extra logging (checks al gedaan boven)
    console.log('Check 3: volgendeWedstrijdId:', data.volgendeWedstrijdId, 'winnaarNaarSlot:', data.winnaarNaarSlot);
    if (isLocked && data.volgendeWedstrijdId) {
        // Checks al gedaan boven - hier alleen logging
        console.log('Validatie passed - juiste wedstrijd en positie');

        // Check 2c: Als wedstrijd AL gespeeld is en dit is NIET de winnaar = CORRECTIE
        if (!data.isAdminOverride && data.isGespeeld && !data.isWinnaar) {
            if (!confirm(
                `⚠️ CORRECTIE: Winnaar wijzigen?\n\n` +
                `${naam} was niet de winnaar van deze wedstrijd.\n\n` +
                `Wil je ${naam} als nieuwe winnaar instellen?\n` +
                `(De oude winnaar wordt uit de volgende ronde verwijderd en de B-groep wordt aangepast)`
            )) {
                return; // Gebruiker annuleerde
            }
            // Gebruiker bevestigde - markeer als correctie
            data.isCorrectie = true;
        }
    } else if (!isLocked) {
        // SEEDING MODE: Vrij verplaatsen toegestaan
        console.log('SEEDING MODE - vrij verplaatsen toegestaan');
    } else {
        // Geen volgendeWedstrijdId = finale of bye judoka
        console.log('GEEN volgendeWedstrijdId - finale of bye judoka, wordt geaccepteerd');
    }

    console.log('=== VALIDATIE PASSED - doorgaan met plaatsen ===');

    try {
        // Bepaal of dit een SEEDING (move binnen ronde) of WEDSTRIJD WINNEN (copy naar volgende ronde) is
        // Seeding = target is NIET de volgende_wedstrijd_id
        // Winnen = target IS de volgende_wedstrijd_id
        const isWinnaarDoorschuif = data.volgendeWedstrijdId && data.volgendeWedstrijdId == targetWedstrijdId;
        const isSeeding = !isWinnaarDoorschuif && !isLocked;

        console.log('=== ACTIE TYPE ===', { isWinnaarDoorschuif, isSeeding, isLocked });

        if (isSeeding) {
            // SEEDING MODE: Verplaatsen binnen zelfde ronde (MOVE)
            // Huidige bewoner naar swap
            if (data.targetHuidigeJudoka) {
                console.log('SEEDING: Huidige bewoner naar swap:', data.targetHuidigeJudoka);
                window.addToSwap(data.pouleId, {
                    id: data.targetHuidigeJudoka.id,
                    naam: data.targetHuidigeJudoka.naam
                });
            }

            // Als judoka uit swap komt, verwijder uit swap
            if (data.fromSwap && data.pouleId) {
                console.log('Verwijder uit swap:', data.judokaId);
                window.removeFromSwap(data.pouleId, data.judokaId);
            }

            // Verwijder judoka uit oude plek (MOVE, niet COPY)
            if (data.wedstrijdId && data.positie && !data.fromSwap) {
                console.log('SEEDING: Verwijder uit oude plek:', data.wedstrijdId, data.positie);
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
            // Judoka blijft in vorige ronde staan (met groene stip)
            // Geen verwijdering uit oude plek - dat is de winnaar markering
            console.log('WINNAAR DOORSCHUIF: Judoka blijft in vorige ronde met groene stip');
        }

        const requestBody = {
            wedstrijd_id: targetWedstrijdId,
            judoka_id: data.judokaId,
            positie: positie,
            bron_wedstrijd_id: data.wedstrijdId || null,  // Stuur bron wedstrijd mee voor uitslag registratie
            is_correctie: data.isCorrectie || false       // Flag voor winnaar-correctie
        };
        console.log('=== FETCH REQUEST ===', requestBody);

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
            return;
        }

        // Toon correcties aan admin als die er zijn
        if (result.correcties && result.correcties.length > 0) {
            alert('✅ Automatische correcties uitgevoerd:\n\n• ' + result.correcties.join('\n• '));
        }

        Alpine.evaluate(document.querySelector('[x-data]'), 'laadWedstrijden()');
    } catch (err) {
        console.error('Drop error:', err);
        alert('❌ Fout bij plaatsen: ' + err.message);
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

        // Refresh bracket
        Alpine.evaluate(document.querySelector('[x-data]'), 'laadWedstrijden()');
    } catch (err) {
        console.error('Medaille drop error:', err);
        alert('❌ Fout bij medaille plaatsing: ' + err.message);
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
            '🔒 BRACKET VERGRENDELD\n\n' +
            `Je probeert ${naam} te verwijderen.\n` +
            'Dit kan alleen door een admin.\n\n' +
            'Voer het admin wachtwoord in:'
        );

        if (!wachtwoord) {
            return; // Geannuleerd
        }

        const adminWachtwoord = '{{ $toernooi->admin_wachtwoord ?? "admin123" }}';
        if (wachtwoord !== adminWachtwoord) {
            alert('❌ Onjuist wachtwoord!\n\nVerwijdering geannuleerd.');
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

        if (response.ok) {
            Alpine.evaluate(document.querySelector('[x-data]'), 'laadWedstrijden()');
        }
    } catch (err) {
        console.error('Verwijder error:', err);
    }
};

function matInterface() {
    const urlParams = new URLSearchParams(window.location.search);
    const blokNummer = urlParams.get('blok');
    const blokkenData = @json($blokken->map(fn($b) => ['id' => $b->id, 'nummer' => $b->nummer]));
    const mattenData = @json($matten->map(fn($m) => ['id' => $m->id, 'nummer' => $m->nummer]));
    const voorgeselecteerdBlok = blokNummer ? blokkenData.find(b => b.nummer == blokNummer) : null;

    return {
        blokId: voorgeselecteerdBlok ? String(voorgeselecteerdBlok.id) : '',
        matId: '',
        poules: [],
        blokkenData,
        mattenData,
        debugSlots: false,  // Toggle om slot nummers te tonen

        init() {
            if (this.blokId && @json($matten->count()) > 0) {
                this.matId = '{{ $matten->first()?->id }}';
                this.laadWedstrijden();
            }
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

        async laadWedstrijden() {
            if (!this.blokId || !this.matId) {
                this.poules = [];
                return;
            }

            const response = await fetch(`{{ route('toernooi.mat.wedstrijden', $toernooi->routeParams()) }}`, {
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

            const data = await response.json();

            // Swap ruimte wordt NIET meer automatisch gecleared
            // Admin kan judoka's in swap houden ook na lock

            // Initialize scores from existing data
            this.poules = data.map(poule => {
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
                        }

                        // Parse JP from scores
                        if (w.score_wit) w.jpScores[w.wit.id] = parseInt(w.score_wit) || 0;
                        if (w.score_blauw) w.jpScores[w.blauw.id] = parseInt(w.score_blauw) || 0;
                    }

                    return w;
                });
                return poule;
            });
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

        updateWP(wedstrijd, judokaId, value) {
            if (!wedstrijd.wit || !wedstrijd.blauw) return;
            const wp = parseInt(value) || 0;
            const opponentId = wedstrijd.wit.id == judokaId ? wedstrijd.blauw.id : wedstrijd.wit.id;

            // Reassign objects voor Alpine reactivity
            wedstrijd.wpScores = { ...wedstrijd.wpScores, [judokaId]: wp };

            // Auto-set opponent WP
            if (wp === 2) {
                wedstrijd.wpScores = { ...wedstrijd.wpScores, [opponentId]: 0 };
            } else if (wp === 0) {
                wedstrijd.wpScores = { ...wedstrijd.wpScores, [opponentId]: 2 };
            }
        },

        updateJP(wedstrijd, judokaId, value) {
            if (!wedstrijd.wit || !wedstrijd.blauw) return;
            const jp = parseInt(value) || 0;
            const opponentId = wedstrijd.wit.id == judokaId ? wedstrijd.blauw.id : wedstrijd.wit.id;

            // Reassign objects voor Alpine reactivity
            wedstrijd.jpScores = { ...wedstrijd.jpScores, [judokaId]: jp };

            // Logica: als JP > 0, dan WP=2 voor winnaar, WP=0 en JP=0 voor verliezer
            if (jp > 0) {
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
            await fetch(`{{ route('toernooi.mat.uitslag', $toernooi->routeParams()) }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    wedstrijd_id: wedstrijd.id,
                    winnaar_id: winnaarId,
                    score_wit: String(wedstrijd.jpScores[wedstrijd.wit.id] || ''),
                    score_blauw: String(wedstrijd.jpScores[wedstrijd.blauw.id] || ''),
                    uitslag_type: 'punten'
                })
            });

            const wasGespeeld = wedstrijd.is_gespeeld;

            // BELANGRIJK: Check VOOR is_gespeeld update of dit de actieve (groene) wedstrijd was
            // Anders klopt de berekening niet meer na de update
            const wasActief = poule && (
                poule.actieve_wedstrijd_id === wedstrijd.id ||
                (!poule.actieve_wedstrijd_id && !wasGespeeld)  // Fallback: eerste niet-gespeelde
            );

            wedstrijd.is_gespeeld = !!(winnaarId || (wedstrijd.jpScores[wedstrijd.wit.id] !== undefined && wedstrijd.jpScores[wedstrijd.blauw.id] !== undefined));
            wedstrijd.winnaar_id = winnaarId;

            // Auto-advance: als groene wedstrijd klaar → gele (klaar maken) wordt groen (speelt)
            if (!wasGespeeld && wedstrijd.is_gespeeld && wasActief) {
                // Gele (huidige_wedstrijd_id = klaar maken) wordt groen (actief)
                // huidige_wedstrijd_id bevat de handmatig geselecteerde gele wedstrijd
                const nieuweActief = poule.huidige_wedstrijd_id || null;

                poule.actieve_wedstrijd_id = nieuweActief;
                poule.huidige_wedstrijd_id = null; // Reset geel

                // Notify backend
                await this.setWedstrijdStatus(poule, nieuweActief, null);
            }
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
            // Bij barrage: alle judoka's in barrage groep krijgen zelfde plaats
            const barrageJudokas = this.getBarrageJudokas(poule);
            if (barrageJudokas.length >= 3) {
                const isInBarrage = barrageJudokas.some(j => j.id === judokaId);
                if (isInBarrage) {
                    return 1; // Allemaal 1e plaats tot barrage gespeeld
                }
            }

            const standings = poule.judokas.map(j => ({
                id: j.id,
                wp: this.getTotaalWP(poule, j.id),
                jp: this.getTotaalJP(poule, j.id)
            }));

            const wedstrijden = poule.wedstrijden;
            standings.sort((a, b) => {
                if (b.wp !== a.wp) return b.wp - a.wp;
                if (b.jp !== a.jp) return b.jp - a.jp;
                // Head-to-head
                for (const w of wedstrijden) {
                    const isMatch = (w.wit.id === a.id && w.blauw.id === b.id)
                                 || (w.wit.id === b.id && w.blauw.id === a.id);
                    if (isMatch && w.winnaar_id) {
                        return w.winnaar_id === a.id ? -1 : 1;
                    }
                }
                return 0;
            });

            return standings.findIndex(s => s.id === judokaId) + 1;
        },

        isPouleAfgerond(poule) {
            if (poule.wedstrijden.length === 0) return false;

            // Eliminatie: check finale + brons wedstrijden
            if (poule.type === 'eliminatie') {
                return this.isEliminatieAfgerond(poule);
            }

            // Poule: alle wedstrijden moeten gespeeld zijn
            return poule.wedstrijden.every(w => w.is_gespeeld);
        },

        // Check of eliminatie bracket volledig is afgerond
        // Afgerond = finale gespeeld + alle brons wedstrijden gespeeld
        isEliminatieAfgerond(poule) {
            // Check finale (A-groep)
            const finale = poule.wedstrijden.find(w => w.groep === 'A' && w.ronde === 'finale');
            if (!finale || !finale.is_gespeeld) return false;

            // Check brons wedstrijden (b_halve_finale_2)
            const bronsWedstrijden = poule.wedstrijden.filter(w =>
                w.ronde === 'b_halve_finale_2' || w.ronde === 'b_brons' || w.ronde === 'b_finale'
            );

            // Als er brons wedstrijden zijn, moeten die ook gespeeld zijn
            if (bronsWedstrijden.length > 0) {
                return bronsWedstrijden.every(w => w.is_gespeeld);
            }

            // Geen brons wedstrijden (kleine bracket) = alleen finale nodig
            return true;
        },

        async markeerKlaar(poule) {
            try {
                const response = await fetch(`{{ route('toernooi.mat.poule-klaar', $toernooi->routeParams()) }}`, {
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
            if (!this.isPouleAfgerond(poule)) return false;

            const barrageJudokas = this.getBarrageJudokas(poule);
            return barrageJudokas.length >= 3;
        },

        // Vind judoka's die barrage moeten spelen (gelijke WP+JP met cirkel-verliezen)
        getBarrageJudokas(poule) {
            const standings = poule.judokas.map(j => ({
                id: j.id,
                naam: j.naam,
                wp: this.getTotaalWP(poule, j.id),
                jp: this.getTotaalJP(poule, j.id)
            }));

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

        // Bepaal huidige (groen) en volgende (geel) wedstrijd
        // Groen = actieve_wedstrijd_id OF eerste niet-gespeelde
        // Geel = huidige_wedstrijd_id OF tweede niet-gespeelde
        getHuidigeEnVolgende(poule) {
            const wedstrijden = poule.wedstrijden;
            if (!wedstrijden || wedstrijden.length === 0) return { huidige: null, volgende: null };

            // Zoek eerste niet-gespeelde wedstrijd (voor auto-fallback)
            const eersteNietGespeeld = wedstrijden.find(w => !w.is_gespeeld);
            const tweedeNietGespeeld = wedstrijden.filter(w => !w.is_gespeeld)[1] || null;

            // Huidige (groen) = handmatig actieve OF eerste niet-gespeelde
            let huidige = null;
            if (poule.actieve_wedstrijd_id) {
                huidige = wedstrijden.find(w => w.id === poule.actieve_wedstrijd_id && !w.is_gespeeld);
            }
            if (!huidige) {
                huidige = eersteNietGespeeld;
            }

            // Volgende (geel) = handmatig geselecteerd OF auto-fallback
            // Auto-fallback is nodig zodat zaal/coach-app de volgende wedstrijd zien
            let volgende = null;
            if (poule.huidige_wedstrijd_id) {
                volgende = wedstrijden.find(w => w.id === poule.huidige_wedstrijd_id && !w.is_gespeeld);
            }
            // Auto-fallback: tweede niet-gespeelde (na huidige)
            if (!volgende && huidige) {
                volgende = wedstrijden.find(w => !w.is_gespeeld && w.id !== huidige.id);
            }

            return { huidige, volgende };
        },

        // CSS class voor wedstrijd header
        getWedstrijdKleurClass(poule, wedstrijd, idx) {
            const { huidige, volgende } = this.getHuidigeEnVolgende(poule);

            if (wedstrijd.is_gespeeld) {
                return 'bg-gray-300 text-gray-600'; // Gespeeld
            }
            if (huidige && wedstrijd.id === huidige.id) {
                return 'bg-green-500 text-white cursor-pointer'; // Huidige (groen) - klikbaar
            }
            if (volgende && wedstrijd.id === volgende.id) {
                return 'bg-yellow-400 text-yellow-900 cursor-pointer'; // Volgende (geel) - klikbaar
            }
            return 'bg-gray-200 text-gray-700 cursor-pointer'; // Nog niet aan de beurt - klikbaar
        },

        // Tooltip voor wedstrijd header
        getWedstrijdTitel(poule, wedstrijd, idx) {
            const { huidige, volgende } = this.getHuidigeEnVolgende(poule);

            if (wedstrijd.is_gespeeld) return 'Gespeeld';
            if (huidige && wedstrijd.id === huidige.id) {
                return 'Speelt nu - klik om te stoppen (gele wordt groen)';
            }
            if (volgende && wedstrijd.id === volgende.id) {
                return 'Volgende partij - klik om te deselecteren';
            }
            return 'Klik om te selecteren';
        },

        // Toggle wedstrijd selectie (groen/geel systeem)
        // MATJURY kan handmatig groen/geel instellen door te klikken
        // CODE mag alleen: geel→groen promoten bij score, groen uitzetten bij score
        // CODE maakt NOOIT automatisch een nieuwe gele aan
        async toggleVolgendeWedstrijd(poule, wedstrijd) {
            // Niet toestaan voor gespeelde wedstrijden
            if (wedstrijd.is_gespeeld) return;

            const { huidige, volgende } = this.getHuidigeEnVolgende(poule);

            // Klik op GROENE wedstrijd = skip (geen punten), gele wordt groen
            // Geen nieuwe gele - matjury moet dat zelf kiezen
            if (huidige && wedstrijd.id === huidige.id) {
                const nieuweActieve = volgende ? volgende.id : null;
                await this.setWedstrijdStatus(poule, nieuweActieve, null);
                return;
            }

            // Klik op GELE wedstrijd = deselecteren
            if (volgende && wedstrijd.id === volgende.id) {
                await this.setWedstrijdStatus(poule, poule.actieve_wedstrijd_id, null);
                return;
            }

            // Klik op andere wedstrijd
            if (huidige) {
                // Er is al een groene, dus deze wordt geel
                await this.setWedstrijdStatus(poule, poule.actieve_wedstrijd_id, wedstrijd.id);
            } else {
                // Geen groene, deze wordt groen (geen automatische gele)
                await this.setWedstrijdStatus(poule, wedstrijd.id, null);
            }
        },

        // Helper: update actieve en/of huidige wedstrijd
        async setWedstrijdStatus(poule, actieveId, huidigeId) {
            try {
                const url = `{{ route('toernooi.mat.huidige-wedstrijd', $toernooi->routeParams()) }}`;

                // Update actieve (groen)
                const response1 = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        poule_id: poule.poule_id,
                        wedstrijd_id: actieveId,
                        type: 'actief'
                    })
                });

                if (!response1.ok) return;

                // Update huidige (geel = klaar maken)
                const response2 = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        poule_id: poule.poule_id,
                        wedstrijd_id: huidigeId,
                        type: 'volgend'
                    })
                });

                if (!response2.ok) return;

                const data1 = await response1.json();
                const data2 = await response2.json();

                if (data1.success && data2.success) {
                    poule.actieve_wedstrijd_id = actieveId;
                    poule.huidige_wedstrijd_id = huidigeId;
                }
            } catch (err) {
                console.error('Fout bij wijzigen wedstrijd status:', err);
            }
        },

        // === ELIMINATIE FUNCTIES ===

        // Ronde volgorde voor sorteren (finale = laatste)
        rondeVolgorde: {
            'zestiende_finale': 1,
            'achtste_finale': 2,
            'kwartfinale': 3,
            'halve_finale': 4,
            'finale': 5,
            'herkansing_r1': 1,
            'herkansing_r2': 2,
            'herkansing_r3': 3,
            'herkansing_r4': 4,
        },

        // Bepaal ronde naam op basis van aantal slots
        getRondeNaamVoorSlots(aantalSlots, rondeKey) {
            // Gebaseerd op aantal deelnemers in de ronde
            if (aantalSlots >= 32) return '1/16';
            if (aantalSlots >= 16) return '1/8';
            if (aantalSlots >= 8) return '1/4';
            if (aantalSlots >= 4) return '1/2';
            if (aantalSlots >= 2) return 'Finale';
            // Fallback: gebruik key maar maak het leesbaar
            return rondeKey.replace('_', ' ').replace('ronde ', 'R');
        },

        // Ronde volgorde voor sorteren (kleinste eerst = meeste wedstrijden)
        // B-groep: deel 1 en deel 2 rondes op volgorde
        rondeVolgordeLookup: {
            // A-groep (1/16 met byes als nodig)
            'zestiende_finale': 1,
            'achtste_finale': 2,
            'kwartfinale': 3,
            'halve_finale': 4,
            'finale': 5,
            // B-groep: dynamische structuur op basis van aantal judoka's
            // 25+ j: B-1/8(1) → B-1/8(2) → B-1/4(1) → B-1/4(2) → B-1/2(1) → B-brons
            // 17-24 j: B-1/8 → B-1/4 → B-brons
            // 13-16 j: B-1/4(1) → B-1/4(2) → B-1/2(1) → B-brons
            // 9-12 j: B-1/4 → B-brons
            'b_achtste_finale_1': 1,
            'b_achtste_finale_2': 2,
            'b_achtste_finale': 1,     // Zonder suffix (17-24 j)
            'b_kwartfinale_1': 3,
            'b_kwartfinale_2': 4,
            'b_kwartfinale': 3,        // Zonder suffix (9-12 j)
            'b_halve_finale_1': 5,
            'b_halve_finale_2': 6,
            'b_brons': 6,  // Legacy support
        },

        // Get bracket als array van rondes met wedstrijden
        getEliminatieBracket(poule, groep) {
            // B-groep: inclusief brons (niet apart renderen)
            const wedstrijden = poule.wedstrijden.filter(w => w.groep === groep);
            if (wedstrijden.length === 0) return [];

            // Groepeer op ronde
            const rondesMap = {};
            wedstrijden.forEach(w => {
                if (!rondesMap[w.ronde]) {
                    rondesMap[w.ronde] = [];
                }
                rondesMap[w.ronde].push(w);
            });

            // Sorteer rondes op volgorde (start/1/16 eerst, finale laatst)
            const rondes = Object.entries(rondesMap)
                .sort((a, b) => {
                    const volgordeA = this.rondeVolgordeLookup[a[0]] ?? 99;
                    const volgordeB = this.rondeVolgordeLookup[b[0]] ?? 99;
                    return volgordeA - volgordeB;
                })
                .map(([ronde, weds]) => {
                    weds.sort((a, b) => (a.bracket_positie || 0) - (b.bracket_positie || 0));

                    // Bepaal leesbare naam (geen aparte voorronde, 1/16 met byes)
                    let naam = this.getRondeDisplayNaam(ronde, weds.length);

                    return { naam, ronde, wedstrijden: weds };
                });

            return rondes;
        },

        // Geef leesbare naam voor ronde
        // A-groep: 1/16 → 1/8 → 1/4 → 1/2 → Finale
        // B-groep: dynamisch op basis van aantal judoka's
        getRondeDisplayNaam(ronde, aantalWeds) {
            const namen = {
                // A-groep
                'zestiende_finale': '1/16',
                'achtste_finale': '1/8',
                'kwartfinale': '1/4',
                'halve_finale': '1/2',
                'finale': 'Finale',
                // B-groep: (1)/(2) alleen als ronde 2x gespeeld wordt
                'b_achtste_finale_1': '1/8 (1)',
                'b_achtste_finale_2': '1/8 (2)',
                'b_achtste_finale': '1/8',      // Zonder suffix
                'b_kwartfinale_1': '1/4 (1)',
                'b_kwartfinale_2': '1/4 (2)',
                'b_kwartfinale': '1/4',         // Zonder suffix
                'b_halve_finale_1': '1/2 (1)',
                'b_halve_finale_2': '1/2 (2)',
                'b_brons': 'Brons',  // Legacy support (IJF)
            };
            return namen[ronde] || ronde.replace('b_', 'B ').replace('_', ' ');
        },

        // Check of poule herkansing (groep B) heeft
        heeftHerkansing(poule) {
            return poule.wedstrijden.some(w => w.groep === 'B');
        },

        // Check of poule bronswedstrijden heeft
        heeftBronsWedstrijden(poule) {
            return poule.wedstrijden.some(w =>
                w.ronde === 'b_halve_finale_2' || w.ronde === 'b_brons'
            );
        },

        // Check of bracket locked is (minimaal 1 wedstrijd gespeeld)
        // In seeding-fase (niet locked) mag je vrij schuiven
        isBracketLocked(poule) {
            return poule.wedstrijden.some(w => w.is_gespeeld);
        },

        // Render bracket als HTML met draggable chips
        renderBracket(poule, groep) {
            const rondes = this.getEliminatieBracket(poule, groep);
            if (rondes.length === 0) return '<div class="text-gray-500">Geen wedstrijden</div>';

            // B-groep: gebruik gespiegelde layout
            if (groep === 'B') {
                return this.renderBBracketMirrored(poule, rondes);
            }

            // Check of bracket locked is (seeding-fase voorbij)
            const isLocked = this.isBracketLocked(poule);

            const h = 28; // slot height
            let html = '';

            // Kleuren op basis van groep
            const headerColor = 'text-purple-600';
            const ringColor = 'ring-purple-400';
            const winIcon = groep === 'A' ? '🏆' : '🥉';

            // Bepaal of er een B-start ronde is (eerste instroom B-groep)
            const bStartIdx = rondes.findIndex(r => r.ronde === 'b_start');
            const heeftBStartInBracket = bStartIdx >= 0;

            // B-groep: zelfde volgorde als A-groep (vroege rondes links, brons rechts)
            const isBGroep = groep === 'B';
            const displayRondes = rondes;

            // Header met ronde namen
            html += `<div class="flex mb-1">`;
            displayRondes.forEach((ronde, rondeIdx) => {
                const isBStartHeader = ronde.ronde === 'b_start';
                const headerWidth = isBStartHeader ? 'w-36' : 'w-32';
                const aantalWeds = ronde.wedstrijden.length;
                const headerTekst = isBStartHeader ? `${ronde.naam} (${aantalWeds})` : ronde.naam;
                html += `<div class="${headerWidth} flex-shrink-0 text-center text-xs font-bold ${headerColor}">${headerTekst}</div>`;
                if (rondeIdx < displayRondes.length - 1) {
                    html += '<div class="w-2 flex-shrink-0"></div>';
                }
            });
            html += `<div class="w-32 flex-shrink-0 text-center text-xs font-bold text-yellow-600">${winIcon}</div>`;
            html += '</div>';

            // Simpele berekening met absolute positioning
            const potjeHeight = 2 * h; // 56px (wit + blauw)
            const potjeGap = 8; // marge tussen potjes

            // Niveau bepaalt verticale positie
            // (1) en (2) rondes krijgen NIET hetzelfde niveau - ze zijn opeenvolgend
            const rondeNiveauMap = {
                // A-groep: standaard bracket
                'zestiende_finale': 0,
                'achtste_finale': 1,
                'kwartfinale': 2,
                'halve_finale': 3,
                'finale': 4,
                // B-groep: elke ronde eigen niveau (opeenvolgend)
                'b_achtste_finale_1': 0,
                'b_achtste_finale_2': 1,
                'b_achtste_finale': 0,    // Zonder suffix
                'b_kwartfinale_1': 0,     // Of 2 als na b_achtste
                'b_kwartfinale_2': 1,     // Of 3 als na b_kwartfinale_1
                'b_kwartfinale': 0,       // Zonder suffix (eerste B-ronde)
                'b_halve_finale_1': 2,
                'b_halve_finale_2': 3,
                'b_brons': 3,  // Legacy support
            };

            // Bereken posities voor elke ronde op basis van niveau
            const berekenPotjeTop = (niveau, potjeIdx) => {
                if (niveau <= 0) {
                    return potjeIdx * (potjeHeight + potjeGap);
                }
                // Gecentreerd tussen 2 potjes van vorige niveau
                const prevPotje1 = potjeIdx * 2;
                const prevPotje2 = potjeIdx * 2 + 1;
                const top1 = berekenPotjeTop(niveau - 1, prevPotje1);
                const top2 = berekenPotjeTop(niveau - 1, prevPotje2);
                // Center tussen de 2 potjes
                const center1 = top1 + potjeHeight / 2;
                const center2 = top2 + potjeHeight / 2;
                const center = (center1 + center2) / 2;
                return center - potjeHeight / 2;
            };

            // Helper om niveau te bepalen voor een ronde
            // Gebruik gewoon rondeIdx voor beide groepen (simpele bracket layout)
            const getNiveau = (rondeNaam, rondeIdx) => {
                return rondeIdx;
            };

            // Bepaal totale hoogte gebaseerd op eerste ronde
            const eersteRonde = rondes[0];
            const tweedeRonde = rondes[1];

            // Bereken aantal slots op basis van eerste ronde wedstrijden
            let aantalSlots;
            if (eersteRonde) {
                // Gebruik eerste ronde wedstrijden * 2 voor correcte spacing
                aantalSlots = eersteRonde.wedstrijden.length * 2;
            } else {
                aantalSlots = 8; // Fallback
            }
            const totaleHoogte = Math.max(aantalSlots * (potjeHeight + potjeGap), 300);

            html += `<div class="flex" style="height: ${totaleHoogte}px;">`;

            // Benodigde data voor medaille slots
            const laatsteRonde = rondes[rondes.length - 1];
            const laatsteRondeWedstrijden = laatsteRonde?.wedstrijden || [];
            const laatsteRondeNiveau = getNiveau(laatsteRonde?.ronde, rondes.length - 1);

            displayRondes.forEach((ronde, rondeIdx) => {
                // Normale ronde rendering met absolute positioning
                    html += `<div class="relative flex-shrink-0 w-32">`;

                    // Sorteer wedstrijden op bracket_positie voor correcte volgorde
                    const sortedWeds = [...ronde.wedstrijden].sort((a, b) => a.bracket_positie - b.bracket_positie);
                    sortedWeds.forEach((wed, wedIdx) => {
                        const isLastRound = rondeIdx === rondes.length - 1;
                        const niveau = getNiveau(ronde.ronde, rondeIdx);
                        const topPos = berekenPotjeTop(niveau, wedIdx);

                        // Potje container met absolute positie
                        html += `<div class="absolute w-32" style="top: ${topPos}px;">`;

                        // Helper: groen cirkeltje voor winnaar (niet bij bye)
                        const winnaarIcon = '<span class="inline-block w-2 h-2 bg-green-500 rounded-full ml-1 flex-shrink-0" title="Winnaar"></span>';
                        const isBye = wed.uitslag_type === 'bye';
                        const isWitWinnaar = wed.is_gespeeld && wed.winnaar_id === wed.wit?.id && !isBye;
                        const isBlauwWinnaar = wed.is_gespeeld && wed.winnaar_id === wed.blauw?.id && !isBye;

                        // Drag data met volgende wedstrijd info voor validatie
                        // isWinnaar en isGespeeld toegevoegd om te checken of doorschuiven toegestaan is
                        // Escape voor JS string (backslash en single quote) en HTML attribute (double quote)
                        const escapeForHtml = (str) => str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');

                        const witDragData = escapeForHtml(JSON.stringify({
                            judokaId: wed.wit?.id,
                            wedstrijdId: wed.id,
                            judokaNaam: wed.wit?.naam || '',
                            volgendeWedstrijdId: wed.volgende_wedstrijd_id,
                            winnaarNaarSlot: wed.winnaar_naar_slot,
                            pouleIsLocked: isLocked,
                            pouleId: poule.poule_id,
                            positie: 'wit',
                            isWinnaar: isWitWinnaar,
                            isGespeeld: wed.is_gespeeld === true
                        }));

                        const blauwDragData = escapeForHtml(JSON.stringify({
                            judokaId: wed.blauw?.id,
                            wedstrijdId: wed.id,
                            judokaNaam: wed.blauw?.naam || '',
                            volgendeWedstrijdId: wed.volgende_wedstrijd_id,
                            winnaarNaarSlot: wed.winnaar_naar_slot,
                            pouleIsLocked: isLocked,
                            pouleId: poule.poule_id,
                            positie: 'blauw',
                            isWinnaar: isBlauwWinnaar,
                            isGespeeld: wed.is_gespeeld === true
                        }));

                        // Data voor huidige bewoners (voor seeding swap)
                        const witBewoner = wed.wit ? escapeForHtml(JSON.stringify({id: wed.wit.id, naam: wed.wit.naam})) : 'null';
                        const blauwBewoner = wed.blauw ? escapeForHtml(JSON.stringify({id: wed.blauw.id, naam: wed.blauw.naam})) : 'null';

                        // Visuele slot nummers (van boven naar beneden: 1,2,3,4,...)
                        const visualSlotWit = wedIdx * 2 + 1;
                        const visualSlotBlauw = wedIdx * 2 + 2;
                        const DEBUG_SLOTS = this.debugSlots;

                        // Wit slot
                        html += `<div class="relative">`;
                        html += `<div class="w-32 h-7 bg-white border border-gray-300 rounded-l flex items-center text-xs drop-slot ${!isLastRound ? 'border-r-0' : ''}"
                                      ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                                      ondragleave="this.classList.remove('ring-2','${ringColor}')"
                                      ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, 'wit', ${poule.poule_id}, ${witBewoner})">`;
                        if (wed.wit) {
                            const displayName = DEBUG_SLOTS ? `[${visualSlotWit}] ${wed.wit.naam}` : wed.wit.naam;
                            html += `<div class="w-full h-full px-1 flex items-center cursor-pointer hover:bg-green-50" draggable="true"
                                          ondragstart="event.dataTransfer.setData('text/plain', '${witDragData}')">
                                        <span class="truncate">${displayName}</span>${isWitWinnaar ? winnaarIcon : ''}
                                     </div>`;
                        } else if (DEBUG_SLOTS) {
                            html += `<span class="px-1 text-gray-400">[${visualSlotWit}]</span>`;
                        }
                        html += '</div>';
                        // Connector lijnen naar rechts
                        if (!isLastRound) {
                            html += `<div class="absolute right-0 top-0 w-4 h-full border-t border-r border-gray-400"></div>`;
                        }
                        html += '</div>';

                        // Blauw slot
                        html += `<div class="relative">`;
                        html += `<div class="w-32 h-7 bg-blue-50 border border-gray-300 rounded-l flex items-center text-xs drop-slot ${!isLastRound ? 'border-r-0' : ''}"
                                      ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                                      ondragleave="this.classList.remove('ring-2','${ringColor}')"
                                      ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, 'blauw', ${poule.poule_id}, ${blauwBewoner})">`;
                        if (wed.blauw) {
                            const displayName = DEBUG_SLOTS ? `[${visualSlotBlauw}] ${wed.blauw.naam}` : wed.blauw.naam;
                            html += `<div class="w-full h-full px-1 flex items-center cursor-pointer hover:bg-green-50" draggable="true"
                                          ondragstart="event.dataTransfer.setData('text/plain', '${blauwDragData}')">
                                        <span class="truncate">${displayName}</span>${isBlauwWinnaar ? winnaarIcon : ''}
                                     </div>`;
                        } else if (DEBUG_SLOTS) {
                            html += `<span class="px-1 text-gray-400">[${visualSlotBlauw}]</span>`;
                        }
                        html += '</div>';
                        if (!isLastRound) {
                            html += `<div class="absolute right-0 top-0 w-4 h-full border-b border-r border-gray-400"></div>`;
                        }
                        html += '</div>';

                        html += '</div>'; // einde potje
                    });

                    html += '</div>';

                // Ruimte voor connector
                if (rondeIdx < rondes.length - 1) {
                    html += '<div class="w-2 flex-shrink-0"></div>';
                }
            });

            // Medaille slots RECHTS van finale/brons
            if (groep === 'A') {
                const finale = laatsteRondeWedstrijden[0];
                const winnaar = finale?.is_gespeeld ? (finale.winnaar_id === finale.wit?.id ? finale.wit : finale.blauw) : null;
                const verliezer = finale?.is_gespeeld ? (finale.winnaar_id === finale.wit?.id ? finale.blauw : finale.wit) : null;
                // Winnaar (goud) en verliezer (zilver) naast finale
                const winnaarTop = berekenPotjeTop(laatsteRondeNiveau, 0) + h / 2 - 16; // Iets hoger voor goud
                html += `<div class="relative flex-shrink-0 w-32">`;
                // Goud (1e plaats) - drop target voor finale winnaar
                html += `<div class="absolute w-32" style="top: ${winnaarTop}px;">`;
                html += `<div class="w-32 h-7 bg-yellow-100 border border-yellow-400 rounded flex items-center px-1 text-xs font-bold truncate ${!winnaar ? 'cursor-pointer' : ''}"
                              ondragover="event.preventDefault(); if(!${!!winnaar}) this.classList.add('ring-2','ring-yellow-500')"
                              ondragleave="this.classList.remove('ring-2','ring-yellow-500')"
                              ondrop="this.classList.remove('ring-2','ring-yellow-500'); window.dropOpMedaille(event, ${finale?.id || 'null'}, 'goud', ${poule.poule_id})">`;
                html += winnaar ? `🥇 ${winnaar.naam}` : '🥇 Sleep winnaar hier';
                html += '</div></div>';
                // Zilver (2e plaats) - drop target voor finale verliezer
                html += `<div class="absolute w-32" style="top: ${winnaarTop + 30}px;">`;
                html += `<div class="w-32 h-7 bg-gray-200 border border-gray-400 rounded flex items-center px-1 text-xs truncate ${!verliezer ? 'cursor-pointer' : ''}"
                              ondragover="event.preventDefault(); if(!${!!verliezer}) this.classList.add('ring-2','ring-gray-500')"
                              ondragleave="this.classList.remove('ring-2','ring-gray-500')"
                              ondrop="this.classList.remove('ring-2','ring-gray-500'); window.dropOpMedaille(event, ${finale?.id || 'null'}, 'zilver', ${poule.poule_id})">`;
                html += verliezer ? `🥈 ${verliezer.naam}` : '🥈 Sleep verliezer hier';
                html += '</div></div>';
                html += '</div>';
            } else {
                // B groep: bronzen winnaars rechts van brons wedstrijden
                html += `<div class="relative flex-shrink-0 w-32">`;
                laatsteRondeWedstrijden.forEach((wed, wedIdx) => {
                    const winnaar = wed.is_gespeeld ? (wed.winnaar_id === wed.wit?.id ? wed.wit : wed.blauw) : null;
                    const winnaarTop = berekenPotjeTop(laatsteRondeNiveau, wedIdx) + h / 2;
                    html += `<div class="absolute w-32" style="top: ${winnaarTop}px;">`;
                    html += `<div class="w-32 h-7 bg-amber-100 border border-amber-400 rounded flex items-center px-1 text-xs truncate ${!winnaar ? 'cursor-pointer' : ''}"
                                  ondragover="event.preventDefault(); if(!${!!winnaar}) this.classList.add('ring-2','ring-amber-500')"
                                  ondragleave="this.classList.remove('ring-2','ring-amber-500')"
                                  ondrop="this.classList.remove('ring-2','ring-amber-500'); window.dropOpMedaille(event, ${wed.id}, 'brons', ${poule.poule_id})">`;
                    html += winnaar ? `🥉 ${winnaar.naam}` : '🥉 Sleep winnaar';
                    html += '</div></div>';
                });
                html += '</div>';
            }

            html += '</div>';

            return html;
        },

        // B-groep gespiegelde layout: bovenste helft en onderste helft rond horizon
        // (1) en (2) rondes horizontaal naast elkaar
        renderBBracketMirrored(poule, rondes) {
            const isLocked = this.isBracketLocked(poule);
            const h = 28; // slot height
            const potjeHeight = 2 * h;
            const potjeGap = 8;
            const ringColor = 'ring-purple-400';

            // Groepeer rondes per niveau: b_achtste_finale_1 en _2 samen, etc.
            // Niveau = basis ronde naam zonder _1 of _2
            const niveaus = [];
            const niveauMap = {};

            rondes.forEach(ronde => {
                // Bepaal basis niveau (zonder _1 of _2)
                let basisNiveau = ronde.ronde.replace(/_[12]$/, '');
                if (!niveauMap[basisNiveau]) {
                    niveauMap[basisNiveau] = { naam: basisNiveau, subRondes: [] };
                    niveaus.push(niveauMap[basisNiveau]);
                }
                niveauMap[basisNiveau].subRondes.push(ronde);
            });

            // Bepaal aantal wedstrijden in eerste niveau (voor hoogte berekening)
            const eersteNiveau = niveaus[0];
            const wedsPerHelft = eersteNiveau ? Math.ceil(eersteNiveau.subRondes[0].wedstrijden.length / 2) : 4;

            // Totale hoogte: 2 helften met ruimte ertussen
            const helftHoogte = wedsPerHelft * (potjeHeight + potjeGap);
            const horizonHoogte = 20; // Ruimte tussen helften (lijn niet zichtbaar)
            const totaleHoogte = 2 * helftHoogte + horizonHoogte;

            let html = '';

            // Header met niveau namen (niet individuele rondes)
            html += `<div class="flex mb-4">`;
            niveaus.forEach((niveau, idx) => {
                // Voor elk niveau: toon de subronde namen
                niveau.subRondes.forEach((sr, srIdx) => {
                    html += `<div class="w-32 flex-shrink-0 text-center text-xs font-bold text-purple-600 px-1">${sr.naam}</div>`;
                    if (srIdx < niveau.subRondes.length - 1 || idx < niveaus.length - 1) {
                        html += '<div class="w-4 flex-shrink-0"></div>';
                    }
                });
            });
            html += `<div class="w-32 flex-shrink-0 text-center text-xs font-bold text-yellow-600 px-1">🥉</div>`;
            html += '</div>';

            // Main container
            html += `<div class="flex" style="height: ${totaleHoogte}px;">`;

            // Render elke subronde als kolom
            niveaus.forEach((niveau, niveauIdx) => {
                niveau.subRondes.forEach((ronde, subRondeIdx) => {
                    const sortedWeds = [...ronde.wedstrijden].sort((a, b) => a.bracket_positie - b.bracket_positie);
                    const halfCount = Math.ceil(sortedWeds.length / 2);
                    const isLastNiveau = niveauIdx === niveaus.length - 1;
                    const isLastSubRonde = subRondeIdx === niveau.subRondes.length - 1;
                    const isLastColumn = isLastNiveau && isLastSubRonde;

                    // Is dit een (1) ronde? Dan offset omhoog voor alignment met wit slot van (2)
                    const isRonde1 = ronde.ronde.endsWith('_1');
                    const verticalOffset = isRonde1 ? -h / 2 : 0; // Halve slot hoogte omhoog

                    // Is dit een (2) ronde? Dan toon A-verliezer placeholder
                    const isRonde2 = ronde.ronde.endsWith('_2');
                    // Bepaal A-ronde naam voor placeholder (b_achtste_finale_2 → A-1/8)
                    let aRondeNaam = '';
                    if (isRonde2) {
                        if (ronde.ronde.includes('achtste')) aRondeNaam = 'A-1/8';
                        else if (ronde.ronde.includes('kwart')) aRondeNaam = 'A-1/4';
                        else if (ronde.ronde.includes('halve')) aRondeNaam = 'A-1/2';
                    }

                    html += `<div class="relative flex-shrink-0 w-32">`;

                    // Bovenste helft (wedstrijden 0 tot halfCount-1)
                    for (let i = 0; i < halfCount; i++) {
                        const wed = sortedWeds[i];
                        if (!wed) continue;

                        // Bereken positie binnen bovenste helft
                        const spacing = helftHoogte / halfCount;
                        const topPos = i * spacing + (spacing - potjeHeight) / 2 + verticalOffset;

                        // Gebruik database slot nummers (locatie_wit, locatie_blauw)
                        html += this.renderBPotje(wed, poule, topPos, isLastColumn, isLocked, ringColor, isRonde2, aRondeNaam, false, null, null);
                    }

                    // Geen horizon lijn in B-groep (was verwarrend)

                    // Onderste helft (wedstrijden halfCount tot einde) - gespiegeld
                    // De wedstrijden worden visueel gespiegeld, maar slot nummers komen uit database
                    for (let i = halfCount; i < sortedWeds.length; i++) {
                        const wed = sortedWeds[i];
                        if (!wed) continue;

                        // Bereken positie binnen onderste helft (gespiegeld)
                        // Voor (1) rondes: offset omlaag (tegenovergestelde richting van bovenste helft)
                        const mirroredIdx = sortedWeds.length - 1 - i;
                        const spacing = helftHoogte / halfCount;
                        const mirroredOffset = isRonde1 ? h / 2 : 0; // Halve slot hoogte omlaag voor spiegeling
                        const topPos = helftHoogte + horizonHoogte + mirroredIdx * spacing + (spacing - potjeHeight) / 2 + mirroredOffset;

                        // Gebruik database slot nummers (locatie_wit, locatie_blauw)
                        html += this.renderBPotje(wed, poule, topPos, isLastColumn, isLocked, ringColor, isRonde2, aRondeNaam, true, null, null);
                    }

                    html += '</div>';

                    // Ruimte tussen kolommen (moet matchen met header spacing)
                    if (!isLastColumn) {
                        html += '<div class="w-4 flex-shrink-0"></div>';
                    }
                });
            });

            // Medaille slots rechts van laatste kolom (1/2(2) wedstrijden)
            const laatsteNiveau = niveaus[niveaus.length - 1];
            const laatsteRonde = laatsteNiveau?.subRondes[laatsteNiveau.subRondes.length - 1];
            const sortedLaatsteWeds = [...(laatsteRonde?.wedstrijden || [])].sort((a, b) => a.bracket_positie - b.bracket_positie);
            const halfLaatste = Math.ceil(sortedLaatsteWeds.length / 2);

            html += `<div class="relative flex-shrink-0 w-32">`;

            // Brons 1 (bovenste 1/2(2) winnaar) - zelfde positie als bovenste 1/2(2) wedstrijd
            if (sortedLaatsteWeds.length > 0) {
                const wed1 = sortedLaatsteWeds[0]; // Eerste wedstrijd = bovenste helft
                const winnaar1 = wed1?.is_gespeeld ? (wed1.winnaar_id === wed1.wit?.id ? wed1.wit : wed1.blauw) : null;
                const spacing = helftHoogte / halfLaatste;
                const bronPos1 = 0 * spacing + (spacing - potjeHeight) / 2 + h / 2;

                html += `<div class="absolute w-32" style="top: ${bronPos1}px;">`;
                html += `<div class="w-32 h-7 bg-amber-100 border border-amber-400 rounded flex items-center px-1 text-xs truncate"
                              ondragover="event.preventDefault(); this.classList.add('ring-2','ring-amber-500')"
                              ondragleave="this.classList.remove('ring-2','ring-amber-500')"
                              ondrop="this.classList.remove('ring-2','ring-amber-500'); window.dropOpMedaille(event, ${wed1?.id || 'null'}, 'brons', ${poule.poule_id})">`;
                html += winnaar1 ? `🥉 ${winnaar1.naam}` : '🥉 Sleep winnaar hier';
                html += '</div></div>';
            }

            // Brons 2 (onderste 1/2(2) winnaar) - gespiegelde positie onder horizon
            if (sortedLaatsteWeds.length > 1) {
                const wed2 = sortedLaatsteWeds[sortedLaatsteWeds.length - 1]; // Laatste wedstrijd = onderste helft
                const winnaar2 = wed2?.is_gespeeld ? (wed2.winnaar_id === wed2.wit?.id ? wed2.wit : wed2.blauw) : null;
                // Gespiegelde positie: eerste positie onder horizon (gespiegeld = dichtst bij horizon)
                const spacing = helftHoogte / halfLaatste;
                const bronPos2 = helftHoogte + horizonHoogte + 0 * spacing + (spacing - potjeHeight) / 2 + h / 2;

                html += `<div class="absolute w-32" style="top: ${bronPos2}px;">`;
                html += `<div class="w-32 h-7 bg-amber-100 border border-amber-400 rounded flex items-center px-1 text-xs truncate"
                              ondragover="event.preventDefault(); this.classList.add('ring-2','ring-amber-500')"
                              ondragleave="this.classList.remove('ring-2','ring-amber-500')"
                              ondrop="this.classList.remove('ring-2','ring-amber-500'); window.dropOpMedaille(event, ${wed2?.id || 'null'}, 'brons', ${poule.poule_id})">`;
                html += winnaar2 ? `🥉 ${winnaar2.naam}` : '🥉 Sleep winnaar hier';
                html += '</div></div>';
            }

            html += '</div>';
            html += '</div>';

            return html;
        },

        // Helper: render een B-groep potje
        // isRonde2 = true als dit een (2) ronde is waar blauw slot A-verliezer krijgt
        // isMirrored = true voor onderste helft (alleen grafisch, slot nummers lopen door)
        // visualSlotWit/visualSlotBlauw = visuele slot nummers (van boven naar beneden doorlopend)
        renderBPotje(wed, poule, topPos, isLastColumn, isLocked, ringColor, isRonde2 = false, aRondeNaam = '', isMirrored = false, visualSlotWit = null, visualSlotBlauw = null) {
            const isBye = wed.uitslag_type === 'bye';
            const isWitWinnaar = wed.is_gespeeld && wed.winnaar_id === wed.wit?.id && !isBye;
            const isBlauwWinnaar = wed.is_gespeeld && wed.winnaar_id === wed.blauw?.id && !isBye;
            const winnaarIcon = '<span class="inline-block w-2 h-2 bg-green-500 rounded-full ml-1 flex-shrink-0" title="Winnaar"></span>';

            // DEBUG: Toon visuele slot nummers (doorlopend van boven naar beneden)
            const DEBUG_SLOTS = this.debugSlots;
            // Gebruik visuele slot nummers als doorgegeven, anders fallback naar wedstrijd-gebaseerd
            const topSlotNr = visualSlotWit ?? (wed.locatie_wit || (wed.bracket_positie * 2 - 1));
            const bottomSlotNr = visualSlotBlauw ?? (wed.locatie_blauw || (wed.bracket_positie * 2));

            // WIT = altijd boven, BLAUW = altijd onder (spiegeling is alleen grafisch)
            const topSlot = 'wit';
            const bottomSlot = 'blauw';
            const topJudoka = wed.wit;
            const bottomJudoka = wed.blauw;
            const topIsWinnaar = isWitWinnaar;
            const bottomIsWinnaar = isBlauwWinnaar;
            const topBgColor = 'bg-white';
            const bottomBgColor = 'bg-blue-50';

            // Escape functie voor HTML attributes (quotes en single quotes)
            const escapeForHtml = (str) => str.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');

            const topDragData = escapeForHtml(JSON.stringify({
                judokaId: topJudoka?.id,
                wedstrijdId: wed.id,
                judokaNaam: topJudoka?.naam || '',
                volgendeWedstrijdId: wed.volgende_wedstrijd_id,
                winnaarNaarSlot: wed.winnaar_naar_slot,
                pouleIsLocked: isLocked,
                pouleId: poule.poule_id,
                positie: topSlot,
                isWinnaar: topIsWinnaar,
                isGespeeld: wed.is_gespeeld === true
            }));

            const bottomDragData = escapeForHtml(JSON.stringify({
                judokaId: bottomJudoka?.id,
                wedstrijdId: wed.id,
                judokaNaam: bottomJudoka?.naam || '',
                volgendeWedstrijdId: wed.volgende_wedstrijd_id,
                winnaarNaarSlot: wed.winnaar_naar_slot,
                pouleIsLocked: isLocked,
                pouleId: poule.poule_id,
                positie: bottomSlot,
                isWinnaar: bottomIsWinnaar,
                isGespeeld: wed.is_gespeeld === true
            }));

            const topBewoner = topJudoka ? escapeForHtml(JSON.stringify({id: topJudoka.id, naam: topJudoka.naam})) : 'null';
            const bottomBewoner = bottomJudoka ? escapeForHtml(JSON.stringify({id: bottomJudoka.id, naam: bottomJudoka.naam})) : 'null';

            let html = `<div class="absolute w-32" style="top: ${topPos}px;">`;

            // Top slot = WIT (altijd)
            html += `<div class="relative">`;
            html += `<div class="w-32 h-7 ${topBgColor} border border-gray-300 rounded-l flex items-center text-xs drop-slot ${!isLastColumn ? 'border-r-0' : ''}"
                          ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                          ondragleave="this.classList.remove('ring-2','${ringColor}')"
                          ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, '${topSlot}', ${poule.poule_id}, ${topBewoner})">`;
            if (topJudoka) {
                const displayName = DEBUG_SLOTS ? `[${topSlotNr}] ${topJudoka.naam}` : topJudoka.naam;
                html += `<div class="w-full h-full px-1 flex items-center cursor-pointer hover:bg-green-50" draggable="true"
                              ondragstart="event.dataTransfer.setData('text/plain', '${topDragData}')">
                            <span class="truncate">${displayName}</span>${topIsWinnaar ? winnaarIcon : ''}
                         </div>`;
            } else if (DEBUG_SLOTS) {
                html += `<span class="px-1 text-gray-400">[${topSlotNr}]</span>`;
            }
            html += '</div>';
            if (!isLastColumn) {
                html += `<div class="absolute right-0 top-0 w-4 h-full border-t border-r border-gray-400"></div>`;
            }
            html += '</div>';

            // Bottom slot = BLAUW (altijd)
            html += `<div class="relative">`;
            html += `<div class="w-32 h-7 ${bottomBgColor} border border-gray-300 rounded-l flex items-center text-xs drop-slot ${!isLastColumn ? 'border-r-0' : ''}"
                          ondragover="event.preventDefault(); this.classList.add('ring-2','${ringColor}')"
                          ondragleave="this.classList.remove('ring-2','${ringColor}')"
                          ondrop="this.classList.remove('ring-2','${ringColor}'); window.dropJudoka(event, ${wed.id}, '${bottomSlot}', ${poule.poule_id}, ${bottomBewoner})">`;
            if (bottomJudoka) {
                const displayName = DEBUG_SLOTS ? `[${bottomSlotNr}] ${bottomJudoka.naam}` : bottomJudoka.naam;
                html += `<div class="w-full h-full px-1 flex items-center cursor-pointer hover:bg-green-50" draggable="true"
                              ondragstart="event.dataTransfer.setData('text/plain', '${bottomDragData}')">
                            <span class="truncate">${displayName}</span>${bottomIsWinnaar ? winnaarIcon : ''}
                         </div>`;
            } else if (isRonde2 && aRondeNaam) {
                // Placeholder: A-verliezer komt altijd op BLAUW slot
                html += `<span class="px-1 text-gray-400 italic text-xs">← uit ${aRondeNaam}</span>`;
            } else if (DEBUG_SLOTS) {
                html += `<span class="px-1 text-gray-400">[${bottomSlotNr}]</span>`;
            }
            html += '</div>';
            if (!isLastColumn) {
                html += `<div class="absolute right-0 top-0 w-4 h-full border-b border-r border-gray-400"></div>`;
            }
            html += '</div>';

            html += '</div>';
            return html;
        },

        // Get finale winnaar (GOUD)
        getFinaleWinnaar(poule, groep) {
            const finale = poule.wedstrijden.find(w => w.groep === groep && w.ronde === 'finale');
            if (!finale || !finale.is_gespeeld || !finale.winnaar_id) return null;
            return finale.winnaar_id === finale.wit?.id ? finale.wit : finale.blauw;
        },

        // Get finale verliezer (ZILVER)
        getFinaleVerliezer(poule) {
            const finale = poule.wedstrijden.find(w => w.groep === 'A' && w.ronde === 'finale');
            if (!finale || !finale.is_gespeeld || !finale.winnaar_id) return null;
            // Verliezer is degene die NIET de winnaar is
            return finale.winnaar_id === finale.wit?.id ? finale.blauw : finale.wit;
        },

        // Get brons winnaars (2 stuks bij double elimination)
        getBronsWinnaars(poule) {
            const bronsWedstrijden = poule.wedstrijden.filter(w =>
                w.ronde === 'b_halve_finale_2' || w.ronde === 'b_brons' || w.ronde === 'b_finale'
            );
            return bronsWedstrijden
                .filter(w => w.is_gespeeld && w.winnaar_id)
                .map(w => w.winnaar_id === w.wit?.id ? w.wit : w.blauw)
                .filter(j => j);
        },

        // Get alle medaille winnaars voor eliminatie
        getMedailleWinnaars(poule) {
            const goud = this.getFinaleWinnaar(poule, 'A');
            const zilver = this.getFinaleVerliezer(poule);
            const brons = this.getBronsWinnaars(poule);
            return { goud, zilver, brons };
        },

        // Drag & drop handler
        async dropJudoka(event, poule, targetSlot) {
            // Get dragged judoka data from event
            const dragData = event.dataTransfer?.getData('text/plain');
            if (!dragData) return;

            try {
                const { judokaId, fromWedstrijdId, fromPositie } = JSON.parse(dragData);

                // Find target wedstrijd
                const targetWedstrijd = poule.wedstrijden.find(w => w.id === targetSlot.wedstrijdId);
                if (!targetWedstrijd) return;

                // Registreer als winnaar als we van vorige ronde naar volgende slepen
                const fromWedstrijd = poule.wedstrijden.find(w => w.id === fromWedstrijdId);
                if (fromWedstrijd && fromWedstrijd.volgende_wedstrijd_id === targetSlot.wedstrijdId) {
                    // Dit is een winnaar die doorschuift
                    const response = await fetch(`{{ route('toernooi.mat.uitslag', $toernooi->routeParams()) }}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            wedstrijd_id: fromWedstrijdId,
                            winnaar_id: judokaId,
                            uitslag_type: 'eliminatie'
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.laadWedstrijden();
                    }
                }
            } catch (err) {
                console.error('Drop error:', err);
            }
        }
    }
}

// Clock
function updateClock() {
    const now = new Date();
    document.getElementById('clock').textContent =
        now.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' });
}
updateClock();
setInterval(updateClock, 1000);

// Auto-refresh poules elke 30 seconden (voor verplaatste poules van andere matten)
setInterval(() => {
    const component = document.querySelector('[x-data]');
    if (component && component.__x) {
        Alpine.evaluate(component, 'laadWedstrijden()');
    }
}, 30000);
</script>
