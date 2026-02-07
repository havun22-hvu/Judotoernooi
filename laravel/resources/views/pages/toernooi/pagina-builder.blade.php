@extends('layouts.app')

@section('title', __('Pagina Builder'))

@push('styles')
<link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<style>
    trix-toolbar [data-trix-button-group="file-tools"] { display: none; }
    trix-editor { min-height: 150px; }
    .drag-handle { cursor: grab; }
    .drag-handle:active { cursor: grabbing; }
    .sortable-ghost { opacity: 0.4; background: #e5e7eb; }
</style>
@endpush

@section('content')
<div class="max-w-5xl mx-auto" x-data="paginaBuilder()">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-800">{{ __('Pagina Builder') }}</h1>
            <span x-show="saving" class="text-sm text-blue-600">{{ __('Opslaan...') }}</span>
            <span x-show="saved" x-transition class="text-sm text-green-600">{{ __('Opgeslagen!') }}</span>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('publiek.index', $toernooi->routeParams()) }}" target="_blank"
               class="px-4 py-2 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                {{ __('Preview') }}
            </a>
            <a href="{{ route('toernooi.edit', $toernooi->routeParams()) }}" class="text-blue-600 hover:text-blue-800">
                &larr; {{ __('Terug naar Instellingen') }}
            </a>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <p class="text-sm text-gray-600 mb-3">{{ __('Voeg blokken toe aan je publieke toernooi pagina:') }}</p>
        <div class="flex flex-wrap gap-2">
            <button @click="addBlok('header')" type="button"
                    class="px-4 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                {{ __('Header') }}
            </button>
            <button @click="addBlok('tekst')" type="button"
                    class="px-4 py-2 bg-green-100 text-green-700 rounded hover:bg-green-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
                </svg>
                {{ __('Tekst') }}
            </button>
            <button @click="addBlok('afbeelding')" type="button"
                    class="px-4 py-2 bg-purple-100 text-purple-700 rounded hover:bg-purple-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                {{ __('Afbeelding') }}
            </button>
            <button @click="addBlok('sponsors')" type="button"
                    class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                {{ __('Sponsors') }}
            </button>
            <button @click="addBlok('video')" type="button"
                    class="px-4 py-2 bg-red-100 text-red-700 rounded hover:bg-red-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ __('Video') }}
            </button>
            <button @click="addBlok('info_kaart')" type="button"
                    class="px-4 py-2 bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ __('Info Kaart') }}
            </button>
        </div>
    </div>

    <!-- Blokken Container -->
    <div id="blokken-container" class="space-y-4 min-h-[200px]">
        <template x-for="blok in blokken" :key="blok.id">
            <div class="bg-white rounded-lg shadow overflow-hidden" :data-id="blok.id">
                <!-- Blok Header -->
                <div class="bg-gray-100 px-4 py-3 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <span class="drag-handle text-gray-400 cursor-grab">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"></path>
                            </svg>
                        </span>
                        <span class="font-medium text-gray-700" x-text="getBlokLabel(blok.type)"></span>
                    </div>
                    <button @click="removeBlok(blok.id)" type="button" class="text-red-500 hover:text-red-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>

                <!-- Blok Content -->
                <div class="p-4">
                    <!-- Header Blok -->
                    <template x-if="blok.type === 'header'">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Logo') }}</label>
                                <div class="flex items-center gap-4">
                                    <template x-if="blok.data.logo">
                                        <img :src="'/storage/' + blok.data.logo" class="h-20 object-contain rounded border">
                                    </template>
                                    <input type="file" @change="uploadAfbeelding($event, blok, 'logo')" accept="image/*"
                                           class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Titel') }}</label>
                                <input type="text" x-model="blok.data.titel" @input.debounce.500ms="saveBlokken()"
                                       class="w-full border rounded px-3 py-2" placeholder="{{ __('Titel van het toernooi') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Subtitel') }}</label>
                                <input type="text" x-model="blok.data.subtitel" @input.debounce.500ms="saveBlokken()"
                                       class="w-full border rounded px-3 py-2" placeholder="{{ __('Welkomstbericht of slogan') }}">
                            </div>
                        </div>
                    </template>

                    <!-- Tekst Blok -->
                    <template x-if="blok.type === 'tekst'">
                        <div>
                            <input :id="'trix-input-' + blok.id" type="hidden" x-model="blok.data.html">
                            <trix-editor :input="'trix-input-' + blok.id"
                                         @trix-change="blok.data.html = $event.target.value; debouncedSave()"
                                         class="prose max-w-none"></trix-editor>
                        </div>
                    </template>

                    <!-- Afbeelding Blok -->
                    <template x-if="blok.type === 'afbeelding'">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Afbeelding') }}</label>
                                <template x-if="blok.data.src">
                                    <img :src="'/storage/' + blok.data.src" class="max-h-48 object-contain rounded border mb-2">
                                </template>
                                <input type="file" @change="uploadAfbeelding($event, blok, 'src')" accept="image/*"
                                       class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Onderschrift') }}</label>
                                <input type="text" x-model="blok.data.caption" @input.debounce.500ms="saveBlokken()"
                                       class="w-full border rounded px-3 py-2" placeholder="{{ __('Optioneel onderschrift') }}">
                            </div>
                        </div>
                    </template>

                    <!-- Sponsors Blok -->
                    <template x-if="blok.type === 'sponsors'">
                        <div class="space-y-4">
                            <template x-for="(sponsor, index) in blok.data.sponsors || []" :key="index">
                                <div class="flex items-center gap-4 p-3 bg-gray-50 rounded">
                                    <template x-if="sponsor.logo">
                                        <img :src="'/storage/' + sponsor.logo" class="h-12 object-contain">
                                    </template>
                                    <input type="file" @change="uploadSponsorLogo($event, blok, index)" accept="image/*"
                                           class="text-sm flex-shrink-0">
                                    <input type="text" x-model="sponsor.naam" @input.debounce.500ms="saveBlokken()"
                                           class="flex-1 border rounded px-2 py-1 text-sm" placeholder="{{ __('Naam') }}">
                                    <input type="url" x-model="sponsor.url" @input.debounce.500ms="saveBlokken()"
                                           class="flex-1 border rounded px-2 py-1 text-sm" placeholder="{{ __('Website URL') }}">
                                    <button @click="removeSponsor(blok, index)" type="button" class="text-red-500 hover:text-red-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                            <button @click="addSponsor(blok)" type="button"
                                    class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded hover:bg-yellow-200 text-sm">
                                {{ __('+ Sponsor toevoegen') }}
                            </button>
                        </div>
                    </template>

                    <!-- Video Blok -->
                    <template x-if="blok.type === 'video'">
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Video URL (YouTube of Vimeo)') }}</label>
                                <input type="url" x-model="blok.data.url" @input.debounce.500ms="saveBlokken()"
                                       class="w-full border rounded px-3 py-2" placeholder="https://www.youtube.com/watch?v=...">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Titel') }}</label>
                                <input type="text" x-model="blok.data.titel" @input.debounce.500ms="saveBlokken()"
                                       class="w-full border rounded px-3 py-2" placeholder="{{ __('Optionele titel') }}">
                            </div>
                        </div>
                    </template>

                    <!-- Info Kaart Blok -->
                    <template x-if="blok.type === 'info_kaart'">
                        <div class="bg-blue-50 p-4 rounded text-blue-800">
                            <p class="text-sm">{{ __('Dit blok toont automatisch de toernooi informatie (datum, locatie, tijdschema) uit de instellingen.') }}</p>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- Empty State -->
        <div x-show="blokken.length === 0" class="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="text-gray-500">{{ __('Nog geen blokken. Klik hierboven om te beginnen.') }}</p>
        </div>
    </div>

    <!-- Save Button (mobile) -->
    <div class="fixed bottom-4 right-4 md:hidden">
        <button @click="saveBlokken()" type="button"
                class="px-6 py-3 bg-blue-600 text-white rounded-full shadow-lg hover:bg-blue-700">
            {{ __('Opslaan') }}
        </button>
    </div>
</div>

<script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function paginaBuilder() {
    return {
        blokken: @json($blokken),
        saving: false,
        saved: false,
        saveTimeout: null,

        init() {
            this.$nextTick(() => {
                this.initSortable();
            });
        },

        initSortable() {
            const container = document.getElementById('blokken-container');
            if (!container) return;

            new Sortable(container, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                onEnd: () => {
                    this.updateOrder();
                    this.saveBlokken();
                }
            });
        },

        updateOrder() {
            const container = document.getElementById('blokken-container');
            const items = container.querySelectorAll('[data-id]');
            const newOrder = [];

            items.forEach((item, index) => {
                const id = item.getAttribute('data-id');
                const blok = this.blokken.find(b => b.id === id);
                if (blok) {
                    blok.order = index;
                    newOrder.push(blok);
                }
            });

            this.blokken = newOrder;
        },

        generateId() {
            return 'blok-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        },

        addBlok(type) {
            const defaultData = {
                header: { logo: null, titel: '', subtitel: '' },
                tekst: { html: '' },
                afbeelding: { src: null, alt: '', caption: '' },
                sponsors: { sponsors: [] },
                video: { url: '', titel: '' },
                info_kaart: {}
            };

            this.blokken.push({
                id: this.generateId(),
                type: type,
                order: this.blokken.length,
                data: defaultData[type] || {}
            });

            this.$nextTick(() => {
                this.initSortable();
            });

            this.saveBlokken();
        },

        removeBlok(id) {
            if (confirm(@json(__('Weet je zeker dat je dit blok wilt verwijderen?')))) {
                this.blokken = this.blokken.filter(b => b.id !== id);
                this.saveBlokken();
            }
        },

        getBlokLabel(type) {
            const labels = {
                header: @json(__('Header')),
                tekst: @json(__('Tekst')),
                afbeelding: @json(__('Afbeelding')),
                sponsors: @json(__('Sponsors')),
                video: @json(__('Video')),
                info_kaart: @json(__('Info Kaart'))
            };
            return labels[type] || type;
        },

        addSponsor(blok) {
            if (!blok.data.sponsors) blok.data.sponsors = [];
            blok.data.sponsors.push({ naam: '', logo: null, url: '' });
        },

        removeSponsor(blok, index) {
            blok.data.sponsors.splice(index, 1);
            this.saveBlokken();
        },

        async uploadAfbeelding(event, blok, field) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('afbeelding', file);

            try {
                const response = await fetch('{{ route("toernooi.pagina-builder.upload", $toernooi->routeParams()) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    blok.data[field] = data.path;
                    this.saveBlokken();
                }
            } catch (error) {
                console.error('Upload failed:', error);
                alert(@json(__('Upload mislukt. Probeer opnieuw.')));
            }
        },

        async uploadSponsorLogo(event, blok, index) {
            const file = event.target.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('afbeelding', file);

            try {
                const response = await fetch('{{ route("toernooi.pagina-builder.upload", $toernooi->routeParams()) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    blok.data.sponsors[index].logo = data.path;
                    this.saveBlokken();
                }
            } catch (error) {
                console.error('Upload failed:', error);
                alert(@json(__('Upload mislukt. Probeer opnieuw.')));
            }
        },

        debouncedSave() {
            if (this.saveTimeout) clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => this.saveBlokken(), 500);
        },

        async saveBlokken() {
            this.saving = true;
            this.saved = false;

            try {
                const response = await fetch('{{ route("toernooi.pagina-builder.opslaan", $toernooi->routeParams()) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ blokken: this.blokken })
                });

                const data = await response.json();
                if (data.success) {
                    this.saved = true;
                    setTimeout(() => this.saved = false, 2000);
                }
            } catch (error) {
                console.error('Save failed:', error);
                alert(@json(__('Opslaan mislukt. Probeer opnieuw.')));
            }

            this.saving = false;
        }
    }
}
</script>
@endsection
