<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $club->naam }} - {{ __('Weegkaarten') }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto py-8 px-4">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ $club->naam }}</h1>
                    <p class="text-gray-600">{{ $toernooi->naam }} - {{ $toernooi->datum->format('d F Y') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    @include('partials.coach-locale-switcher')
                    <form action="{{ route('coach.portal.logout', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}" method="POST">
                        @csrf
                        <button type="submit" class="text-gray-600 hover:text-gray-800">{{ __('Uitloggen') }}</button>
                    </form>
                </div>
            </div>

            <!-- Navigation tabs -->
            <div class="mt-4 flex space-x-4 border-t pt-4 overflow-x-auto">
                <a href="{{ route('coach.portal.judokas', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    {{ __('Judoka\'s') }}
                </a>
                <a href="{{ route('coach.portal.coachkaarten', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    {{ __('Coach Kaarten') }}
                </a>
                <a href="{{ route('coach.portal.weegkaarten', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
                   class="text-blue-600 font-medium border-b-2 border-blue-600 px-3 py-1 whitespace-nowrap">
                    {{ __('Weegkaarten') }}
                </a>
                <a href="{{ route('coach.portal.resultaten', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}"
                   class="text-gray-600 hover:text-gray-800 px-3 py-1 whitespace-nowrap">
                    {{ __('Resultaten') }}
                </a>
            </div>
        </div>

        <!-- Info box -->
        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded mb-6">
            <p class="font-medium">{{ __('Weegkaarten voor je judoka\'s') }}</p>
            <p class="text-sm mt-1">{{ __('Stuur deze links naar je judoka\'s zodat zij hun weegkaart kunnen tonen bij de weging. Elke judoka heeft een unieke QR-code.') }}</p>
        </div>

        @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
        @endif

        <!-- Weegkaarten List -->
        <div class="bg-white rounded-lg shadow overflow-hidden" x-data="{ copiedId: null, search: '' }">
            <div class="px-6 py-4 border-b bg-gray-50">
                <div class="flex justify-between items-center">
                    <h2 class="text-xl font-bold text-gray-800">{{ __('Weegkaarten') }} ({{ $judokas->count() }})</h2>
                </div>
                <div class="mt-2">
                    <input type="text" x-model="search" placeholder="{{ __('Zoek judoka op naam...') }}"
                           class="w-full border rounded px-3 py-2 text-sm" />
                </div>
            </div>

            @if($judokas->count() > 0)
            <div class="divide-y">
                @foreach($judokas as $judoka)
                @php
                    $weegkaartUrl = route('weegkaart.show', $judoka->qr_code);
                    $blok = $judoka->poules->first()?->blok;
                @endphp
                <div class="p-4 hover:bg-gray-50" x-data="{ showQr: false }"
                     x-show="search === '' || '{{ strtolower($judoka->naam) }}'.includes(search.toLowerCase())">
                    <div class="flex justify-between items-start gap-4">
                        <!-- QR Code (clickable) -->
                        <div class="cursor-pointer shrink-0" @click="showQr = !showQr" title="{{ __('Klik voor grote QR') }}">
                            <canvas id="qr-weeg-{{ $judoka->id }}" class="w-14 h-14"></canvas>
                        </div>

                        <div class="flex-grow min-w-0">
                            <p class="font-medium text-gray-800">{{ $judoka->naam }}</p>
                            <p class="text-sm text-gray-600">
                                @if($judoka->gewichtsklasse && $judoka->gewichtsklasse !== 'Variabel'){{ $judoka->gewichtsklasse }} kg | @elseif($judoka->gewicht){{ $judoka->gewicht }} kg | @endif{{ \App\Enums\Band::toKleur($judoka->band) }} |
                                {{ $judoka->leeftijdsklasse }}
                            </p>
                            @if($toernooi->voorbereiding_klaar_op && $blok)
                            <p class="text-sm text-amber-600 mt-1">
                                @if($toernooi->blokken()->count() > 1){{ $blok->naam }} - @endif
                                @if($blok->weging_start && $blok->weging_einde)
                                    {{ __('Weging:') }} {{ $blok->weging_start->format('H:i') }} - {{ $blok->weging_einde->format('H:i') }}
                                @endif
                            </p>
                            @elseif(!$toernooi->voorbereiding_klaar_op)
                            <p class="text-sm text-gray-400 mt-1">{{ __('Indeling wordt later bekendgemaakt') }}</p>
                            @else
                            <p class="text-sm text-gray-400 mt-1">{{ __('Nog niet ingedeeld') }}</p>
                            @endif
                        </div>

                        <!-- Action buttons -->
                        <div class="flex items-center space-x-2 shrink-0">
                            <!-- View link -->
                            <a href="{{ $weegkaartUrl }}?from_portal" target="_blank"
                               class="inline-flex items-center px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                {{ __('Bekijk') }}
                            </a>

                            <!-- Copy link button -->
                            <button
                                @click="
                                    navigator.clipboard.writeText('{{ $weegkaartUrl }}');
                                    copiedId = {{ $judoka->id }};
                                    setTimeout(() => copiedId = null, 2000);
                                "
                                class="inline-flex items-center px-3 py-1.5 bg-blue-100 hover:bg-blue-200 text-blue-700 rounded text-sm"
                            >
                                <template x-if="copiedId !== {{ $judoka->id }}">
                                    <span class="flex items-center">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                                        </svg>
                                        {{ __('Kopieer') }}
                                    </span>
                                </template>
                                <template x-if="copiedId === {{ $judoka->id }}">
                                    <span class="flex items-center text-green-700">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        {{ __('Gekopieerd!') }}
                                    </span>
                                </template>
                            </button>

                            <!-- WhatsApp share -->
                            <a href="https://wa.me/?text={{ urlencode("Hoi {$judoka->naam}! Hier is je weegkaart voor {$toernooi->naam}: {$weegkaartUrl}") }}"
                               target="_blank"
                               class="inline-flex items-center px-3 py-1.5 bg-green-100 hover:bg-green-200 text-green-700 rounded text-sm">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                WhatsApp
                            </a>
                        </div>
                    </div>

                    <!-- Grote QR popup -->
                    <div x-show="showQr" x-collapse class="mt-4 pt-4 border-t text-center">
                        <p class="text-sm text-gray-600 mb-2">{{ __('Laat :naam deze QR scannen voor de weegkaart', ['naam' => $judoka->naam]) }}</p>
                        <canvas id="qr-weeg-large-{{ $judoka->id }}" class="mx-auto"></canvas>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-8 text-center text-gray-500">
                {{ __('Nog geen judoka\'s opgegeven.') }}
                <a href="{{ route('coach.portal.judokas', ['organisator' => $organisator, 'toernooi' => $toernooiSlug, 'code' => $code]) }}" class="text-blue-600 hover:underline">
                    {{ __('Voeg eerst judoka\'s toe.') }}
                </a>
            </div>
            @endif
        </div>

        <!-- Bulk actions -->
        @if($judokas->count() > 0)
        <div class="mt-6 bg-white rounded-lg shadow p-4">
            <h3 class="font-medium text-gray-800 mb-3">{{ __('Alle weegkaarten delen') }}</h3>
            <div class="flex flex-wrap gap-2">
                <button
                    onclick="copyAllLinks()"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm font-medium"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
                    </svg>
                    {{ __('Kopieer alle links') }}
                </button>
            </div>
        </div>
        @endif

        <!-- Footer -->
        <div class="mt-6 text-center text-sm text-gray-500">
            <p>{{ __('Stuur de weegkaarten naar je judoka\'s via WhatsApp of kopieer de link.') }}</p>
        </div>
    </div>

    <script>
        function copyAllLinks() {
            const links = [
                @foreach($judokas as $judoka)
                "{{ $judoka->naam }}: {{ route('weegkaart.show', $judoka->qr_code) }}",
                @endforeach
            ];

            const text = "Weegkaarten {{ $toernooi->naam }}\n\n" + links.join("\n");
            navigator.clipboard.writeText(text).then(() => {
                alert('{{ __('Alle links gekopieerd naar klembord!') }}');
            });
        }

        // Generate QR codes for weegkaarten
        document.addEventListener('DOMContentLoaded', function() {
            @foreach($judokas as $judoka)
            // Small QR
            QRCode.toCanvas(document.getElementById('qr-weeg-{{ $judoka->id }}'), '{{ route('weegkaart.show', $judoka->qr_code) }}', {
                width: 56,
                margin: 0,
                color: { dark: '#1d4ed8' }
            });
            // Large QR
            QRCode.toCanvas(document.getElementById('qr-weeg-large-{{ $judoka->id }}'), '{{ route('weegkaart.show', $judoka->qr_code) }}', {
                width: 200,
                margin: 1,
                color: { dark: '#1d4ed8' }
            });
            @endforeach
        });
    </script>
</body>
</html>
