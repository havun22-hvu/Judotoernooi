@extends('layouts.app')

@section('title', __('Weeglijst Live'))

@section('content')
<div class="flex justify-between items-center mb-4">
    <h1 class="text-2xl font-bold text-gray-800">⚖️ {{ __('Weeglijst Live') }}</h1>
    <div class="text-sm text-gray-500">{{ __('Auto-refresh 10s') }}</div>
</div>

<div x-data="weeglijst()" x-init="init()">
    <!-- Filters en stats -->
    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <input type="text" x-model="zoekterm" @input="filterJudokas()"
                       placeholder="{{ __('Zoek naam of club...') }}"
                       class="border-2 border-gray-300 rounded px-3 py-2 w-48">
                <select x-model="blokFilter" @change="filterJudokas()" class="border-2 border-gray-300 rounded px-3 py-2 font-medium">
                    <option value="">{{ __('Alle blokken') }}</option>
                    @foreach($toernooi->blokken as $blok)
                    <option value="{{ $blok->nummer }}" data-gesloten="{{ $blok->weging_gesloten ? '1' : '0' }}">{{ __('Blok') }} {{ $blok->nummer }}</option>
                    @endforeach
                </select>
                <select x-model="statusFilter" @change="filterJudokas()" class="border-2 border-gray-300 rounded px-3 py-2">
                    <option value="">{{ __('Alle status') }}</option>
                    <option value="gewogen">{{ __('Gewogen') }}</option>
                    <option value="niet_gewogen">{{ __('Niet gewogen') }}</option>
                    <option value="afwezig">{{ __('Afwezig') }}</option>
                </select>

                <!-- Einde weegtijd knop - alleen zichtbaar als blok geselecteerd en niet gesloten -->
                <template x-if="blokFilter !== '' && !blokGesloten[blokFilter]">
                    <button type="button"
                            @click="sluitWeegtijd()"
                            class="bg-orange-500 hover:bg-orange-600 text-white font-bold px-4 py-2 rounded">
                        {{ __('Blok') }} <span x-text="blokFilter"></span>: {{ __('Einde weegtijd') }}
                    </button>
                </template>
                <template x-if="blokFilter !== '' && blokGesloten[blokFilter]">
                    <span class="text-gray-500 px-4 py-2">{{ __('Blok') }} <span x-text="blokFilter"></span>: {{ __('Gesloten') }}</span>
                </template>
            </div>

            <!-- Stats per blok (vaste breedte, highlight actieve) -->
            <div class="flex items-center gap-4">
                @foreach($toernooi->blokken as $blok)
                <div class="text-center w-28 transition-opacity"
                     :class="blokFilter !== '' && blokFilter != '{{ $blok->nummer }}' ? 'opacity-40' : ''">
                    <div class="text-xs text-gray-500">{{ __('Blok') }} {{ $blok->nummer }}</div>
                    <div class="text-lg font-bold">
                        <span class="text-green-600" x-text="stats.blok{{ $blok->nummer }}?.gewogen || 0"></span>
                        <span class="text-gray-400">/</span>
                        <span class="text-gray-600" x-text="stats.blok{{ $blok->nummer }}?.totaal || 0"></span>
                    </div>
                    @if($blok->weging_gesloten)
                    <div class="text-xs text-gray-500">{{ __('Gesloten') }}</div>
                    @elseif($blok->weging_einde)
                    <div x-data="countdown('{{ $blok->weging_start?->toISOString() }}', '{{ $blok->weging_einde->toISOString() }}', {{ $blok->nummer }})" x-init="start()"
                         class="text-xs font-mono" :class="expired ? 'text-red-600 font-bold' : (warning ? 'text-yellow-600' : 'text-blue-600')"
                         x-text="display"></div>
                    @endif
                </div>
                @endforeach

                <div class="text-center border-l pl-4 w-20">
                    <div class="text-xs text-gray-500">{{ __('Totaal') }}</div>
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
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Naam') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Club') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Leeftijd') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Opgegeven') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Blok') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Gewogen') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Tijd') }}</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Actie') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <template x-for="judoka in gefilterd" :key="judoka.id">
                    <tr class="hover:bg-gray-50"
                        :class="judoka.afwezig ? 'bg-red-50 opacity-60' : (judoka.gewogen ? '' : 'bg-yellow-50')">
                        <td class="px-4 py-3 font-medium" :class="judoka.afwezig ? 'line-through text-gray-500' : ''">
                            <span x-text="judoka.naam"></span>
                            <span x-show="judoka.afwezig" class="ml-2 text-xs bg-red-200 text-red-800 px-1.5 py-0.5 rounded no-underline inline-block">AFWEZIG</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600" x-text="judoka.club || '-'"></td>
                        <td class="px-4 py-3" x-text="judoka.leeftijdsklasse || '-'"></td>
                        <td class="px-4 py-3" x-text="(judoka.gewicht || judoka.gewichtsklasse || '-') + ' kg'"></td>
                        <td class="px-4 py-3" x-text="judoka.blok ? 'Blok ' + judoka.blok : '-'"></td>
                        <td class="px-4 py-3">
                            <span x-show="judoka.afwezig" class="text-red-600 font-medium">-</span>
                            <span x-show="!judoka.afwezig && judoka.gewogen" class="font-bold" x-text="judoka.gewicht_gewogen + ' kg'"></span>
                            <span x-show="!judoka.afwezig && !judoka.gewogen" class="text-gray-400">-</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500" x-text="judoka.gewogen_om || '-'"></td>
                        <td class="px-4 py-3">
                            <button @click="openEditGewicht(judoka)" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                <span x-text="judoka.afwezig ? 'Herstel' : 'Wijzig'"></span>
                            </button>
                        </td>
                    </tr>
                </template>
                <tr x-show="gefilterd.length === 0">
                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                        {{ __("Geen judoka's gevonden") }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Edit Gewicht Modal (binnen x-data scope) - Draggable -->
    <div x-show="editModal" x-cloak class="fixed inset-0 z-50 pointer-events-none">
        <div class="absolute bg-white rounded-lg shadow-xl w-80 pointer-events-auto"
             x-ref="editModalBox"
             :style="'left:' + modalX + 'px; top:' + modalY + 'px;'">
            <div class="p-4 border-b cursor-move select-none bg-gray-50 rounded-t-lg"
                 @mousedown="startDrag($event)"
                 @touchstart.prevent="startDrag($event)">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-800">{{ __('Gewicht wijzigen') }}</h3>
                    <button @click="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                </div>
                <p class="text-sm text-gray-600">
                    <span x-text="editJudoka?.naam"></span>
                    <template x-if="editJudoka?.gewicht && parseFloat(editJudoka.gewicht) > 0">
                        <span class="text-blue-600 font-medium">
                            (opgegeven: <span x-text="editJudoka.gewicht"></span> kg)
                        </span>
                    </template>
                </p>
            </div>
            <div class="p-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Gewogen gewicht') }} (kg)</label>
                <input type="number" step="0.1" min="0" max="150" x-model="editGewicht"
                       class="w-full border-2 border-gray-300 rounded-lg px-3 py-2 text-lg font-bold text-center"
                       placeholder="0.0"
                       @keyup.enter="saveGewicht()">
                <p class="text-xs text-gray-500 mt-1">{{ __('Tip: 0 = afmelden (kan niet deelnemen)') }}</p>
            </div>
            <div class="p-4 border-t flex gap-2">
                <button @click="markeerAfwezig()" :disabled="editSaving"
                        class="flex-1 bg-red-600 hover:bg-red-700 disabled:bg-gray-300 text-white font-bold py-2 px-4 rounded">
                    {{ __('Afmelden (kan niet deelnemen)') }}
                </button>
                <button @click="saveGewicht()" :disabled="editSaving"
                        class="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white font-bold py-2 px-4 rounded">
                    <span x-show="!editSaving">{{ __('Opslaan') }}</span>
                    <span x-show="editSaving">{{ __('Bezig...') }}</span>
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
                result = result.filter(j => j.gewogen && !j.afwezig);
            } else if (this.statusFilter === 'niet_gewogen') {
                result = result.filter(j => !j.gewogen && !j.afwezig);
            } else if (this.statusFilter === 'afwezig') {
                result = result.filter(j => j.afwezig);
            }

            // Sorteer: afwezig onderaan, dan niet gewogen eerst, dan op naam
            result.sort((a, b) => {
                // Afwezig altijd onderaan
                if (a.afwezig !== b.afwezig) return a.afwezig ? 1 : -1;
                // Gewogen onderaan (niet gewogen eerst)
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

        // Edit gewicht modal (draggable)
        editModal: false,
        editJudoka: null,
        editGewicht: '',
        editSaving: false,
        modalX: 100,
        modalY: 100,
        isDragging: false,
        dragOffsetX: 0,
        dragOffsetY: 0,

        openEditGewicht(judoka) {
            this.editJudoka = judoka;
            this.editGewicht = judoka.gewicht_gewogen || '';
            // Center modal on first open
            this.modalX = Math.max(50, (window.innerWidth - 320) / 2);
            this.modalY = Math.max(50, (window.innerHeight - 300) / 2);
            this.editModal = true;
        },

        closeEditModal() {
            this.editModal = false;
            this.editJudoka = null;
            this.editGewicht = '';
        },

        startDrag(e) {
            this.isDragging = true;
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            this.dragOffsetX = clientX - this.modalX;
            this.dragOffsetY = clientY - this.modalY;

            const moveHandler = (ev) => {
                if (!this.isDragging) return;
                const cx = ev.touches ? ev.touches[0].clientX : ev.clientX;
                const cy = ev.touches ? ev.touches[0].clientY : ev.clientY;
                this.modalX = Math.max(0, Math.min(window.innerWidth - 320, cx - this.dragOffsetX));
                this.modalY = Math.max(0, Math.min(window.innerHeight - 100, cy - this.dragOffsetY));
            };

            const upHandler = () => {
                this.isDragging = false;
                document.removeEventListener('mousemove', moveHandler);
                document.removeEventListener('mouseup', upHandler);
                document.removeEventListener('touchmove', moveHandler);
                document.removeEventListener('touchend', upHandler);
            };

            document.addEventListener('mousemove', moveHandler);
            document.addEventListener('mouseup', upHandler);
            document.addEventListener('touchmove', moveHandler);
            document.addEventListener('touchend', upHandler);
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
                            // Markeer als afwezig (blijft in lijst zichtbaar)
                            this.judokas[idx].afwezig = true;
                            this.judokas[idx].gewogen = false;
                            this.judokas[idx].gewicht_gewogen = null;
                        } else {
                            this.judokas[idx].gewogen = true;
                            this.judokas[idx].gewicht_gewogen = gewicht;
                            this.judokas[idx].afwezig = false;
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
