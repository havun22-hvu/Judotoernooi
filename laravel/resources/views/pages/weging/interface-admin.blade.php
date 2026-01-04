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
                    <option value="{{ $blok->nummer }}">Blok {{ $blok->nummer }}</option>
                    @endforeach
                </select>
                <select x-model="statusFilter" @change="filterJudokas()" class="border-2 border-gray-300 rounded px-3 py-2">
                    <option value="">Alle status</option>
                    <option value="gewogen">Gewogen</option>
                    <option value="niet_gewogen">Niet gewogen</option>
                </select>
            </div>

            <!-- Stats per blok (vaste breedte, highlight actieve) -->
            <div class="flex items-center gap-4">
                @foreach($toernooi->blokken as $blok)
                <div class="text-center w-20 transition-opacity"
                     :class="blokFilter !== '' && blokFilter != '{{ $blok->nummer }}' ? 'opacity-40' : ''">
                    <div class="text-xs text-gray-500">Blok {{ $blok->nummer }}</div>
                    <div class="text-lg font-bold">
                        <span class="text-green-600" x-text="stats.blok{{ $blok->nummer }}?.gewogen || 0"></span>
                        <span class="text-gray-400">/</span>
                        <span class="text-gray-600" x-text="stats.blok{{ $blok->nummer }}?.totaal || 0"></span>
                    </div>
                    @if($blok->weging_einde && !$blok->weging_gesloten)
                    <div x-data="countdown('{{ $blok->weging_einde->toISOString() }}')" x-init="start()"
                         class="text-xs font-mono" :class="expired ? 'text-red-600' : (warning ? 'text-yellow-600' : 'text-blue-600')"
                         x-text="display"></div>
                    @elseif($blok->weging_gesloten)
                    <div class="text-xs text-gray-500">Gesloten</div>
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gewicht</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Blok</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gewogen</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tijd</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <template x-for="judoka in gefilterd" :key="judoka.id">
                    <tr class="hover:bg-gray-50" :class="judoka.gewogen ? '' : 'bg-yellow-50'">
                        <td class="px-4 py-3 font-medium" x-text="judoka.naam"></td>
                        <td class="px-4 py-3 text-gray-600" x-text="judoka.club || '-'"></td>
                        <td class="px-4 py-3" x-text="judoka.leeftijdsklasse || '-'"></td>
                        <td class="px-4 py-3" x-text="judoka.gewichtsklasse + ' kg'"></td>
                        <td class="px-4 py-3" x-text="judoka.blok ? 'Blok ' + judoka.blok : '-'"></td>
                        <td class="px-4 py-3">
                            <span x-show="judoka.gewogen" class="font-bold" x-text="judoka.gewicht_gewogen + ' kg'"></span>
                            <span x-show="!judoka.gewogen" class="text-gray-400">-</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="judoka.gewogen_om || '-'"></td>
                    </tr>
                </template>
                <tr x-show="gefilterd.length === 0">
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                        Geen judoka's gevonden
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
// Countdown timer
function countdown(eindtijd) {
    return {
        end: new Date(eindtijd),
        display: '',
        expired: false,
        warning: false,
        interval: null,
        start() {
            this.update();
            this.interval = setInterval(() => this.update(), 1000);
        },
        update() {
            const diff = this.end - new Date();
            if (diff <= 0) {
                this.expired = true;
                this.display = 'Voorbij!';
                clearInterval(this.interval);
                return;
            }
            this.warning = diff <= 5 * 60 * 1000;
            const m = Math.floor(diff / 60000);
            const s = Math.floor((diff % 60000) / 1000);
            this.display = `${m}:${String(s).padStart(2, '0')}`;
        }
    }
}

function weeglijst() {
    return {
        judokas: @json($judokas),
        gefilterd: [],
        zoekterm: '',
        blokFilter: '',
        statusFilter: '',
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
                const response = await fetch('{{ route('toernooi.weging.lijst-json', $toernooi) }}');
                const data = await response.json();
                this.judokas = data;
                this.berekenStats();
                this.filterJudokas();
            } catch (err) {
                console.error('Refresh failed:', err);
            }
        }
    }
}
</script>
@endsection
