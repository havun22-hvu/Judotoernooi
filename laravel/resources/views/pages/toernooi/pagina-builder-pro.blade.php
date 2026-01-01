@extends('layouts.app')

@section('title', 'Pagina Builder')

@push('styles')
<link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.0.0/dist/trix.css">
<style>
    :root {
        --primary: {{ $toernooi->thema_kleur ?? '#2563eb' }};
        --primary-light: {{ $toernooi->thema_kleur ?? '#2563eb' }}20;
    }

    /* Trix editor */
    trix-toolbar [data-trix-button-group="file-tools"] { display: none; }
    trix-editor { min-height: 100px; }

    /* Drag & Drop */
    .drag-handle { cursor: grab; }
    .drag-handle:active { cursor: grabbing; }
    .sortable-ghost { opacity: 0.4; background: #e5e7eb; }
    .sortable-chosen { box-shadow: 0 10px 25px rgba(0,0,0,0.15); }

    /* Builder Layout */
    .builder-sidebar {
        width: 320px;
        transition: transform 0.3s ease;
    }
    .builder-canvas {
        background: repeating-linear-gradient(
            45deg,
            #f8fafc,
            #f8fafc 10px,
            #f1f5f9 10px,
            #f1f5f9 20px
        );
    }

    /* Section styling */
    .section-wrapper {
        position: relative;
        transition: all 0.2s ease;
    }
    .section-wrapper:hover .section-controls {
        opacity: 1;
    }
    .section-controls {
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    /* Column indicators */
    .col-indicator {
        border: 2px dashed #cbd5e1;
        min-height: 120px;
        transition: all 0.2s ease;
        background: rgba(241, 245, 249, 0.5);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .col-indicator:hover {
        border-color: var(--primary);
        background: var(--primary-light);
    }
    .col-indicator.has-content {
        border: 2px dashed transparent;
        background: transparent;
        justify-content: flex-start;
    }
    .col-indicator.has-content:hover {
        border-color: #cbd5e1;
    }

    /* Block palette */
    .block-palette-item {
        transition: all 0.2s ease;
    }
    .block-palette-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    /* Preview modes */
    .preview-desktop { max-width: 100%; }
    .preview-tablet { max-width: 768px; margin: 0 auto; }
    .preview-mobile { max-width: 375px; margin: 0 auto; }

    /* Settings panel animations */
    .settings-panel {
        transition: all 0.3s ease;
    }
</style>
@endpush

@section('content')
<div x-data="paginaBuilderPro()" x-init="init()" class="h-screen flex flex-col bg-gray-100">

    <!-- Top Toolbar -->
    <div class="bg-white border-b px-4 py-2 flex items-center justify-between shadow-sm z-20">
        <div class="flex items-center gap-4">
            <a href="{{ route('toernooi.edit', $toernooi) }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <h1 class="text-lg font-semibold text-gray-800">Pagina Builder</h1>
            <span x-show="saving" class="text-sm text-blue-600 flex items-center gap-1">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Opslaan...
            </span>
            <span x-show="saved" x-transition class="text-sm text-green-600">✓ Opgeslagen</span>
        </div>

        <div class="flex items-center gap-2">
            <!-- Device Preview Toggle -->
            <div class="flex items-center bg-gray-100 rounded-lg p-1">
                <button @click="previewMode = 'desktop'"
                        :class="previewMode === 'desktop' ? 'bg-white shadow text-blue-600' : 'text-gray-500'"
                        class="p-2 rounded transition-all" title="Desktop">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </button>
                <button @click="previewMode = 'tablet'"
                        :class="previewMode === 'tablet' ? 'bg-white shadow text-blue-600' : 'text-gray-500'"
                        class="p-2 rounded transition-all" title="Tablet">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </button>
                <button @click="previewMode = 'mobile'"
                        :class="previewMode === 'mobile' ? 'bg-white shadow text-blue-600' : 'text-gray-500'"
                        class="p-2 rounded transition-all" title="Mobile">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                </button>
            </div>

            <div class="h-6 w-px bg-gray-300"></div>

            <!-- Theme Color -->
            <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600">Thema:</label>
                <input type="color" x-model="themeColor" @change="saveSettings()"
                       class="w-8 h-8 rounded cursor-pointer border-0">
            </div>

            <div class="h-6 w-px bg-gray-300"></div>

            <a href="{{ route('publiek.index', $toernooi) }}" target="_blank"
               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Preview
            </a>
        </div>
    </div>

    <!-- Main Builder Area -->
    <div class="flex-1 flex overflow-hidden">

        <!-- Left Sidebar - Block Palette -->
        <div class="builder-sidebar bg-white border-r flex flex-col overflow-hidden">
            <!-- Tabs -->
            <div class="flex border-b">
                <button @click="sidebarTab = 'blocks'"
                        :class="sidebarTab === 'blocks' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500'"
                        class="flex-1 py-3 text-sm font-medium">
                    Blokken
                </button>
                <button @click="sidebarTab = 'sections'"
                        :class="sidebarTab === 'sections' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500'"
                        class="flex-1 py-3 text-sm font-medium">
                    Secties
                </button>
                <button @click="sidebarTab = 'templates'"
                        :class="sidebarTab === 'templates' ? 'border-b-2 border-blue-600 text-blue-600' : 'text-gray-500'"
                        class="flex-1 py-3 text-sm font-medium">
                    Templates
                </button>
            </div>

            <!-- Blocks Panel -->
            <div x-show="sidebarTab === 'blocks'" class="flex-1 overflow-y-auto p-4">
                <div class="space-y-4">
                    <!-- Basic Blocks -->
                    <div>
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Basis</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="addBlock('heading')" class="block-palette-item p-3 bg-gray-50 rounded-lg text-center hover:bg-gray-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8m-8 6h16"/>
                                </svg>
                                <span class="text-xs text-gray-600">Kop</span>
                            </button>
                            <button @click="addBlock('text')" class="block-palette-item p-3 bg-gray-50 rounded-lg text-center hover:bg-gray-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                                </svg>
                                <span class="text-xs text-gray-600">Tekst</span>
                            </button>
                            <button @click="addBlock('image')" class="block-palette-item p-3 bg-gray-50 rounded-lg text-center hover:bg-gray-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-xs text-gray-600">Afbeelding</span>
                            </button>
                            <button @click="addBlock('button')" class="block-palette-item p-3 bg-gray-50 rounded-lg text-center hover:bg-gray-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
                                </svg>
                                <span class="text-xs text-gray-600">Button</span>
                            </button>
                            <button @click="addBlock('video')" class="block-palette-item p-3 bg-gray-50 rounded-lg text-center hover:bg-gray-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs text-gray-600">Video</span>
                            </button>
                            <button @click="addBlock('divider')" class="block-palette-item p-3 bg-gray-50 rounded-lg text-center hover:bg-gray-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                </svg>
                                <span class="text-xs text-gray-600">Scheidslijn</span>
                            </button>
                        </div>
                    </div>

                    <!-- Layout Blocks -->
                    <div>
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Layout</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="addBlock('hero')" class="block-palette-item p-3 bg-blue-50 rounded-lg text-center hover:bg-blue-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                </svg>
                                <span class="text-xs text-blue-600">Hero</span>
                            </button>
                            <button @click="addBlock('columns')" class="block-palette-item p-3 bg-blue-50 rounded-lg text-center hover:bg-blue-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                                </svg>
                                <span class="text-xs text-blue-600">Kolommen</span>
                            </button>
                            <button @click="addBlock('cards')" class="block-palette-item p-3 bg-blue-50 rounded-lg text-center hover:bg-blue-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                                </svg>
                                <span class="text-xs text-blue-600">Kaarten</span>
                            </button>
                            <button @click="addBlock('features')" class="block-palette-item p-3 bg-blue-50 rounded-lg text-center hover:bg-blue-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                </svg>
                                <span class="text-xs text-blue-600">Features</span>
                            </button>
                        </div>
                    </div>

                    <!-- Content Blocks -->
                    <div>
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Content</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="addBlock('cta')" class="block-palette-item p-3 bg-green-50 rounded-lg text-center hover:bg-green-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                                </svg>
                                <span class="text-xs text-green-600">Call to Action</span>
                            </button>
                            <button @click="addBlock('timeline')" class="block-palette-item p-3 bg-green-50 rounded-lg text-center hover:bg-green-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs text-green-600">Tijdlijn</span>
                            </button>
                            <button @click="addBlock('faq')" class="block-palette-item p-3 bg-green-50 rounded-lg text-center hover:bg-green-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs text-green-600">FAQ</span>
                            </button>
                            <button @click="addBlock('sponsors')" class="block-palette-item p-3 bg-green-50 rounded-lg text-center hover:bg-green-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <span class="text-xs text-green-600">Sponsors</span>
                            </button>
                            <button @click="addBlock('countdown')" class="block-palette-item p-3 bg-green-50 rounded-lg text-center hover:bg-green-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs text-green-600">Countdown</span>
                            </button>
                            <button @click="addBlock('map')" class="block-palette-item p-3 bg-green-50 rounded-lg text-center hover:bg-green-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="text-xs text-green-600">Kaart</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tournament Specific -->
                    <div>
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Toernooi</h3>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="addBlock('info_card')" class="block-palette-item p-3 bg-purple-50 rounded-lg text-center hover:bg-purple-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs text-purple-600">Info Kaart</span>
                            </button>
                            <button @click="addBlock('schedule')" class="block-palette-item p-3 bg-purple-50 rounded-lg text-center hover:bg-purple-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-xs text-purple-600">Programma</span>
                            </button>
                            <button @click="addBlock('contact')" class="block-palette-item p-3 bg-purple-50 rounded-lg text-center hover:bg-purple-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="text-xs text-purple-600">Contact</span>
                            </button>
                            <button @click="addBlock('social')" class="block-palette-item p-3 bg-purple-50 rounded-lg text-center hover:bg-purple-100">
                                <svg class="w-6 h-6 mx-auto mb-1 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/>
                                </svg>
                                <span class="text-xs text-purple-600">Social Media</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sections Panel -->
            <div x-show="sidebarTab === 'sections'" class="flex-1 overflow-y-auto p-4">
                <div class="space-y-2">
                    <!-- Header & Footer -->
                    <div class="mb-4">
                        <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Header & Footer</h3>
                        <div class="space-y-2">
                            <button @click="toggleHeaderFooter('header')"
                                    class="w-full p-3 rounded-lg text-left flex items-center justify-between"
                                    :class="headerSection ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50 hover:bg-gray-100'">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-6 bg-blue-400 rounded-t"></div>
                                    <span class="text-sm">Header</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <template x-if="headerSection">
                                        <button @click.stop="toggleSticky('header')" class="p-1 rounded hover:bg-blue-100"
                                                :title="headerSection?.settings?.sticky ? 'Sticky (klik om los te maken)' : 'Niet sticky (klik om vast te zetten)'">
                                            <svg x-show="headerSection?.settings?.sticky" class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                            </svg>
                                            <svg x-show="!headerSection?.settings?.sticky" class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 17c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6-9h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6h1.9c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm0 12H6V10h12v10z"/>
                                            </svg>
                                        </button>
                                    </template>
                                    <span x-show="headerSection" class="text-green-600 text-xs">✓ Actief</span>
                                    <span x-show="!headerSection" class="text-gray-400 text-xs">+ Toevoegen</span>
                                </div>
                            </button>
                            <button @click="toggleHeaderFooter('footer')"
                                    class="w-full p-3 rounded-lg text-left flex items-center justify-between"
                                    :class="footerSection ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50 hover:bg-gray-100'">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-6 bg-gray-500 rounded-b"></div>
                                    <span class="text-sm">Footer</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <template x-if="footerSection">
                                        <button @click.stop="toggleSticky('footer')" class="p-1 rounded hover:bg-blue-100"
                                                :title="footerSection?.settings?.sticky ? 'Sticky (klik om los te maken)' : 'Niet sticky (klik om vast te zetten)'">
                                            <svg x-show="footerSection?.settings?.sticky" class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                            </svg>
                                            <svg x-show="!footerSection?.settings?.sticky" class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 17c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6-9h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6h1.9c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm0 12H6V10h12v10z"/>
                                            </svg>
                                        </button>
                                    </template>
                                    <span x-show="footerSection" class="text-green-600 text-xs">✓ Actief</span>
                                    <span x-show="!footerSection" class="text-gray-400 text-xs">+ Toevoegen</span>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Merge Mode Toggle -->
                    <div class="mb-4 p-3 rounded-lg" :class="mergeMode ? 'bg-orange-50 border border-orange-200' : 'bg-gray-50'">
                        <button @click="toggleMergeMode()" class="w-full flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5" :class="mergeMode ? 'text-orange-600' : 'text-gray-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
                                </svg>
                                <span class="text-sm font-medium" :class="mergeMode ? 'text-orange-700' : 'text-gray-700'">Cellen samenvoegen</span>
                            </div>
                            <span x-show="mergeMode" class="text-xs bg-orange-200 text-orange-700 px-2 py-1 rounded">AAN</span>
                        </button>
                        <p x-show="mergeMode" class="text-xs text-orange-600 mt-2">Klik op 2 aangrenzende cellen om samen te voegen</p>
                        <p x-show="mergeMode && selectedCells.length > 0" class="text-xs text-orange-700 mt-1 font-medium">
                            <span x-text="selectedCells.length"></span>/2 cellen geselecteerd
                        </p>
                    </div>

                    <!-- Content Sections -->
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Kolom layouts</h3>
                    <button @click="addSection('full')" class="w-full p-3 bg-gray-50 rounded-lg text-left hover:bg-gray-100 flex items-center gap-3">
                        <div class="w-12 h-8 bg-gray-300 rounded"></div>
                        <span class="text-sm">Volledige breedte</span>
                    </button>
                    <button @click="addSection('two-cols')" class="w-full p-3 bg-gray-50 rounded-lg text-left hover:bg-gray-100 flex items-center gap-3">
                        <div class="w-12 h-8 flex gap-0.5">
                            <div class="flex-1 bg-gray-300 rounded-l"></div>
                            <div class="flex-1 bg-gray-300 rounded-r"></div>
                        </div>
                        <span class="text-sm">2 kolommen (50/50)</span>
                    </button>
                    <button @click="addSection('two-cols-left')" class="w-full p-3 bg-gray-50 rounded-lg text-left hover:bg-gray-100 flex items-center gap-3">
                        <div class="w-12 h-8 flex gap-0.5">
                            <div class="w-8 bg-gray-300 rounded-l"></div>
                            <div class="flex-1 bg-gray-300 rounded-r"></div>
                        </div>
                        <span class="text-sm">2 kolommen (66/33)</span>
                    </button>
                    <button @click="addSection('two-cols-right')" class="w-full p-3 bg-gray-50 rounded-lg text-left hover:bg-gray-100 flex items-center gap-3">
                        <div class="w-12 h-8 flex gap-0.5">
                            <div class="flex-1 bg-gray-300 rounded-l"></div>
                            <div class="w-8 bg-gray-300 rounded-r"></div>
                        </div>
                        <span class="text-sm">2 kolommen (33/66)</span>
                    </button>
                    <button @click="addSection('three-cols')" class="w-full p-3 bg-gray-50 rounded-lg text-left hover:bg-gray-100 flex items-center gap-3">
                        <div class="w-12 h-8 flex gap-0.5">
                            <div class="flex-1 bg-gray-300 rounded-l"></div>
                            <div class="flex-1 bg-gray-300"></div>
                            <div class="flex-1 bg-gray-300 rounded-r"></div>
                        </div>
                        <span class="text-sm">3 kolommen</span>
                    </button>
                    <button @click="addSection('four-cols')" class="w-full p-3 bg-gray-50 rounded-lg text-left hover:bg-gray-100 flex items-center gap-3">
                        <div class="w-12 h-8 flex gap-0.5">
                            <div class="flex-1 bg-gray-300 rounded-l"></div>
                            <div class="flex-1 bg-gray-300"></div>
                            <div class="flex-1 bg-gray-300"></div>
                            <div class="flex-1 bg-gray-300 rounded-r"></div>
                        </div>
                        <span class="text-sm">4 kolommen</span>
                    </button>
                    <button @click="addSection('sidebar-left')" class="w-full p-3 bg-gray-50 rounded-lg text-left hover:bg-gray-100 flex items-center gap-3">
                        <div class="w-12 h-8 flex gap-0.5">
                            <div class="w-3 bg-gray-400 rounded-l"></div>
                            <div class="flex-1 bg-gray-300 rounded-r"></div>
                        </div>
                        <span class="text-sm">Sidebar links</span>
                    </button>
                    <button @click="addSection('sidebar-right')" class="w-full p-3 bg-gray-50 rounded-lg text-left hover:bg-gray-100 flex items-center gap-3">
                        <div class="w-12 h-8 flex gap-0.5">
                            <div class="flex-1 bg-gray-300 rounded-l"></div>
                            <div class="w-3 bg-gray-400 rounded-r"></div>
                        </div>
                        <span class="text-sm">Sidebar rechts</span>
                    </button>

                    <!-- Grid Layouts -->
                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 mt-4">Grid layouts (rijen & kolommen)</h3>
                    <button @click="addSection('grid-2x2')" class="w-full p-3 bg-blue-50 rounded-lg text-left hover:bg-blue-100 flex items-center gap-3">
                        <div class="w-12 h-10 grid grid-cols-2 grid-rows-2 gap-0.5">
                            <div class="bg-blue-300 rounded-tl"></div>
                            <div class="bg-blue-300 rounded-tr"></div>
                            <div class="bg-blue-300 rounded-bl"></div>
                            <div class="bg-blue-300 rounded-br"></div>
                        </div>
                        <span class="text-sm text-blue-700">2x2 Grid</span>
                    </button>
                    <button @click="addSection('grid-2x3')" class="w-full p-3 bg-blue-50 rounded-lg text-left hover:bg-blue-100 flex items-center gap-3">
                        <div class="w-12 h-10 grid grid-cols-3 grid-rows-2 gap-0.5">
                            <div class="bg-blue-300 rounded-tl"></div>
                            <div class="bg-blue-300"></div>
                            <div class="bg-blue-300 rounded-tr"></div>
                            <div class="bg-blue-300 rounded-bl"></div>
                            <div class="bg-blue-300"></div>
                            <div class="bg-blue-300 rounded-br"></div>
                        </div>
                        <span class="text-sm text-blue-700">2x3 Grid</span>
                    </button>
                    <button @click="addSection('grid-3x3')" class="w-full p-3 bg-blue-50 rounded-lg text-left hover:bg-blue-100 flex items-center gap-3">
                        <div class="w-12 h-10 grid grid-cols-3 grid-rows-3 gap-0.5">
                            <div class="bg-blue-300 rounded-tl"></div>
                            <div class="bg-blue-300"></div>
                            <div class="bg-blue-300 rounded-tr"></div>
                            <div class="bg-blue-300"></div>
                            <div class="bg-blue-300"></div>
                            <div class="bg-blue-300"></div>
                            <div class="bg-blue-300 rounded-bl"></div>
                            <div class="bg-blue-300"></div>
                            <div class="bg-blue-300 rounded-br"></div>
                        </div>
                        <span class="text-sm text-blue-700">3x3 Grid</span>
                    </button>
                </div>
            </div>

            <!-- Templates Panel -->
            <div x-show="sidebarTab === 'templates'" class="flex-1 overflow-y-auto p-4">
                <div class="space-y-3">
                    <p class="text-sm text-gray-500 mb-4">Start met een sjabloon:</p>
                    <button @click="loadTemplate('judo-basic')" class="w-full p-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg text-left hover:from-blue-600 hover:to-blue-700">
                        <div class="font-medium">Judo Toernooi Basis</div>
                        <div class="text-xs text-blue-100 mt-1">Hero, info, programma, sponsors</div>
                    </button>
                    <button @click="loadTemplate('judo-pro')" class="w-full p-4 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-lg text-left hover:from-purple-600 hover:to-purple-700">
                        <div class="font-medium">Judo Toernooi Pro</div>
                        <div class="text-xs text-purple-100 mt-1">Compleet met countdown, FAQ, kaart</div>
                    </button>
                    <button @click="loadTemplate('minimal')" class="w-full p-4 bg-gradient-to-r from-gray-600 to-gray-700 text-white rounded-lg text-left hover:from-gray-700 hover:to-gray-800">
                        <div class="font-medium">Minimalistisch</div>
                        <div class="text-xs text-gray-300 mt-1">Strak en eenvoudig</div>
                    </button>
                    <div class="border-t pt-3 mt-3">
                        <button @click="clearAll()" class="w-full p-3 bg-red-50 text-red-600 rounded-lg text-center hover:bg-red-100">
                            Alles wissen
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canvas Area -->
        <div class="flex-1 overflow-y-auto builder-canvas p-8" :class="'preview-' + previewMode">
            <div class="max-w-6xl mx-auto" :class="{'max-w-[768px]': previewMode === 'tablet', 'max-w-[375px]': previewMode === 'mobile'}">

                <!-- Header Section -->
                <template x-if="headerSection">
                    <div class="header-section-wrapper group mb-4 bg-white rounded-xl shadow-sm overflow-hidden relative"
                         :class="{'ring-2 ring-blue-500': headerSection.settings?.sticky}">
                        <!-- Header Badge -->
                        <div class="absolute top-2 left-2 z-10 flex items-center gap-2 bg-blue-600 text-white text-xs px-2 py-1 rounded">
                            <span>HEADER</span>
                            <template x-if="headerSection.settings?.sticky">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                            </template>
                        </div>
                        <!-- Header Controls -->
                        <div class="section-controls absolute -top-3 right-4 z-10 flex items-center gap-1 bg-white rounded-full shadow px-2 py-1">
                            <button @click="editHeaderFooter('header')" class="text-gray-400 hover:text-blue-600 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                            <button @click="toggleHeaderFooter('header')" class="text-gray-400 hover:text-red-600 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                        <!-- Header Content -->
                        <div :style="getSectionStyle(headerSection)" class="py-4 px-6">
                            <div class="blocks-container space-y-2" data-section="header" data-col="0">
                                <template x-for="block in headerSection.columns[0].blocks" :key="block.id">
                                    <div class="block-wrapper group/block relative" :data-block-id="block.id">
                                        <div class="absolute -right-2 top-0 z-10 opacity-0 group-hover/block:opacity-100 transition-opacity flex flex-col gap-1 bg-white rounded shadow p-1">
                                            <button @click="editBlock(block)" class="text-gray-400 hover:text-blue-600 p-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                </svg>
                                            </button>
                                            <button @click="removeBlockFromHeaderFooter('header', block.id)" class="text-gray-400 hover:text-red-600 p-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div x-html="renderBlock(block)"></div>
                                    </div>
                                </template>
                                <div x-show="!headerSection.columns[0].blocks || headerSection.columns[0].blocks.length === 0"
                                     @click="selectHeaderFooterColumn('header')"
                                     class="text-center py-4 text-gray-400 text-sm cursor-pointer hover:bg-gray-50 rounded">
                                    <p class="text-xs">Klik om een blok toe te voegen aan de header</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Main Sections Container -->
                <div id="sections-container" class="space-y-4">
                    <!-- Sections Loop -->
                    <template x-for="(section, sectionIndex) in sections" :key="section.id">
                    <div class="section-wrapper group bg-white rounded-xl shadow-sm overflow-hidden" :data-section-id="section.id">

                        <!-- Section Controls -->
                        <div class="section-controls absolute -top-3 left-1/2 transform -translate-x-1/2 z-10 flex items-center gap-1 bg-white rounded-full shadow px-2 py-1">
                            <span class="drag-section-handle cursor-grab text-gray-400 hover:text-gray-600 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                                </svg>
                            </span>
                            <button @click="editSection(section)" class="text-gray-400 hover:text-blue-600 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </button>
                            <button @click="duplicateSection(section)" class="text-gray-400 hover:text-green-600 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </button>
                            <button @click="removeSection(section.id)" class="text-gray-400 hover:text-red-600 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Section Content with Background -->
                        <div :style="getSectionStyle(section)" class="relative">
                            <div class="container mx-auto" :class="section.settings?.padding || 'py-12 px-6'">
                                <!-- Grid Layout (new) -->
                                <template x-if="section.grid && section.grid.length > 0">
                                    <div class="grid-container" :style="getGridStyle(section)">
                                        <template x-for="(cell, cellIndex) in getFlatCells(section)" :key="cell.id">
                                            <div x-show="!cell.merged"
                                                 class="grid-cell col-indicator rounded-lg p-4 min-h-[100px] transition-all relative"
                                                 :class="{
                                                     'has-content': cell.blocks && cell.blocks.length > 0,
                                                     'cursor-pointer': !cell.blocks || cell.blocks.length === 0,
                                                     'ring-2 ring-blue-500 bg-blue-50': isCellSelected(section.id, cell.rowIndex, cell.colIndex),
                                                     'hover:ring-2 hover:ring-blue-300': mergeMode && !isCellSelected(section.id, cell.rowIndex, cell.colIndex)
                                                 }"
                                                 :style="getCellStyle(cell, cell.rowIndex, cell.colIndex)"
                                                 @dragover.prevent="onDragOver($event)"
                                                 @drop="onDropGrid($event, section.id, cell.rowIndex, cell.colIndex)"
                                                 @click="mergeMode ? selectCellForMerge(section.id, cell.rowIndex, cell.colIndex) : selectGridCell(section.id, cell.rowIndex, cell.colIndex)">

                                                <!-- Merge Mode Indicator -->
                                                <div x-show="mergeMode" class="absolute top-1 right-1 text-xs bg-blue-100 text-blue-600 px-1 rounded">
                                                    <span x-text="'R' + (cell.rowIndex+1) + 'C' + (cell.colIndex+1)"></span>
                                                </div>

                                                <!-- Cell Span Indicator -->
                                                <div x-show="cell.colSpan > 1 || cell.rowSpan > 1" class="absolute top-1 left-1">
                                                    <button @click.stop="unmergeCells(section.id, cell.rowIndex, cell.colIndex)"
                                                            class="text-xs bg-orange-100 text-orange-600 px-1 rounded hover:bg-orange-200"
                                                            title="Samenvoegen ongedaan maken">
                                                        <span x-text="cell.colSpan + 'x' + cell.rowSpan"></span> ✕
                                                    </button>
                                                </div>

                                                <!-- Blocks in Cell -->
                                                <div class="blocks-container space-y-4" :data-section="section.id" :data-row="cell.rowIndex" :data-col="cell.colIndex">
                                                    <template x-for="(block, blockIndex) in cell.blocks" :key="block.id">
                                                        <div class="block-wrapper group/block relative" :data-block-id="block.id">

                                                            <!-- Block Controls -->
                                                            <div class="absolute -right-2 top-0 z-10 opacity-0 group-hover/block:opacity-100 transition-opacity flex flex-col gap-1 bg-white rounded shadow p-1">
                                                                <span class="drag-block-handle cursor-grab text-gray-400 hover:text-gray-600 p-1">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                                                                    </svg>
                                                                </span>
                                                                <button @click="editBlock(block)" class="text-gray-400 hover:text-blue-600 p-1">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                                    </svg>
                                                                </button>
                                                                <button @click="removeBlockFromGrid(section.id, cell.rowIndex, cell.colIndex, block.id)" class="text-gray-400 hover:text-red-600 p-1">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                    </svg>
                                                                </button>
                                                            </div>

                                                            <!-- Block Render -->
                                                            <div x-html="renderBlock(block)"></div>
                                                        </div>
                                                    </template>

                                                    <!-- Empty Cell Placeholder -->
                                                    <div x-show="!cell.blocks || cell.blocks.length === 0"
                                                         class="text-center text-gray-400 text-sm py-4">
                                                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                        </svg>
                                                        <p class="text-xs font-medium" x-text="'Kolom ' + (cell.colIndex+1)"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Legacy Columns Grid (fallback for old sections) -->
                                <template x-if="!section.grid || section.grid.length === 0">
                                    <div class="grid gap-6" :class="getColumnClasses(section.layout)">
                                        <template x-for="(column, colIndex) in section.columns" :key="colIndex">
                                            <div class="col-indicator rounded-lg p-4"
                                                 :class="{'has-content': column.blocks && column.blocks.length > 0, 'cursor-pointer': !column.blocks || column.blocks.length === 0}"
                                                 @dragover.prevent="onDragOver($event)"
                                                 @drop="onDrop($event, section.id, colIndex)"
                                                 @click="selectColumn(section.id, colIndex)">

                                                <!-- Blocks in Column -->
                                                <div class="blocks-container space-y-4" :data-section="section.id" :data-col="colIndex">
                                                    <template x-for="(block, blockIndex) in column.blocks" :key="block.id">
                                                        <div class="block-wrapper group/block relative" :data-block-id="block.id">

                                                            <!-- Block Controls -->
                                                            <div class="absolute -right-2 top-0 z-10 opacity-0 group-hover/block:opacity-100 transition-opacity flex flex-col gap-1 bg-white rounded shadow p-1">
                                                                <span class="drag-block-handle cursor-grab text-gray-400 hover:text-gray-600 p-1">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/>
                                                                    </svg>
                                                                </span>
                                                                <button @click="editBlock(block)" class="text-gray-400 hover:text-blue-600 p-1">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                                    </svg>
                                                                </button>
                                                                <button @click="removeBlock(section.id, colIndex, block.id)" class="text-gray-400 hover:text-red-600 p-1">
                                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                    </svg>
                                                                </button>
                                                            </div>

                                                            <!-- Block Render -->
                                                            <div x-html="renderBlock(block)"></div>
                                                        </div>
                                                    </template>

                                                    <!-- Empty Column Placeholder -->
                                                    <div x-show="!column.blocks || column.blocks.length === 0"
                                                         class="text-center text-gray-400 text-sm">
                                                        <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                        </svg>
                                                        <p class="font-medium">Kolom <span x-text="colIndex + 1"></span></p>
                                                        <p class="text-xs opacity-75">Klik op een blok links om toe te voegen</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- Add Row/Column Buttons -->
                            <div x-show="section.grid" class="flex justify-center gap-4 py-2 border-t border-dashed border-gray-200">
                                <button @click="addRowToSection(section)"
                                        class="text-xs text-gray-400 hover:text-blue-600 inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-blue-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Rij
                                </button>
                                <button @click="addColumnToSection(section)"
                                        :disabled="section.gridConfig?.cols >= 6"
                                        class="text-xs text-gray-400 hover:text-green-600 inline-flex items-center gap-1 px-2 py-1 rounded hover:bg-green-50 disabled:opacity-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    Kolom
                                </button>
                                <span class="text-xs text-gray-300 px-2 py-1" x-text="(section.gridConfig?.cols || 1) + '×' + (section.grid?.length || 1)"></span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div x-show="sections.length === 0" class="text-center py-20 bg-white rounded-xl border-2 border-dashed border-gray-300">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-gray-500 text-lg mb-2">Begin met bouwen</p>
                    <p class="text-gray-400 text-sm mb-6">Kies een sjabloon of voeg secties toe</p>
                    <div class="flex justify-center gap-3">
                        <button @click="loadTemplate('judo-basic')" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Start met sjabloon
                        </button>
                        <button @click="addSection('full')" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                            Lege sectie
                        </button>
                    </div>
                </div>

                <!-- Add Section Button -->
                <div x-show="sections.length > 0" class="text-center py-4">
                    <button @click="sidebarTab = 'sections'" class="px-6 py-3 bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 inline-flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Sectie toevoegen
                    </button>
                </div>
                </div><!-- End sections-container -->

                <!-- Footer Section -->
                <template x-if="footerSection">
                    <div class="footer-section-wrapper group mt-4 bg-white rounded-xl shadow-sm overflow-hidden relative"
                         :class="{'ring-2 ring-gray-500': footerSection.settings?.sticky}">
                        <!-- Footer Badge -->
                        <div class="absolute top-2 left-2 z-10 flex items-center gap-2 bg-gray-700 text-white text-xs px-2 py-1 rounded">
                            <span>FOOTER</span>
                            <template x-if="footerSection.settings?.sticky">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                                </svg>
                            </template>
                        </div>
                        <!-- Footer Controls -->
                        <div class="section-controls absolute -top-3 right-4 z-10 flex items-center gap-1 bg-white rounded-full shadow px-2 py-1">
                            <button @click="editHeaderFooter('footer')" class="text-gray-400 hover:text-blue-600 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                            <button @click="toggleHeaderFooter('footer')" class="text-gray-400 hover:text-red-600 p-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                        <!-- Footer Content -->
                        <div :style="getSectionStyle(footerSection)" class="py-4 px-6">
                            <div class="blocks-container space-y-2" data-section="footer" data-col="0">
                                <template x-for="block in footerSection.columns[0].blocks" :key="block.id">
                                    <div class="block-wrapper group/block relative" :data-block-id="block.id">
                                        <div class="absolute -right-2 top-0 z-10 opacity-0 group-hover/block:opacity-100 transition-opacity flex flex-col gap-1 bg-white rounded shadow p-1">
                                            <button @click="editBlock(block)" class="text-gray-400 hover:text-blue-600 p-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                </svg>
                                            </button>
                                            <button @click="removeBlockFromHeaderFooter('footer', block.id)" class="text-gray-400 hover:text-red-600 p-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div x-html="renderBlock(block)"></div>
                                    </div>
                                </template>
                                <div x-show="!footerSection.columns[0].blocks || footerSection.columns[0].blocks.length === 0"
                                     @click="selectHeaderFooterColumn('footer')"
                                     class="text-center py-4 text-gray-400 text-sm cursor-pointer hover:bg-gray-50 rounded">
                                    <p class="text-xs">Klik om een blok toe te voegen aan de footer</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

            </div>
        </div>

        <!-- Right Sidebar - Settings Panel -->
        <div x-show="editingBlock || editingSection"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             class="settings-panel w-80 bg-white border-l overflow-y-auto">

            <!-- Block Settings -->
            <template x-if="editingBlock">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800" x-text="getBlockLabel(editingBlock.type) + ' bewerken'"></h3>
                        <button @click="editingBlock = null" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4" x-html="renderBlockSettings(editingBlock)"></div>
                </div>
            </template>

            <!-- Section Settings -->
            <template x-if="editingSection && !editingBlock">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-semibold text-gray-800">Sectie instellingen</h3>
                        <button @click="editingSection = null" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Background Color -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Achtergrondkleur</label>
                            <div class="flex gap-2">
                                <input type="color" x-model="editingSection.settings.bgColor" @change="saveData()"
                                       class="w-10 h-10 rounded cursor-pointer border">
                                <input type="text" x-model="editingSection.settings.bgColor" @input.debounce.500ms="saveData()"
                                       class="flex-1 border rounded px-3 py-2 text-sm" placeholder="#ffffff">
                            </div>
                        </div>

                        <!-- Background Image -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Achtergrondafbeelding</label>
                            <input type="file" @change="uploadSectionBg($event)" accept="image/*"
                                   class="w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700">
                            <template x-if="editingSection.settings.bgImage">
                                <div class="mt-2 relative">
                                    <img :src="'/storage/' + editingSection.settings.bgImage" class="w-full h-20 object-cover rounded">
                                    <button @click="editingSection.settings.bgImage = null; saveData()"
                                            class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- Padding -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Padding</label>
                            <select x-model="editingSection.settings.padding" @change="saveData()"
                                    class="w-full border rounded px-3 py-2 text-sm">
                                <option value="py-4 px-4">Klein</option>
                                <option value="py-8 px-6">Normaal</option>
                                <option value="py-12 px-6">Groot</option>
                                <option value="py-20 px-8">Extra groot</option>
                            </select>
                        </div>

                        <!-- Text Color -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tekstkleur</label>
                            <div class="flex gap-2">
                                <button @click="editingSection.settings.textColor = '#1f2937'; saveData()"
                                        class="w-8 h-8 bg-gray-800 rounded border-2"
                                        :class="editingSection.settings.textColor === '#1f2937' ? 'border-blue-500' : 'border-transparent'"></button>
                                <button @click="editingSection.settings.textColor = '#ffffff'; saveData()"
                                        class="w-8 h-8 bg-white rounded border-2"
                                        :class="editingSection.settings.textColor === '#ffffff' ? 'border-blue-500' : 'border-gray-300'"></button>
                            </div>
                        </div>

                        <!-- Grid Settings (for all grid sections) -->
                        <template x-if="editingSection.grid">
                            <div class="border-t pt-4 mt-4">
                                <h4 class="text-sm font-medium text-gray-700 mb-3">Grid instellingen</h4>

                                <!-- Current Grid Info -->
                                <div class="mb-4 p-3 bg-blue-50 rounded-lg text-sm">
                                    <span class="font-medium text-blue-800">
                                        <span x-text="editingSection.gridConfig?.cols || 1"></span> kolommen ×
                                        <span x-text="editingSection.grid?.length || 1"></span> rijen
                                    </span>
                                </div>

                                <!-- Column Controls -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Kolommen</label>
                                    <div class="flex items-center gap-2">
                                        <button @click="removeColumnFromSection(editingSection, editingSection.gridConfig.cols - 1)"
                                                :disabled="editingSection.gridConfig?.cols <= 1"
                                                class="flex-1 py-2 px-3 bg-red-50 text-red-600 rounded hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                                            − Kolom
                                        </button>
                                        <span class="px-3 py-2 bg-gray-100 rounded font-medium" x-text="editingSection.gridConfig?.cols || 1"></span>
                                        <button @click="addColumnToSection(editingSection)"
                                                :disabled="editingSection.gridConfig?.cols >= 6"
                                                class="flex-1 py-2 px-3 bg-green-50 text-green-600 rounded hover:bg-green-100 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                                            + Kolom
                                        </button>
                                    </div>
                                </div>

                                <!-- Row Controls -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Rijen</label>
                                    <div class="flex items-center gap-2">
                                        <button @click="removeRowFromSection(editingSection, editingSection.grid.length - 1)"
                                                :disabled="editingSection.grid?.length <= 1"
                                                class="flex-1 py-2 px-3 bg-red-50 text-red-600 rounded hover:bg-red-100 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                                            − Rij
                                        </button>
                                        <span class="px-3 py-2 bg-gray-100 rounded font-medium" x-text="editingSection.grid?.length || 1"></span>
                                        <button @click="addRowToSection(editingSection)"
                                                :disabled="editingSection.grid?.length >= 6"
                                                class="flex-1 py-2 px-3 bg-green-50 text-green-600 rounded hover:bg-green-100 disabled:opacity-50 disabled:cursor-not-allowed text-sm">
                                            + Rij
                                        </button>
                                    </div>
                                </div>

                                <!-- Row Gap / Divider Height -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Ruimte tussen cellen</label>
                                    <select x-model="editingSection.settings.gap" @change="saveData()"
                                            class="w-full border rounded px-3 py-2 text-sm">
                                        <option value="0.5rem">Extra klein (8px)</option>
                                        <option value="1rem">Klein (16px)</option>
                                        <option value="1.5rem">Normaal (24px)</option>
                                        <option value="2rem">Groot (32px)</option>
                                        <option value="3rem">Extra groot (48px)</option>
                                        <option value="4rem">Maximaal (64px)</option>
                                    </select>
                                </div>

                                <!-- Row Height -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Rij hoogte</label>
                                    <select x-model="editingSection.settings.rowHeight" @change="saveData()"
                                            class="w-full border rounded px-3 py-2 text-sm">
                                        <option value="auto">Automatisch</option>
                                        <option value="100px">Klein (100px)</option>
                                        <option value="150px">Normaal (150px)</option>
                                        <option value="200px">Groot (200px)</option>
                                        <option value="300px">Extra groot (300px)</option>
                                    </select>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script src="https://unpkg.com/trix@2.0.0/dist/trix.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function paginaBuilderPro() {
    return {
        sections: @json($sections ?? []),
        headerSection: @json($toernooi->pagina_content['header'] ?? null),
        footerSection: @json($toernooi->pagina_content['footer'] ?? null),
        themeColor: '{{ $toernooi->thema_kleur ?? "#2563eb" }}',
        previewMode: 'desktop',
        sidebarTab: 'blocks',
        editingBlock: null,
        editingSection: null,
        saving: false,
        saved: false,
        saveTimeout: null,

        init() {
            this.$nextTick(() => {
                this.initSortable();
            });
        },

        initSortable() {
            // Make sections sortable
            const container = document.getElementById('sections-container');
            if (container) {
                new Sortable(container, {
                    animation: 150,
                    handle: '.drag-section-handle',
                    ghostClass: 'sortable-ghost',
                    onEnd: () => this.updateSectionOrder()
                });
            }

            // Make blocks in columns sortable
            document.querySelectorAll('.blocks-container').forEach(col => {
                new Sortable(col, {
                    group: 'blocks',
                    animation: 150,
                    handle: '.drag-block-handle',
                    ghostClass: 'sortable-ghost',
                    onEnd: (evt) => this.updateBlockOrder(evt)
                });
            });
        },

        generateId() {
            return 'id-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        },

        // Header/Footer Methods
        toggleHeaderFooter(type) {
            if (type === 'header') {
                if (this.headerSection) {
                    if (confirm('Weet je zeker dat je de header wilt verwijderen?')) {
                        this.headerSection = null;
                    }
                } else {
                    this.headerSection = {
                        id: this.generateId(),
                        columns: [{ blocks: [] }],
                        settings: {
                            bgColor: '#ffffff',
                            textColor: '#1f2937',
                            sticky: false
                        }
                    };
                }
            } else {
                if (this.footerSection) {
                    if (confirm('Weet je zeker dat je de footer wilt verwijderen?')) {
                        this.footerSection = null;
                    }
                } else {
                    this.footerSection = {
                        id: this.generateId(),
                        columns: [{ blocks: [] }],
                        settings: {
                            bgColor: '#1f2937',
                            textColor: '#ffffff',
                            sticky: false
                        }
                    };
                }
            }
            this.saveData();
        },

        toggleSticky(type) {
            if (type === 'header' && this.headerSection) {
                this.headerSection.settings.sticky = !this.headerSection.settings.sticky;
            } else if (type === 'footer' && this.footerSection) {
                this.footerSection.settings.sticky = !this.footerSection.settings.sticky;
            }
            this.saveData();
        },

        editHeaderFooter(type) {
            this.editingBlock = null;
            this.editingSection = type === 'header' ? this.headerSection : this.footerSection;
        },

        selectHeaderFooterColumn(type) {
            this.selectedSectionId = type;
            this.selectedColIndex = 0;
        },

        removeBlockFromHeaderFooter(type, blockId) {
            const section = type === 'header' ? this.headerSection : this.footerSection;
            if (section) {
                section.columns[0].blocks = section.columns[0].blocks.filter(b => b.id !== blockId);
                this.saveData();
            }
        },

        // Grid cell selection for merging
        selectedCells: [],
        mergeMode: false,

        // Section Methods
        addSection(layout) {
            // New grid-based structure: rows containing cells
            const gridConfig = {
                'full': { rows: 1, cols: 1 },
                'two-cols': { rows: 1, cols: 2 },
                'two-cols-left': { rows: 1, cols: 2, colWidths: ['2fr', '1fr'] },
                'two-cols-right': { rows: 1, cols: 2, colWidths: ['1fr', '2fr'] },
                'three-cols': { rows: 1, cols: 3 },
                'four-cols': { rows: 1, cols: 4 },
                'sidebar-left': { rows: 1, cols: 2, colWidths: ['250px', '1fr'] },
                'sidebar-right': { rows: 1, cols: 2, colWidths: ['1fr', '250px'] },
                // New grid layouts
                'grid-2x2': { rows: 2, cols: 2 },
                'grid-2x3': { rows: 2, cols: 3 },
                'grid-3x3': { rows: 3, cols: 3 },
            };

            const config = gridConfig[layout] || { rows: 1, cols: 1 };

            // Create grid cells
            const grid = [];
            for (let r = 0; r < config.rows; r++) {
                const row = [];
                for (let c = 0; c < config.cols; c++) {
                    row.push({
                        id: this.generateId(),
                        blocks: [],
                        rowSpan: 1,
                        colSpan: 1,
                        merged: false // true if this cell is merged into another
                    });
                }
                grid.push(row);
            }

            // Legacy columns format for backwards compatibility
            const columns = grid[0] || [{ blocks: [] }];

            this.sections.push({
                id: this.generateId(),
                layout: layout,
                columns: columns, // Keep for backwards compat
                grid: grid, // New grid structure
                gridConfig: config,
                settings: {
                    bgColor: '#ffffff',
                    bgImage: null,
                    padding: 'py-12 px-6',
                    textColor: '#1f2937',
                    rowHeight: 'auto', // or specific height like '200px'
                    gap: '1.5rem'
                }
            });

            this.$nextTick(() => this.initSortable());
            this.saveData();
        },

        // Add row to section
        addRowToSection(section) {
            if (!section.grid) {
                // Convert old format to grid
                section.grid = [section.columns.map(col => ({
                    id: this.generateId(),
                    blocks: col.blocks || [],
                    rowSpan: 1,
                    colSpan: 1,
                    merged: false
                }))];
                section.gridConfig = { rows: 1, cols: section.columns.length };
            }

            const cols = section.gridConfig.cols;
            const newRow = [];
            for (let c = 0; c < cols; c++) {
                newRow.push({
                    id: this.generateId(),
                    blocks: [],
                    rowSpan: 1,
                    colSpan: 1,
                    merged: false
                });
            }
            section.grid.push(newRow);
            section.gridConfig.rows++;
            this.saveData();
        },

        // Remove row from section
        removeRowFromSection(section, rowIndex) {
            if (section.grid && section.grid.length > 1) {
                section.grid.splice(rowIndex, 1);
                section.gridConfig.rows--;
                this.saveData();
            }
        },

        // Add column to section
        addColumnToSection(section) {
            if (!section.grid || !section.gridConfig) return;

            // Add a cell to each row
            section.grid.forEach(row => {
                row.push({
                    id: this.generateId(),
                    blocks: [],
                    rowSpan: 1,
                    colSpan: 1,
                    merged: false
                });
            });

            section.gridConfig.cols++;
            // Update colWidths if exists
            if (section.gridConfig.colWidths) {
                section.gridConfig.colWidths.push('1fr');
            }
            this.saveData();
        },

        // Remove column from section
        removeColumnFromSection(section, colIndex) {
            if (!section.grid || !section.gridConfig || section.gridConfig.cols <= 1) return;

            // Remove cell from each row at colIndex
            section.grid.forEach(row => {
                if (row[colIndex]) {
                    row.splice(colIndex, 1);
                }
            });

            section.gridConfig.cols--;
            // Update colWidths if exists
            if (section.gridConfig.colWidths && section.gridConfig.colWidths.length > colIndex) {
                section.gridConfig.colWidths.splice(colIndex, 1);
            }
            this.saveData();
        },

        // Toggle merge mode
        toggleMergeMode() {
            this.mergeMode = !this.mergeMode;
            if (!this.mergeMode) {
                this.selectedCells = [];
            }
        },

        // Select cell for merging
        selectCellForMerge(sectionId, rowIndex, colIndex) {
            if (!this.mergeMode) return;

            const cellId = `${sectionId}-${rowIndex}-${colIndex}`;
            const existingIndex = this.selectedCells.findIndex(c => c.id === cellId);

            if (existingIndex >= 0) {
                // Deselect
                this.selectedCells.splice(existingIndex, 1);
            } else {
                // Select (max 2 cells)
                if (this.selectedCells.length < 2) {
                    this.selectedCells.push({
                        id: cellId,
                        sectionId,
                        rowIndex,
                        colIndex
                    });
                }

                // If 2 cells selected from same section, try to merge
                if (this.selectedCells.length === 2) {
                    this.attemptMergeCells();
                }
            }
        },

        // Check if cell is selected
        isCellSelected(sectionId, rowIndex, colIndex) {
            const cellId = `${sectionId}-${rowIndex}-${colIndex}`;
            return this.selectedCells.some(c => c.id === cellId);
        },

        // Attempt to merge selected cells
        attemptMergeCells() {
            if (this.selectedCells.length !== 2) return;

            const [cell1, cell2] = this.selectedCells;

            // Must be in same section
            if (cell1.sectionId !== cell2.sectionId) {
                alert('Cellen moeten in dezelfde sectie zijn');
                this.selectedCells = [];
                return;
            }

            const section = this.sections.find(s => s.id === cell1.sectionId);
            if (!section || !section.grid) {
                this.selectedCells = [];
                return;
            }

            // Check if cells are adjacent
            const sameRow = cell1.rowIndex === cell2.rowIndex;
            const sameCol = cell1.colIndex === cell2.colIndex;
            const adjacentRow = Math.abs(cell1.rowIndex - cell2.rowIndex) === 1;
            const adjacentCol = Math.abs(cell1.colIndex - cell2.colIndex) === 1;

            if (sameRow && adjacentCol) {
                // Horizontal merge
                const leftCol = Math.min(cell1.colIndex, cell2.colIndex);
                const rightCol = Math.max(cell1.colIndex, cell2.colIndex);

                const leftCell = section.grid[cell1.rowIndex][leftCol];
                const rightCell = section.grid[cell1.rowIndex][rightCol];

                // Move blocks from right to left
                leftCell.blocks = [...leftCell.blocks, ...rightCell.blocks];
                leftCell.colSpan = (leftCell.colSpan || 1) + (rightCell.colSpan || 1);

                // Mark right cell as merged
                rightCell.merged = true;
                rightCell.mergedInto = leftCell.id;
                rightCell.blocks = [];

                this.saveData();
            } else if (sameCol && adjacentRow) {
                // Vertical merge
                const topRow = Math.min(cell1.rowIndex, cell2.rowIndex);
                const bottomRow = Math.max(cell1.rowIndex, cell2.rowIndex);

                const topCell = section.grid[topRow][cell1.colIndex];
                const bottomCell = section.grid[bottomRow][cell1.colIndex];

                // Move blocks from bottom to top
                topCell.blocks = [...topCell.blocks, ...bottomCell.blocks];
                topCell.rowSpan = (topCell.rowSpan || 1) + (bottomCell.rowSpan || 1);

                // Mark bottom cell as merged
                bottomCell.merged = true;
                bottomCell.mergedInto = topCell.id;
                bottomCell.blocks = [];

                this.saveData();
            } else {
                alert('Alleen aangrenzende cellen kunnen worden samengevoegd');
            }

            this.selectedCells = [];
            this.mergeMode = false;
        },

        // Unmerge a cell
        unmergeCells(section, rowIndex, colIndex) {
            const cell = section.grid[rowIndex][colIndex];
            if (!cell) return;

            // Find all cells merged into this one
            section.grid.forEach((row, rIdx) => {
                row.forEach((c, cIdx) => {
                    if (c.mergedInto === cell.id) {
                        c.merged = false;
                        c.mergedInto = null;
                    }
                });
            });

            // Reset spans
            cell.rowSpan = 1;
            cell.colSpan = 1;

            this.saveData();
        },

        removeSection(sectionId) {
            if (confirm('Weet je zeker dat je deze sectie wilt verwijderen?')) {
                this.sections = this.sections.filter(s => s.id !== sectionId);
                this.saveData();
            }
        },

        duplicateSection(section) {
            const newSection = JSON.parse(JSON.stringify(section));
            newSection.id = this.generateId();
            newSection.columns.forEach(col => {
                col.blocks.forEach(block => block.id = this.generateId());
            });
            const index = this.sections.findIndex(s => s.id === section.id);
            this.sections.splice(index + 1, 0, newSection);
            this.$nextTick(() => this.initSortable());
            this.saveData();
        },

        editSection(section) {
            this.editingBlock = null;
            this.editingSection = section;
        },

        updateSectionOrder() {
            const container = document.getElementById('sections-container');
            const newOrder = [];
            container.querySelectorAll('[data-section-id]').forEach(el => {
                const id = el.getAttribute('data-section-id');
                const section = this.sections.find(s => s.id === id);
                if (section) newOrder.push(section);
            });
            this.sections = newOrder;
            this.saveData();
        },

        getColumnClasses(layout) {
            const classes = {
                'full': 'grid-cols-1',
                'two-cols': 'md:grid-cols-2',
                'two-cols-left': 'md:grid-cols-[2fr_1fr]',
                'two-cols-right': 'md:grid-cols-[1fr_2fr]',
                'three-cols': 'md:grid-cols-3',
                'four-cols': 'md:grid-cols-4',
                'sidebar-left': 'md:grid-cols-[250px_1fr]',
                'sidebar-right': 'md:grid-cols-[1fr_250px]'
            };
            return classes[layout] || 'grid-cols-1';
        },

        getSectionStyle(section) {
            let style = '';
            if (section.settings?.bgColor) {
                style += `background-color: ${section.settings.bgColor};`;
            }
            if (section.settings?.bgImage) {
                style += `background-image: url('/storage/${section.settings.bgImage}'); background-size: cover; background-position: center;`;
            }
            if (section.settings?.textColor) {
                style += `color: ${section.settings.textColor};`;
            }
            return style;
        },

        // Flatten grid cells for proper CSS Grid rendering
        getFlatCells(section) {
            if (!section.grid) return [];
            const flat = [];
            section.grid.forEach((row, rowIndex) => {
                row.forEach((cell, colIndex) => {
                    flat.push({
                        ...cell,
                        rowIndex,
                        colIndex
                    });
                });
            });
            return flat;
        },

        // Grid style for CSS Grid layout
        getGridStyle(section) {
            if (!section.grid || !section.gridConfig) return '';
            const cols = section.gridConfig.cols || 1;
            const rows = section.grid.length;
            const gap = section.settings?.gap || '1.5rem';
            const rowHeight = section.settings?.rowHeight || 'auto';
            const colWidths = section.gridConfig.colWidths || Array(cols).fill('1fr');
            const rowTemplate = rowHeight === 'auto' ? 'minmax(100px, auto)' : rowHeight;

            return `display: grid; grid-template-columns: ${colWidths.join(' ')}; grid-template-rows: repeat(${rows}, ${rowTemplate}); gap: ${gap};`;
        },

        // Cell style for grid-column and grid-row span
        getCellStyle(cell, rowIndex, colIndex) {
            let style = '';
            if (cell.colSpan && cell.colSpan > 1) {
                style += `grid-column: span ${cell.colSpan};`;
            }
            if (cell.rowSpan && cell.rowSpan > 1) {
                style += `grid-row: span ${cell.rowSpan};`;
            }
            return style;
        },

        // Select grid cell for adding blocks
        selectedRowIndex: 0,

        selectGridCell(sectionId, rowIndex, colIndex) {
            this.selectedSectionId = sectionId;
            this.selectedRowIndex = rowIndex;
            this.selectedColIndex = colIndex;
        },

        // Drop handler for grid cells
        onDropGrid(event, sectionId, rowIndex, colIndex) {
            event.preventDefault();
            const blockType = event.dataTransfer.getData('blockType');
            if (!blockType) return;

            const section = this.sections.find(s => s.id === sectionId);
            if (!section || !section.grid) return;

            const cell = section.grid[rowIndex]?.[colIndex];
            if (!cell || cell.merged) return;

            const block = {
                id: this.generateId(),
                type: blockType,
                data: this.getBlockDefaults(blockType)
            };

            cell.blocks.push(block);
            this.$nextTick(() => this.initSortable());
            this.saveData();
            this.editBlock(block);
        },

        // Remove block from grid cell
        removeBlockFromGrid(sectionId, rowIndex, colIndex, blockId) {
            const section = this.sections.find(s => s.id === sectionId);
            if (!section || !section.grid) return;

            const cell = section.grid[rowIndex]?.[colIndex];
            if (!cell) return;

            cell.blocks = cell.blocks.filter(b => b.id !== blockId);
            this.saveData();
        },

        // Selected column for adding blocks
        selectedSectionId: null,
        selectedColIndex: 0,

        selectColumn(sectionId, colIndex) {
            this.selectedSectionId = sectionId;
            this.selectedColIndex = colIndex;
        },

        // Block Methods
        addBlock(type) {
            const defaultData = this.getBlockDefaults(type);
            const block = {
                id: this.generateId(),
                type: type,
                data: defaultData
            };

            // Check if adding to header or footer
            if (this.selectedSectionId === 'header' && this.headerSection) {
                this.headerSection.columns[0].blocks.push(block);
                this.$nextTick(() => this.initSortable());
                this.saveData();
                this.editBlock(block);
                return;
            }

            if (this.selectedSectionId === 'footer' && this.footerSection) {
                this.footerSection.columns[0].blocks.push(block);
                this.$nextTick(() => this.initSortable());
                this.saveData();
                this.editBlock(block);
                return;
            }

            // Add to selected column or create new section
            if (this.sections.length === 0) {
                this.addSection('full');
            }

            // Find target section and column
            let targetSection = null;
            let targetColIndex = 0;

            if (this.selectedSectionId) {
                targetSection = this.sections.find(s => s.id === this.selectedSectionId);
                targetColIndex = this.selectedColIndex;
            }

            if (!targetSection) {
                targetSection = this.sections[this.sections.length - 1];
                targetColIndex = 0;
            }

            // Add to grid cell if section has grid
            if (targetSection.grid && targetSection.grid.length > 0) {
                const rowIndex = this.selectedRowIndex || 0;
                const cell = targetSection.grid[rowIndex]?.[targetColIndex];
                if (cell && !cell.merged) {
                    cell.blocks.push(block);
                    this.$nextTick(() => this.initSortable());
                    this.saveData();
                    this.editBlock(block);
                    return;
                }
            }

            // Fallback to columns (legacy)
            if (!targetSection.columns[targetColIndex]) {
                targetColIndex = 0;
            }

            targetSection.columns[targetColIndex].blocks.push(block);
            this.$nextTick(() => this.initSortable());
            this.saveData();

            // Open settings for the new block
            this.editBlock(block);
        },

        getBlockDefaults(type) {
            const defaults = {
                heading: { text: 'Nieuwe kop', level: 'h2', align: 'left' },
                text: { html: '<p>Voeg hier tekst toe...</p>' },
                image: { src: null, alt: '', caption: '', width: '100%' },
                button: { text: 'Klik hier', url: '#', style: 'primary', align: 'left' },
                video: { url: '', title: '' },
                divider: { style: 'solid', color: '#e5e7eb' },
                hero: {
                    title: 'Welkom bij ons toernooi',
                    subtitle: 'De beste judoka\'s komen samen',
                    bgImage: null,
                    buttons: [{ text: 'Meer info', url: '#', style: 'primary' }],
                    overlay: true
                },
                columns: { count: 2, content: [{}, {}] },
                cards: {
                    cards: [
                        { title: 'Kaart 1', text: 'Beschrijving', icon: 'star' },
                        { title: 'Kaart 2', text: 'Beschrijving', icon: 'star' },
                        { title: 'Kaart 3', text: 'Beschrijving', icon: 'star' }
                    ]
                },
                features: {
                    title: 'Wat maakt ons uniek?',
                    features: [
                        { title: 'Feature 1', description: 'Beschrijving', icon: 'check' },
                        { title: 'Feature 2', description: 'Beschrijving', icon: 'check' },
                        { title: 'Feature 3', description: 'Beschrijving', icon: 'check' }
                    ]
                },
                cta: {
                    title: 'Klaar om mee te doen?',
                    text: 'Schrijf je nu in voor het toernooi',
                    buttonText: 'Inschrijven',
                    buttonUrl: '#',
                    bgColor: '#2563eb'
                },
                timeline: {
                    title: 'Programma',
                    items: [
                        { time: '08:00', title: 'Inschrijving', description: 'Weging en registratie' },
                        { time: '09:00', title: 'Start', description: 'Opening toernooi' },
                        { time: '12:00', title: 'Pauze', description: 'Lunch' },
                        { time: '17:00', title: 'Finale', description: 'Finalewedstrijden' }
                    ]
                },
                faq: {
                    title: 'Veelgestelde vragen',
                    items: [
                        { question: 'Hoe schrijf ik me in?', answer: 'Neem contact op met je club.' },
                        { question: 'Wat moet ik meenemen?', answer: 'Judopak, slippers, identiteitsbewijs.' }
                    ]
                },
                sponsors: { sponsors: [] },
                countdown: {
                    targetDate: '{{ $toernooi->datum ?? now()->addMonth()->format("Y-m-d") }}',
                    title: 'Nog te gaan tot het toernooi'
                },
                map: { address: '{{ $toernooi->locatie ?? "" }}', height: '300px' },
                info_card: {},
                schedule: {},
                contact: {
                    email: '{{ $toernooi->contact_email ?? "" }}',
                    phone: '',
                    showForm: false
                },
                social: {
                    facebook: '',
                    instagram: '',
                    twitter: '',
                    youtube: ''
                }
            };
            return defaults[type] || {};
        },

        removeBlock(sectionId, colIndex, blockId) {
            const section = this.sections.find(s => s.id === sectionId);
            if (section) {
                section.columns[colIndex].blocks = section.columns[colIndex].blocks.filter(b => b.id !== blockId);
                this.saveData();
            }
        },

        editBlock(block) {
            this.editingSection = null;
            this.editingBlock = block;
        },

        updateBlockOrder(evt) {
            // Re-sync blocks from DOM to data
            this.sections.forEach(section => {
                section.columns.forEach((col, colIndex) => {
                    const container = document.querySelector(`[data-section="${section.id}"][data-col="${colIndex}"]`);
                    if (container) {
                        const newBlocks = [];
                        container.querySelectorAll('[data-block-id]').forEach(el => {
                            const blockId = el.getAttribute('data-block-id');
                            // Find block in any section/column
                            this.sections.forEach(s => {
                                s.columns.forEach(c => {
                                    const block = c.blocks.find(b => b.id === blockId);
                                    if (block) newBlocks.push(block);
                                });
                            });
                        });
                        col.blocks = newBlocks;
                    }
                });
            });
            this.saveData();
        },

        getBlockLabel(type) {
            const labels = {
                heading: 'Kop', text: 'Tekst', image: 'Afbeelding', button: 'Button',
                video: 'Video', divider: 'Scheidslijn', hero: 'Hero', columns: 'Kolommen',
                cards: 'Kaarten', features: 'Features', cta: 'Call to Action',
                timeline: 'Tijdlijn', faq: 'FAQ', sponsors: 'Sponsors', countdown: 'Countdown',
                map: 'Kaart', info_card: 'Info Kaart', schedule: 'Programma',
                contact: 'Contact', social: 'Social Media'
            };
            return labels[type] || type;
        },

        // Render Methods
        renderBlock(block) {
            const renderers = {
                heading: (b) => `<${b.data.level || 'h2'} class="font-bold text-${b.data.align || 'left'}" style="font-size: ${b.data.level === 'h1' ? '2.5rem' : b.data.level === 'h2' ? '2rem' : '1.5rem'}">${this.escapeHtml(b.data.text || '')}</${b.data.level || 'h2'}>`,

                text: (b) => `<div class="prose max-w-none">${b.data.html || ''}</div>`,

                image: (b) => b.data.src ? `
                    <figure style="width: ${b.data.width || '100%'}">
                        <img src="/storage/${b.data.src}" alt="${this.escapeHtml(b.data.alt || '')}" class="rounded-lg w-full">
                        ${b.data.caption ? `<figcaption class="text-sm text-gray-500 mt-2">${this.escapeHtml(b.data.caption)}</figcaption>` : ''}
                    </figure>
                ` : '<div class="bg-gray-100 rounded-lg p-8 text-center text-gray-400">Klik om afbeelding te uploaden</div>',

                button: (b) => `
                    <div class="text-${b.data.align || 'left'}">
                        <a href="${b.data.url || '#'}" class="inline-block px-6 py-3 rounded-lg font-medium ${b.data.style === 'primary' ? 'bg-blue-600 text-white' : b.data.style === 'secondary' ? 'bg-gray-200 text-gray-800' : 'border-2 border-current'}">${this.escapeHtml(b.data.text || 'Button')}</a>
                    </div>
                `,

                video: (b) => b.data.url ? `
                    <div class="aspect-video bg-gray-900 rounded-lg flex items-center justify-center">
                        <iframe src="${this.getEmbedUrl(b.data.url)}" class="w-full h-full rounded-lg" allowfullscreen></iframe>
                    </div>
                ` : '<div class="aspect-video bg-gray-100 rounded-lg flex items-center justify-center text-gray-400">Voer een YouTube/Vimeo URL in</div>',

                divider: (b) => `<hr class="border-t-2" style="border-color: ${b.data.color || '#e5e7eb'}; border-style: ${b.data.style || 'solid'}">`,

                hero: (b) => `
                    <div class="relative rounded-xl overflow-hidden ${b.data.bgImage ? '' : 'bg-gradient-to-r from-blue-600 to-blue-800'}" style="${b.data.bgImage ? `background-image: url('/storage/${b.data.bgImage}'); background-size: cover; background-position: center;` : ''}">
                        ${b.data.overlay ? '<div class="absolute inset-0 bg-black/50"></div>' : ''}
                        <div class="relative z-10 py-20 px-8 text-center text-white">
                            <h1 class="text-4xl md:text-5xl font-bold mb-4">${this.escapeHtml(b.data.title || '')}</h1>
                            <p class="text-xl md:text-2xl opacity-90 mb-8">${this.escapeHtml(b.data.subtitle || '')}</p>
                            <div class="flex gap-4 justify-center">
                                ${(b.data.buttons || []).map(btn => `<a href="${btn.url || '#'}" class="px-8 py-3 rounded-lg font-medium ${btn.style === 'primary' ? 'bg-white text-blue-600' : 'border-2 border-white text-white'}">${this.escapeHtml(btn.text)}</a>`).join('')}
                            </div>
                        </div>
                    </div>
                `,

                cards: (b) => `
                    <div class="grid md:grid-cols-3 gap-6">
                        ${(b.data.cards || []).map(card => `
                            <div class="bg-white rounded-xl shadow p-6 text-center">
                                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                </div>
                                <h3 class="font-semibold text-lg mb-2">${this.escapeHtml(card.title)}</h3>
                                <p class="text-gray-600">${this.escapeHtml(card.text)}</p>
                            </div>
                        `).join('')}
                    </div>
                `,

                features: (b) => `
                    <div>
                        <h2 class="text-2xl font-bold text-center mb-8">${this.escapeHtml(b.data.title || '')}</h2>
                        <div class="grid md:grid-cols-3 gap-6">
                            ${(b.data.features || []).map(f => `
                                <div class="flex gap-4">
                                    <div class="w-10 h-10 bg-green-100 text-green-600 rounded-full flex items-center justify-center flex-shrink-0">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="font-semibold mb-1">${this.escapeHtml(f.title)}</h3>
                                        <p class="text-gray-600 text-sm">${this.escapeHtml(f.description)}</p>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `,

                cta: (b) => `
                    <div class="rounded-xl p-8 text-center text-white" style="background-color: ${b.data.bgColor || '#2563eb'}">
                        <h2 class="text-2xl font-bold mb-2">${this.escapeHtml(b.data.title || '')}</h2>
                        <p class="opacity-90 mb-6">${this.escapeHtml(b.data.text || '')}</p>
                        <a href="${b.data.buttonUrl || '#'}" class="inline-block px-8 py-3 bg-white text-gray-800 rounded-lg font-medium">${this.escapeHtml(b.data.buttonText || 'Klik hier')}</a>
                    </div>
                `,

                timeline: (b) => `
                    <div>
                        <h2 class="text-2xl font-bold text-center mb-8">${this.escapeHtml(b.data.title || '')}</h2>
                        <div class="space-y-4">
                            ${(b.data.items || []).map(item => `
                                <div class="flex gap-4 items-start">
                                    <div class="w-20 flex-shrink-0 text-right">
                                        <span class="font-bold text-blue-600">${this.escapeHtml(item.time)}</span>
                                    </div>
                                    <div class="w-3 h-3 bg-blue-600 rounded-full mt-1.5 flex-shrink-0"></div>
                                    <div class="flex-1 pb-4 border-l-2 border-gray-200 pl-4 -ml-1.5">
                                        <h3 class="font-semibold">${this.escapeHtml(item.title)}</h3>
                                        <p class="text-gray-600 text-sm">${this.escapeHtml(item.description)}</p>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `,

                faq: (b) => `
                    <div>
                        <h2 class="text-2xl font-bold text-center mb-8">${this.escapeHtml(b.data.title || '')}</h2>
                        <div class="space-y-4 max-w-2xl mx-auto">
                            ${(b.data.items || []).map(item => `
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <h3 class="font-semibold mb-2">${this.escapeHtml(item.question)}</h3>
                                    <p class="text-gray-600">${this.escapeHtml(item.answer)}</p>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `,

                sponsors: (b) => `
                    <div class="text-center">
                        <h3 class="text-xl font-semibold mb-6">Onze Sponsors</h3>
                        <div class="flex flex-wrap justify-center gap-8 items-center">
                            ${(b.data.sponsors || []).map(s => s.logo ? `
                                <a href="${s.url || '#'}" target="_blank" class="hover:opacity-80 transition-opacity">
                                    <img src="/storage/${s.logo}" alt="${this.escapeHtml(s.naam || '')}" class="h-16 object-contain">
                                </a>
                            ` : '').join('')}
                            ${(!b.data.sponsors || b.data.sponsors.length === 0) ? '<p class="text-gray-400">Klik om sponsors toe te voegen</p>' : ''}
                        </div>
                    </div>
                `,

                countdown: (b) => `
                    <div class="text-center py-8">
                        <h3 class="text-xl font-semibold mb-6">${this.escapeHtml(b.data.title || '')}</h3>
                        <div class="flex justify-center gap-4" x-data="countdown('${b.data.targetDate}')" x-init="start()">
                            <div class="bg-blue-600 text-white rounded-lg px-6 py-4">
                                <div class="text-3xl font-bold" x-text="days">0</div>
                                <div class="text-sm opacity-75">Dagen</div>
                            </div>
                            <div class="bg-blue-600 text-white rounded-lg px-6 py-4">
                                <div class="text-3xl font-bold" x-text="hours">0</div>
                                <div class="text-sm opacity-75">Uren</div>
                            </div>
                            <div class="bg-blue-600 text-white rounded-lg px-6 py-4">
                                <div class="text-3xl font-bold" x-text="minutes">0</div>
                                <div class="text-sm opacity-75">Min</div>
                            </div>
                            <div class="bg-blue-600 text-white rounded-lg px-6 py-4">
                                <div class="text-3xl font-bold" x-text="seconds">0</div>
                                <div class="text-sm opacity-75">Sec</div>
                            </div>
                        </div>
                    </div>
                `,

                map: (b) => `
                    <div class="rounded-lg overflow-hidden" style="height: ${b.data.height || '300px'}">
                        <iframe
                            src="https://maps.google.com/maps?q=${encodeURIComponent(b.data.address || '')}&output=embed"
                            class="w-full h-full border-0"
                            loading="lazy">
                        </iframe>
                    </div>
                `,

                info_card: (b) => `
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6">
                        <h3 class="font-semibold text-blue-800 mb-4">Toernooi Informatie</h3>
                        <div class="space-y-2 text-blue-700">
                            <p><strong>Datum:</strong> {{ $toernooi->datum ? $toernooi->datum->format('d-m-Y') : 'Nog niet bekend' }}</p>
                            <p><strong>Locatie:</strong> {{ $toernooi->locatie ?? 'Nog niet bekend' }}</p>
                            <p><strong>Organisator:</strong> {{ $toernooi->organisator ?? 'Nog niet bekend' }}</p>
                        </div>
                    </div>
                `,

                contact: (b) => `
                    <div class="bg-gray-50 rounded-xl p-6">
                        <h3 class="font-semibold mb-4">Contact</h3>
                        <div class="space-y-2">
                            ${b.data.email ? `<p><a href="mailto:${b.data.email}" class="text-blue-600 hover:underline">${this.escapeHtml(b.data.email)}</a></p>` : ''}
                            ${b.data.phone ? `<p><a href="tel:${b.data.phone}" class="text-blue-600 hover:underline">${this.escapeHtml(b.data.phone)}</a></p>` : ''}
                        </div>
                    </div>
                `,

                social: (b) => `
                    <div class="flex justify-center gap-4">
                        ${b.data.facebook ? `<a href="${b.data.facebook}" target="_blank" class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center hover:opacity-80"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/></svg></a>` : ''}
                        ${b.data.instagram ? `<a href="${b.data.instagram}" target="_blank" class="w-10 h-10 bg-pink-600 text-white rounded-full flex items-center justify-center hover:opacity-80"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>` : ''}
                        ${b.data.twitter ? `<a href="${b.data.twitter}" target="_blank" class="w-10 h-10 bg-sky-500 text-white rounded-full flex items-center justify-center hover:opacity-80"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg></a>` : ''}
                        ${b.data.youtube ? `<a href="${b.data.youtube}" target="_blank" class="w-10 h-10 bg-red-600 text-white rounded-full flex items-center justify-center hover:opacity-80"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg></a>` : ''}
                        ${!b.data.facebook && !b.data.instagram && !b.data.twitter && !b.data.youtube ? '<p class="text-gray-400">Voeg social media links toe</p>' : ''}
                    </div>
                `,

                schedule: (b) => `
                    <div class="bg-white rounded-xl shadow p-6">
                        <h3 class="font-semibold text-lg mb-4">Dagprogramma</h3>
                        <p class="text-gray-500">Wordt automatisch geladen vanuit toernooi instellingen</p>
                    </div>
                `
            };

            return renderers[block.type] ? renderers[block.type](block) : `<div class="p-4 bg-gray-100 rounded">${block.type}</div>`;
        },

        renderBlockSettings(block) {
            const self = this;
            const b = block;

            // Common field generator
            const field = (label, type, dataKey, options = {}) => {
                const value = b.data[dataKey] || '';
                const id = `setting-${b.id}-${dataKey}`;

                if (type === 'text') {
                    return `
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${label}</label>
                            <input type="text" value="${this.escapeHtml(value)}" data-key="${dataKey}"
                                   @input="updateBlockData('${dataKey}', $event.target.value)"
                                   class="w-full border rounded px-3 py-2 text-sm">
                        </div>
                    `;
                }
                if (type === 'textarea') {
                    return `
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${label}</label>
                            <textarea data-key="${dataKey}" rows="3"
                                      @input="updateBlockData('${dataKey}', $event.target.value)"
                                      class="w-full border rounded px-3 py-2 text-sm">${this.escapeHtml(value)}</textarea>
                        </div>
                    `;
                }
                if (type === 'select') {
                    const opts = options.options || [];
                    return `
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${label}</label>
                            <select data-key="${dataKey}"
                                    @change="updateBlockData('${dataKey}', $event.target.value)"
                                    class="w-full border rounded px-3 py-2 text-sm">
                                ${opts.map(o => `<option value="${o.value}" ${value === o.value ? 'selected' : ''}>${o.label}</option>`).join('')}
                            </select>
                        </div>
                    `;
                }
                if (type === 'color') {
                    return `
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${label}</label>
                            <div class="flex gap-2">
                                <input type="color" value="${value || '#2563eb'}" data-key="${dataKey}"
                                       @change="updateBlockData('${dataKey}', $event.target.value)"
                                       class="w-10 h-10 rounded cursor-pointer border">
                                <input type="text" value="${value || ''}" data-key="${dataKey}"
                                       @input="updateBlockData('${dataKey}', $event.target.value)"
                                       class="flex-1 border rounded px-3 py-2 text-sm" placeholder="#000000">
                            </div>
                        </div>
                    `;
                }
                if (type === 'image') {
                    return `
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${label}</label>
                            ${value ? `<img src="/storage/${value}" class="w-full h-24 object-cover rounded mb-2">` : ''}
                            <input type="file" accept="image/*" data-key="${dataKey}"
                                   @change="uploadBlockImage($event, '${dataKey}')"
                                   class="w-full text-sm text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-50 file:text-blue-700">
                            ${value ? `<button type="button" @click="updateBlockData('${dataKey}', null)" class="text-red-500 text-sm mt-1">Verwijderen</button>` : ''}
                        </div>
                    `;
                }
                if (type === 'url') {
                    return `
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">${label}</label>
                            <input type="url" value="${this.escapeHtml(value)}" data-key="${dataKey}"
                                   @input="updateBlockData('${dataKey}', $event.target.value)"
                                   class="w-full border rounded px-3 py-2 text-sm" placeholder="https://">
                        </div>
                    `;
                }
                return '';
            };

            // Type-specific settings
            const settings = {
                heading: () => `
                    ${field('Tekst', 'text', 'text')}
                    ${field('Niveau', 'select', 'level', { options: [
                        { value: 'h1', label: 'H1 - Hoofdtitel' },
                        { value: 'h2', label: 'H2 - Sectietitel' },
                        { value: 'h3', label: 'H3 - Subtitel' },
                        { value: 'h4', label: 'H4 - Klein' }
                    ]})}
                    ${field('Uitlijning', 'select', 'align', { options: [
                        { value: 'left', label: 'Links' },
                        { value: 'center', label: 'Midden' },
                        { value: 'right', label: 'Rechts' }
                    ]})}
                `,

                text: () => `
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tekst</label>
                        <div class="border rounded p-2 bg-white min-h-[150px]" contenteditable="true"
                             @blur="updateBlockData('html', $event.target.innerHTML)">${b.data.html || ''}</div>
                        <p class="text-xs text-gray-400 mt-1">Tip: selecteer tekst om op te maken</p>
                    </div>
                `,

                image: () => `
                    ${field('Afbeelding', 'image', 'src')}
                    ${field('Alt tekst', 'text', 'alt')}
                    ${field('Onderschrift', 'text', 'caption')}
                    ${field('Breedte', 'select', 'width', { options: [
                        { value: '100%', label: 'Volledige breedte' },
                        { value: '75%', label: '75%' },
                        { value: '50%', label: '50%' },
                        { value: '25%', label: '25%' }
                    ]})}
                `,

                button: () => `
                    ${field('Tekst', 'text', 'text')}
                    ${field('Link URL', 'url', 'url')}
                    ${field('Stijl', 'select', 'style', { options: [
                        { value: 'primary', label: 'Primair (gevuld)' },
                        { value: 'secondary', label: 'Secundair (grijs)' },
                        { value: 'outline', label: 'Outline (rand)' }
                    ]})}
                    ${field('Uitlijning', 'select', 'align', { options: [
                        { value: 'left', label: 'Links' },
                        { value: 'center', label: 'Midden' },
                        { value: 'right', label: 'Rechts' }
                    ]})}
                `,

                video: () => `
                    ${field('Video URL', 'url', 'url')}
                    <p class="text-xs text-gray-400">YouTube of Vimeo URL</p>
                    ${field('Titel', 'text', 'title')}
                `,

                divider: () => `
                    ${field('Stijl', 'select', 'style', { options: [
                        { value: 'solid', label: 'Doorgetrokken' },
                        { value: 'dashed', label: 'Gestreept' },
                        { value: 'dotted', label: 'Gestippeld' }
                    ]})}
                    ${field('Kleur', 'color', 'color')}
                `,

                hero: () => `
                    ${field('Titel', 'text', 'title')}
                    ${field('Subtitel', 'text', 'subtitle')}
                    ${field('Achtergrond', 'image', 'bgImage')}
                    <div>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" ${b.data.overlay ? 'checked' : ''}
                                   @change="updateBlockData('overlay', $event.target.checked)">
                            Donkere overlay
                        </label>
                    </div>
                    <div class="border-t pt-4 mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Buttons</label>
                        <div class="space-y-2" id="hero-buttons">
                            ${(b.data.buttons || []).map((btn, i) => `
                                <div class="flex gap-2 items-center bg-gray-50 p-2 rounded">
                                    <input type="text" value="${this.escapeHtml(btn.text)}" placeholder="Tekst"
                                           @input="updateHeroButton(${i}, 'text', $event.target.value)"
                                           class="flex-1 border rounded px-2 py-1 text-sm">
                                    <input type="url" value="${this.escapeHtml(btn.url || '')}" placeholder="URL"
                                           @input="updateHeroButton(${i}, 'url', $event.target.value)"
                                           class="flex-1 border rounded px-2 py-1 text-sm">
                                    <button type="button" @click="removeHeroButton(${i})" class="text-red-500 p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                            `).join('')}
                            <button type="button" @click="addHeroButton()" class="text-blue-600 text-sm">+ Button toevoegen</button>
                        </div>
                    </div>
                `,

                cards: () => `
                    <div class="space-y-3">
                        ${(b.data.cards || []).map((card, i) => `
                            <div class="border rounded p-3 bg-gray-50">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium">Kaart ${i + 1}</span>
                                    <button type="button" @click="removeCard(${i})" class="text-red-500 text-sm">Verwijderen</button>
                                </div>
                                <input type="text" value="${this.escapeHtml(card.title)}" placeholder="Titel"
                                       @input="updateCard(${i}, 'title', $event.target.value)"
                                       class="w-full border rounded px-2 py-1 text-sm mb-2">
                                <textarea placeholder="Beschrijving" rows="2"
                                          @input="updateCard(${i}, 'text', $event.target.value)"
                                          class="w-full border rounded px-2 py-1 text-sm">${this.escapeHtml(card.text)}</textarea>
                            </div>
                        `).join('')}
                        <button type="button" @click="addCard()" class="text-blue-600 text-sm">+ Kaart toevoegen</button>
                    </div>
                `,

                features: () => `
                    ${field('Titel', 'text', 'title')}
                    <div class="space-y-3 mt-4">
                        ${(b.data.features || []).map((feat, i) => `
                            <div class="border rounded p-3 bg-gray-50">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium">Feature ${i + 1}</span>
                                    <button type="button" @click="removeFeature(${i})" class="text-red-500 text-sm">Verwijderen</button>
                                </div>
                                <input type="text" value="${this.escapeHtml(feat.title)}" placeholder="Titel"
                                       @input="updateFeature(${i}, 'title', $event.target.value)"
                                       class="w-full border rounded px-2 py-1 text-sm mb-2">
                                <input type="text" value="${this.escapeHtml(feat.description)}" placeholder="Beschrijving"
                                       @input="updateFeature(${i}, 'description', $event.target.value)"
                                       class="w-full border rounded px-2 py-1 text-sm">
                            </div>
                        `).join('')}
                        <button type="button" @click="addFeature()" class="text-blue-600 text-sm">+ Feature toevoegen</button>
                    </div>
                `,

                cta: () => `
                    ${field('Titel', 'text', 'title')}
                    ${field('Tekst', 'text', 'text')}
                    ${field('Button tekst', 'text', 'buttonText')}
                    ${field('Button URL', 'url', 'buttonUrl')}
                    ${field('Achtergrondkleur', 'color', 'bgColor')}
                `,

                timeline: () => `
                    ${field('Titel', 'text', 'title')}
                    <div class="space-y-3 mt-4">
                        ${(b.data.items || []).map((item, i) => `
                            <div class="border rounded p-3 bg-gray-50">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium">Item ${i + 1}</span>
                                    <button type="button" @click="removeTimelineItem(${i})" class="text-red-500 text-sm">Verwijderen</button>
                                </div>
                                <div class="grid grid-cols-2 gap-2 mb-2">
                                    <input type="text" value="${this.escapeHtml(item.time)}" placeholder="Tijd (bijv. 09:00)"
                                           @input="updateTimelineItem(${i}, 'time', $event.target.value)"
                                           class="border rounded px-2 py-1 text-sm">
                                    <input type="text" value="${this.escapeHtml(item.title)}" placeholder="Titel"
                                           @input="updateTimelineItem(${i}, 'title', $event.target.value)"
                                           class="border rounded px-2 py-1 text-sm">
                                </div>
                                <input type="text" value="${this.escapeHtml(item.description)}" placeholder="Beschrijving"
                                       @input="updateTimelineItem(${i}, 'description', $event.target.value)"
                                       class="w-full border rounded px-2 py-1 text-sm">
                            </div>
                        `).join('')}
                        <button type="button" @click="addTimelineItem()" class="text-blue-600 text-sm">+ Item toevoegen</button>
                    </div>
                `,

                faq: () => `
                    ${field('Titel', 'text', 'title')}
                    <div class="space-y-3 mt-4">
                        ${(b.data.items || []).map((item, i) => `
                            <div class="border rounded p-3 bg-gray-50">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium">Vraag ${i + 1}</span>
                                    <button type="button" @click="removeFaqItem(${i})" class="text-red-500 text-sm">Verwijderen</button>
                                </div>
                                <input type="text" value="${this.escapeHtml(item.question)}" placeholder="Vraag"
                                       @input="updateFaqItem(${i}, 'question', $event.target.value)"
                                       class="w-full border rounded px-2 py-1 text-sm mb-2">
                                <textarea placeholder="Antwoord" rows="2"
                                          @input="updateFaqItem(${i}, 'answer', $event.target.value)"
                                          class="w-full border rounded px-2 py-1 text-sm">${this.escapeHtml(item.answer)}</textarea>
                            </div>
                        `).join('')}
                        <button type="button" @click="addFaqItem()" class="text-blue-600 text-sm">+ Vraag toevoegen</button>
                    </div>
                `,

                sponsors: () => `
                    <div class="space-y-3">
                        ${(b.data.sponsors || []).map((sponsor, i) => `
                            <div class="border rounded p-3 bg-gray-50">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-sm font-medium">Sponsor ${i + 1}</span>
                                    <button type="button" @click="removeSponsor(${i})" class="text-red-500 text-sm">Verwijderen</button>
                                </div>
                                ${sponsor.logo ? `<img src="/storage/${sponsor.logo}" class="h-12 object-contain mb-2">` : ''}
                                <input type="file" accept="image/*"
                                       @change="uploadSponsorLogo($event, ${i})"
                                       class="w-full text-sm text-gray-500 mb-2">
                                <input type="text" value="${this.escapeHtml(sponsor.naam)}" placeholder="Naam"
                                       @input="updateSponsor(${i}, 'naam', $event.target.value)"
                                       class="w-full border rounded px-2 py-1 text-sm mb-2">
                                <input type="url" value="${this.escapeHtml(sponsor.url || '')}" placeholder="Website URL"
                                       @input="updateSponsor(${i}, 'url', $event.target.value)"
                                       class="w-full border rounded px-2 py-1 text-sm">
                            </div>
                        `).join('')}
                        <button type="button" @click="addSponsorItem()" class="text-blue-600 text-sm">+ Sponsor toevoegen</button>
                    </div>
                `,

                countdown: () => `
                    ${field('Titel', 'text', 'title')}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Doeldatum</label>
                        <input type="date" value="${b.data.targetDate || ''}"
                               @change="updateBlockData('targetDate', $event.target.value)"
                               class="w-full border rounded px-3 py-2 text-sm">
                    </div>
                `,

                map: () => `
                    ${field('Adres', 'text', 'address')}
                    ${field('Hoogte', 'select', 'height', { options: [
                        { value: '200px', label: 'Klein (200px)' },
                        { value: '300px', label: 'Medium (300px)' },
                        { value: '400px', label: 'Groot (400px)' },
                        { value: '500px', label: 'Extra groot (500px)' }
                    ]})}
                `,

                contact: () => `
                    ${field('E-mail', 'text', 'email')}
                    ${field('Telefoon', 'text', 'phone')}
                `,

                social: () => `
                    ${field('Facebook URL', 'url', 'facebook')}
                    ${field('Instagram URL', 'url', 'instagram')}
                    ${field('Twitter/X URL', 'url', 'twitter')}
                    ${field('YouTube URL', 'url', 'youtube')}
                `,

                info_card: () => `
                    <p class="text-sm text-gray-500">Dit blok toont automatisch de toernooi informatie uit de instellingen.</p>
                `,

                schedule: () => `
                    <p class="text-sm text-gray-500">Dit blok toont automatisch het dagprogramma.</p>
                `
            };

            return settings[block.type] ? settings[block.type]() : `<p class="text-sm text-gray-500">Geen instellingen beschikbaar</p>`;
        },

        // Helper Methods
        escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        },

        getEmbedUrl(url) {
            if (!url) return '';
            // YouTube
            const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&]+)/);
            if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`;
            // Vimeo
            const vimeoMatch = url.match(/vimeo\.com\/(\d+)/);
            if (vimeoMatch) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
            return url;
        },

        // Templates
        loadTemplate(name) {
            if (this.sections.length > 0 && !confirm('Dit vervangt de huidige inhoud. Doorgaan?')) return;

            const templates = {
                'judo-basic': [
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'hero', data: this.getBlockDefaults('hero') }] }],
                        settings: { bgColor: 'transparent', padding: 'py-0 px-0', textColor: '#ffffff' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'info_card', data: {} }] }],
                        settings: { bgColor: '#ffffff', padding: 'py-12 px-6', textColor: '#1f2937' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'timeline', data: this.getBlockDefaults('timeline') }] }],
                        settings: { bgColor: '#f8fafc', padding: 'py-12 px-6', textColor: '#1f2937' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'sponsors', data: { sponsors: [] } }] }],
                        settings: { bgColor: '#ffffff', padding: 'py-12 px-6', textColor: '#1f2937' }
                    }
                ],
                'judo-pro': [
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'hero', data: this.getBlockDefaults('hero') }] }],
                        settings: { bgColor: 'transparent', padding: 'py-0 px-0', textColor: '#ffffff' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'countdown', data: this.getBlockDefaults('countdown') }] }],
                        settings: { bgColor: '#1e3a5f', padding: 'py-8 px-6', textColor: '#ffffff' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'two-cols',
                        columns: [
                            { blocks: [{ id: this.generateId(), type: 'info_card', data: {} }] },
                            { blocks: [{ id: this.generateId(), type: 'map', data: this.getBlockDefaults('map') }] }
                        ],
                        settings: { bgColor: '#ffffff', padding: 'py-12 px-6', textColor: '#1f2937' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'timeline', data: this.getBlockDefaults('timeline') }] }],
                        settings: { bgColor: '#f8fafc', padding: 'py-12 px-6', textColor: '#1f2937' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'faq', data: this.getBlockDefaults('faq') }] }],
                        settings: { bgColor: '#ffffff', padding: 'py-12 px-6', textColor: '#1f2937' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'cta', data: this.getBlockDefaults('cta') }] }],
                        settings: { bgColor: 'transparent', padding: 'py-0 px-6', textColor: '#1f2937' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'sponsors', data: { sponsors: [] } }] }],
                        settings: { bgColor: '#f8fafc', padding: 'py-12 px-6', textColor: '#1f2937' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'social', data: this.getBlockDefaults('social') }] }],
                        settings: { bgColor: '#1f2937', padding: 'py-8 px-6', textColor: '#ffffff' }
                    }
                ],
                'minimal': [
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [
                            { id: this.generateId(), type: 'heading', data: { text: '{{ $toernooi->naam }}', level: 'h1', align: 'center' } },
                            { id: this.generateId(), type: 'text', data: { html: '<p style="text-align: center;">Welkom bij ons toernooi</p>' } }
                        ] }],
                        settings: { bgColor: '#ffffff', padding: 'py-20 px-6', textColor: '#1f2937' }
                    },
                    {
                        id: this.generateId(),
                        layout: 'full',
                        columns: [{ blocks: [{ id: this.generateId(), type: 'info_card', data: {} }] }],
                        settings: { bgColor: '#f8fafc', padding: 'py-12 px-6', textColor: '#1f2937' }
                    }
                ]
            };

            this.sections = templates[name] || [];
            this.$nextTick(() => this.initSortable());
            this.saveData();
        },

        clearAll() {
            if (confirm('Weet je zeker dat je alles wilt wissen?')) {
                this.sections = [];
                this.saveData();
            }
        },

        // Upload Methods
        async uploadSectionBg(event) {
            const file = event.target.files[0];
            if (!file || !this.editingSection) return;

            const formData = new FormData();
            formData.append('afbeelding', file);

            try {
                const response = await fetch('{{ route("toernooi.pagina-builder.upload", $toernooi) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    this.editingSection.settings.bgImage = data.path;
                    this.saveData();
                }
            } catch (error) {
                console.error('Upload failed:', error);
            }
        },

        // Drag & Drop
        onDragOver(event) {
            event.preventDefault();
        },

        onDrop(event, sectionId, colIndex) {
            event.preventDefault();
            // Handle drop from palette
        },

        // Block Data Update Methods
        updateBlockData(key, value) {
            if (this.editingBlock) {
                this.editingBlock.data[key] = value;
                this.debouncedSave();
            }
        },

        debouncedSave() {
            if (this.saveTimeout) clearTimeout(this.saveTimeout);
            this.saveTimeout = setTimeout(() => this.saveData(), 500);
        },

        async uploadBlockImage(event, dataKey) {
            const file = event.target.files[0];
            if (!file || !this.editingBlock) return;

            const formData = new FormData();
            formData.append('afbeelding', file);

            try {
                const response = await fetch('{{ route("toernooi.pagina-builder.upload", $toernooi) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    this.editingBlock.data[dataKey] = data.path;
                    this.saveData();
                }
            } catch (error) {
                console.error('Upload failed:', error);
                alert('Upload mislukt');
            }
        },

        // Hero button methods
        updateHeroButton(index, key, value) {
            if (this.editingBlock && this.editingBlock.data.buttons) {
                this.editingBlock.data.buttons[index][key] = value;
                this.debouncedSave();
            }
        },

        addHeroButton() {
            if (this.editingBlock) {
                if (!this.editingBlock.data.buttons) this.editingBlock.data.buttons = [];
                this.editingBlock.data.buttons.push({ text: 'Nieuwe button', url: '#', style: 'primary' });
                this.saveData();
            }
        },

        removeHeroButton(index) {
            if (this.editingBlock && this.editingBlock.data.buttons) {
                this.editingBlock.data.buttons.splice(index, 1);
                this.saveData();
            }
        },

        // Cards methods
        updateCard(index, key, value) {
            if (this.editingBlock && this.editingBlock.data.cards) {
                this.editingBlock.data.cards[index][key] = value;
                this.debouncedSave();
            }
        },

        addCard() {
            if (this.editingBlock) {
                if (!this.editingBlock.data.cards) this.editingBlock.data.cards = [];
                this.editingBlock.data.cards.push({ title: 'Nieuwe kaart', text: 'Beschrijving', icon: 'star' });
                this.saveData();
            }
        },

        removeCard(index) {
            if (this.editingBlock && this.editingBlock.data.cards) {
                this.editingBlock.data.cards.splice(index, 1);
                this.saveData();
            }
        },

        // Features methods
        updateFeature(index, key, value) {
            if (this.editingBlock && this.editingBlock.data.features) {
                this.editingBlock.data.features[index][key] = value;
                this.debouncedSave();
            }
        },

        addFeature() {
            if (this.editingBlock) {
                if (!this.editingBlock.data.features) this.editingBlock.data.features = [];
                this.editingBlock.data.features.push({ title: 'Nieuwe feature', description: 'Beschrijving', icon: 'check' });
                this.saveData();
            }
        },

        removeFeature(index) {
            if (this.editingBlock && this.editingBlock.data.features) {
                this.editingBlock.data.features.splice(index, 1);
                this.saveData();
            }
        },

        // Timeline methods
        updateTimelineItem(index, key, value) {
            if (this.editingBlock && this.editingBlock.data.items) {
                this.editingBlock.data.items[index][key] = value;
                this.debouncedSave();
            }
        },

        addTimelineItem() {
            if (this.editingBlock) {
                if (!this.editingBlock.data.items) this.editingBlock.data.items = [];
                this.editingBlock.data.items.push({ time: '00:00', title: 'Nieuw item', description: '' });
                this.saveData();
            }
        },

        removeTimelineItem(index) {
            if (this.editingBlock && this.editingBlock.data.items) {
                this.editingBlock.data.items.splice(index, 1);
                this.saveData();
            }
        },

        // FAQ methods
        updateFaqItem(index, key, value) {
            if (this.editingBlock && this.editingBlock.data.items) {
                this.editingBlock.data.items[index][key] = value;
                this.debouncedSave();
            }
        },

        addFaqItem() {
            if (this.editingBlock) {
                if (!this.editingBlock.data.items) this.editingBlock.data.items = [];
                this.editingBlock.data.items.push({ question: 'Nieuwe vraag?', answer: 'Antwoord hier...' });
                this.saveData();
            }
        },

        removeFaqItem(index) {
            if (this.editingBlock && this.editingBlock.data.items) {
                this.editingBlock.data.items.splice(index, 1);
                this.saveData();
            }
        },

        // Sponsor methods
        updateSponsor(index, key, value) {
            if (this.editingBlock && this.editingBlock.data.sponsors) {
                this.editingBlock.data.sponsors[index][key] = value;
                this.debouncedSave();
            }
        },

        addSponsorItem() {
            if (this.editingBlock) {
                if (!this.editingBlock.data.sponsors) this.editingBlock.data.sponsors = [];
                this.editingBlock.data.sponsors.push({ naam: '', logo: null, url: '' });
                this.saveData();
            }
        },

        removeSponsor(index) {
            if (this.editingBlock && this.editingBlock.data.sponsors) {
                this.editingBlock.data.sponsors.splice(index, 1);
                this.saveData();
            }
        },

        async uploadSponsorLogo(event, index) {
            const file = event.target.files[0];
            if (!file || !this.editingBlock) return;

            const formData = new FormData();
            formData.append('afbeelding', file);

            try {
                const response = await fetch('{{ route("toernooi.pagina-builder.upload", $toernooi) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: formData
                });
                const data = await response.json();
                if (data.success) {
                    if (!this.editingBlock.data.sponsors) this.editingBlock.data.sponsors = [];
                    this.editingBlock.data.sponsors[index].logo = data.path;
                    this.saveData();
                }
            } catch (error) {
                console.error('Upload failed:', error);
                alert('Upload mislukt');
            }
        },

        // Save Methods
        async saveData() {
            this.saving = true;
            this.saved = false;

            try {
                const response = await fetch('{{ route("toernooi.pagina-builder.opslaan", $toernooi) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        sections: this.sections,
                        header: this.headerSection,
                        footer: this.footerSection,
                        themeColor: this.themeColor
                    })
                });

                const data = await response.json();
                if (data.success) {
                    this.saved = true;
                    setTimeout(() => this.saved = false, 2000);
                }
            } catch (error) {
                console.error('Save failed:', error);
            }

            this.saving = false;
        },

        async saveSettings() {
            // Save theme color
            await this.saveData();
        }
    }
}

// Countdown component
function countdown(targetDate) {
    return {
        days: 0,
        hours: 0,
        minutes: 0,
        seconds: 0,
        interval: null,

        start() {
            this.update();
            this.interval = setInterval(() => this.update(), 1000);
        },

        update() {
            const target = new Date(targetDate).getTime();
            const now = new Date().getTime();
            const diff = target - now;

            if (diff > 0) {
                this.days = Math.floor(diff / (1000 * 60 * 60 * 24));
                this.hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                this.minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                this.seconds = Math.floor((diff % (1000 * 60)) / 1000);
            } else {
                this.days = this.hours = this.minutes = this.seconds = 0;
            }
        }
    }
}
</script>
@endsection
