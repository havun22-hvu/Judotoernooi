@extends('layouts.app')

@section('title', 'Weeglijst Live')

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-2xl font-bold text-gray-800">⚖️ Weeglijst Live</h1>
    <div class="text-sm text-gray-500">Auto-refresh 10s</div>
</div>

<div x-data="weeglijst()" x-init="init()">
    <!-- Filters en stats -->
    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <input type="text" x-model="zoekterm" @input="filterJudokas()"
                       placeholder="Zoek naam of club..."
                       class="border-2 border-gray-300 rounded px-3 py-2 w-48">
                <select x-model="blokFilter" @change="filterJudokas()" class="border-2 border-gray-300 rounded px-3 py-2 font-medium">
                    <option value="">Alle blokken</option>
                    @foreach($toernooi->blokken as $blok)
                    <option value="{{ $blok->nummer }}" data-gesloten="{{ $blok->weging_gesloten ? '1' : '0' }}">Blok {{ $blok->nummer }}</option>
                    @endforeach
                </select>
                <select x-model="statusFilter" @change="filterJudokas()" class="border-2 border-gray-300 rounded px-3 py-2">
                    <option value="">Alle status</option>
                    <option value="gewogen">Gewogen</option>
                    <option value="niet_gewogen">Niet gewogen</option>
                </select>

                <!-- Einde weegtijd knop - alleen zichtbaar als blok geselecteerd en niet gesloten -->
                <template x-if="blokFilter !== '' && !blokGesloten[blokFilter]">
                    <button type="button"
                            @click="sluitWeegtijd()"
                            class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-4 py-2 rounded">
                        Blok <span x-text="blokFilter"></span>: Einde weegtijd
                    </button>
                </template>
                <template x-if="blokFilter !== '' && blokGesloten[blokFilter]">
                    <span class="text-gray-500 px-4 py-2">Blok <span x-text="blokFilter"></span>: Gesloten</span>
                </template>
            </div>

            <!-- Stats per blok (vaste breedte, highlight actieve) -->
            <div class="flex items-center gap-4">
                @foreach($toernooi->blokken as $blok)
                <div class="text-center w-28 transition-opacity"
                     :class="blokFilter !== '' && blokFilter != '{{ $blok->nummer }}' ? 'opacity-40' : ''">
                    <div class="text-xs text-gray-500">Blok {{ $blok->nummer }}</div>
                    <div class="text-lg font-bold">
                        <span class="text-green-600" x-text="stats.blok{{ $blok->nummer }}?.gewogen || 0"></span>
                        <span class="text-gray-400">/</span>
                        <span class="text-gray-600" x-text="stats.blok{{ $blok->nummer }}?.totaal || 0"></span>
                    </div>
                    @if($blok->weging_gesloten)
                    <div class="text-xs text-gray-500">Gesloten</div>
                    @elseif($blok->weging_einde)
                    <div x-data="countdown('{{ $blok->weging_start?->toISOString() }}', '{{ $blok->weging_einde->toISOString() }}', {{ $blok->nummer }})" x-init="start()"
                         class="text-xs font-mono" :class="expired ? 'text-red-600 font-bold' : (warning ? 'text-yellow-600' : 'text-blue-600')"
                         x-text="display"></div>
                    @endif
                </div>
                @endforeach

                <div class="text-center border-l pl-4 w-20">
                    <div class="text-xs text-gray-500">Totaal</div>
                    <div class="text-xl font-bold">
                        <span class="text-green-600" x-text="stats.totaalGewogen"></span>
                        <span class="text-gray-400">/</span>
                        <span class="text-gray-600" x-text="stats.totaal"></span>
                    </div>
                    <div class="text-xs" :class="stats.percentage >= 100 ? 'text-green-600' : 'text-gray-500'"
                         x-text="stats.percentage + '%'"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Weeglijst tabel -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full table-fixed">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Naam</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Club</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Leeftijd</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Opgegeven</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blok</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gewogen</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tijd</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actie</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <template x-for="judoka in gefilterd" :key="judoka.id">
                    <tr class="hover:bg-gray-50" :class="judoka.gewogen ? '' : 'bg-yellow-50'">
                        <td class="px-4 py-3 font-medium" x-text="judoka.naam"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="judoka.club || '-'"></td>
                        <td class="px-4 py-3" x-text="judoka.leeftijdsklasse || '-'"></td>
                        <td class="px-4 py-3" x-text="(judoka.gewicht || judoka.gewichtsklasse || '-') + ' kg'"></td>
                        <td class="px-4 py-3" x-text="judoka.blok ? 'Blok ' + judoka.blok : '-'"></td>
                        <td class="px-4 py-3">
                            <span x-show="judoka.gewogen" class="font-bold" x-text="judoka.gewicht_gewogen + ' kg'"></span>
                            <span x-show="!judoka.gewogen" class="text-gray-400">-</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="judoka.gewogen_om || '-'"></td>
                        <td class="px-4 py-3">
                            <button @click="openEditGewicht(judoka)" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Wijzig
                            </button>
                        </td>
                    </tr>
                </template>
                <tr x-show="gefilterd.length === 0">
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        Geen judoka's gevonden
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Edit Gewicht Modal (binnen x-data scope) -->
    <div x-show="editModal" x-cloak class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm" @click.away="closeEditModal()">
            <div class="p-4 border-b">
                <h3 class="text-lg font-bold text-gray-800">Gewicht wijzigen</h3>
                <p class="text-sm text-gray-600">
                    <span x-text="editJudoka?.naam"></span>
                    <span class="text-blue-600 font-medium" x-show="editJudoka?.gewicht">
                        (opgegeven: <span x-text="editJudoka?.gewicht"></span> kg)
                    </span>
                </p>
            </div>
            <div class="p-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Gewogen gewicht (kg)</label>
                <input type="number" step="0.1" min="0" max="150" x-model="editGewicht"
                       class="w-full border-2 border-gray-300 rounded-lg px-3 py-2 text-lg font-bold text-center"
                       placeholder="0.0"
                       @keyup.enter="saveGewicht()">
                <p class="text-xs text-gray-500 mt-1">Tip: 0 = afmelden (kan niet deelnemen)</p>
            </div>
            <div class="p-4 border-t flex gap-2">
                <button @click="markeerAfwezig()" :disabled="editSaving"
                        class="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-gray-300 text-white font-bold py-2 px-4 rounded">
                    Afmelden
                </button>
                <button @click="saveGewicht()" :disabled="editSaving"
                        class="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white font-bold py-2 px-4 rounded">
                    <span x-show="!editSaving">Opslaan</span>
                    <span x-show="editSaving">Bezig...</span>
                </button>
            </div>
            <div class="p-4 border-t">
                <button @click="closeEditModal()" class="w-full text-gray-600 hover:text-gray-800 text-sm">
                    Annuleren
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Countdown timer - toont alleen na starttijd, met alert bij einde
function countdown(starttijd, eindtijd, blokNummer) {
    return {
        start_time: starttijd ? new Date(starttijd) : null,
        end: new Date(eindtijd),
        blok: blokNummer,
        display: '',
        expired: false,
        warning: false,
        alerted: false,
        interval: null,
        start() {
            this.update();
            this.interval = setInterval(() => this.update(), 1000);
        },
        update() {
            const now = new Date();
            // Toon niets als weging nog niet begonnen is
            if (this.start_time && now < this.start_time) {
                this.display = '';
                return;
            }
            const diff = this.end - now;
            if (diff <= 0) {
                this.expired = true;
                this.display = 'Voorbij!';
                if (!this.alerted) {
                    this.alerted = true;
                    toonWeegtijdAlert(this.blok);
                }
                clearInterval(this.interval);
                return;
            }
            this.warning = diff <= 5 * 60 * 1000;
            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            this.display = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }
    }
}

let weegtijdAlertGetoond = {};
function toonWeegtijdAlert(blokNummer) {
    if (weegtijdAlertGetoond[blokNummer]) return;
    weegtijdAlertGetoond[blokNummer] = true;
    // Browser notificatie (als toegestaan)
    if (Notification.permission === 'granted') {
        new Notification('Weegtijd voorbij!', { body: 'Blok ' + blokNummer + ' weegtijd is verstreken', icon: '/icon-192x192.png' });
    }
    // Alert popup
    alert('⏰ Weegtijd Blok ' + blokNummer + ' is voorbij!');
}

function weeglijst() {
    return {
        judokas: @json($judokas),
        gefilterd: [],
        zoekterm: '',
        blokFilter: '',
        statusFilter: '',
        blokGesloten: {
            @foreach($toernooi->blokken as $blok)
            {{ $blok->nummer }}: {{ $blok->weging_gesloten ? 'true' : 'false' }},
            @endforeach
        },
        blokIds: {
            @foreach($toernooi->blokken as $blok)
            {{ $blok->nummer }}: {{ $blok->id }},
            @endforeach
        },
        stats: {
            totaal: 0,
            totaalGewogen: 0,
            percentage: 0,
            @foreach($toernooi->blokken as $blok)
            blok{{ $blok->nummer }}: { totaal: 0, gewogen: 0 },
            @endforeach
        },

        init() {
            this.berekenStats();
            this.filterJudokas();

            // Auto-refresh elke 10 seconden
            setInterval(() => this.refresh(), 10000);
        },

        sluitWeegtijd() {
            if (!this.blokFilter) return;
            if (!confirm('Weegtijd Blok ' + this.blokFilter + ' sluiten? Niet-gewogen judoka\'s worden als afwezig gemarkeerd.')) return;

            const blokId = this.blokIds[this.blokFilter];
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ url()->current() }}'.replace('/weging/interface', '/blok/' + blokId + '/sluit-weging');

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            document.body.appendChild(form);
            form.submit();
        },

        berekenStats() {
            this.stats.totaal = this.judokas.length;
            this.stats.totaalGewogen = this.judokas.filter(j => j.gewogen).length;
            this.stats.percentage = this.stats.totaal > 0
                ? Math.round((this.stats.totaalGewogen / this.stats.totaal) * 100)
                : 0;

            // Per blok
            @foreach($toernooi->blokken as $blok)
            const blok{{ $blok->nummer }} = this.judokas.filter(j => j.blok == {{ $blok->nummer }});
            this.stats.blok{{ $blok->nummer }} = {
                totaal: blok{{ $blok->nummer }}.length,
                gewogen: blok{{ $blok->nummer }}.filter(j => j.gewogen).length
            };
            @endforeach
        },

        filterJudokas() {
            let result = [...this.judokas];

            // Zoeken op naam of club
            if (this.zoekterm.length >= 2) {
                const zoek = this.zoekterm.toLowerCase();
                result = result.filter(j =>
                    j.naam.toLowerCase().includes(zoek) ||
                    (j.club && j.club.toLowerCase().includes(zoek))
                );
            }

            if (this.blokFilter) {
                result = result.filter(j => j.blok == this.blokFilter);
            }

            if (this.statusFilter === 'gewogen') {
                result = result.filter(j => j.gewogen);
            } else if (this.statusFilter === 'niet_gewogen') {
                result = result.filter(j => !j.gewogen);
            }

            // Sorteer: niet gewogen eerst, dan op naam
            result.sort((a, b) => {
                if (a.gewogen !== b.gewogen) return a.gewogen ? 1 : -1;
                return a.naam.localeCompare(b.naam);
            });

            this.gefilterd = result;
        },

        async refresh() {
            try {
                const response = await fetch('{{ route('toernooi.weging.lijst-json', $toernooi->routeParams()) }}');
                const data = await response.json();
                this.judokas = data;
                this.berekenStats();
                this.filterJudokas();
            } catch (err) {
                console.error('Refresh failed:', err);
            }
        },

        // Edit gewicht modal
        editModal: false,
        editJudoka: null,
        editGewicht: '',
        editSaving: false,

        openEditGewicht(judoka) {
            this.editJudoka = judoka;
            this.editGewicht = judoka.gewicht_gewogen || '';
            this.editModal = true;
        },

        closeEditModal() {
            this.editModal = false;
            this.editJudoka = null;
            this.editGewicht = '';
        },

        async saveGewicht() {
            if (!this.editJudoka) return;

            const gewicht = parseFloat(this.editGewicht) || 0;
            this.editSaving = true;

            try {
                const response = await fetch(`{{ url()->current() }}`.replace('/weging/interface', `/weging/${this.editJudoka.id}/registreer`), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ gewicht: gewicht })
                });

                const data = await response.json();

                if (data.success) {
                    // Update lokale data
                    const idx = this.judokas.findIndex(j => j.id === this.editJudoka.id);
                    if (idx !== -1) {
                        if (data.afwezig) {
                            // Verwijder uit lijst (is nu afwezig)
                            this.judokas.splice(idx, 1);
                        } else {
                            this.judokas[idx].gewogen = true;
                            this.judokas[idx].gewicht_gewogen = gewicht;
                        }
                    }
                    this.berekenStats();
                    this.filterJudokas();
                    this.closeEditModal();
                } else {
                    alert(data.message || 'Fout bij opslaan');
                }
            } catch (err) {
                console.error('Save failed:', err);
                alert('Fout bij opslaan');
            } finally {
                this.editSaving = false;
            }
        },

        async markeerAfwezig() {
            this.editGewicht = '0';
            await this.saveGewicht();
        }
    }
}
</script>
@endsection
