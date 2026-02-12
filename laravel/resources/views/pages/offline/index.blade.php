<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $toernooi->naam }} - Offline Server</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Fallback als CDN niet beschikbaar (offline!) */
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f3f4f6; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-4xl mx-auto py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">{{ $toernooi->naam }}</h1>
                    <p class="text-gray-500 mt-1">
                        {{ $toernooi->datum?->format('d-m-Y') }}
                        @if($toernooi->locatie) &middot; {{ $toernooi->locatie }} @endif
                    </p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                        OFFLINE SERVER
                    </span>
                </div>
            </div>
        </div>

        <!-- Statistieken -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-blue-600">{{ $stats['judokas'] }}</div>
                <div class="text-sm text-gray-500">Judoka's</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-purple-600">{{ $stats['poules'] }}</div>
                <div class="text-sm text-gray-500">Poules</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-green-600">{{ $stats['wedstrijden_gespeeld'] }}</div>
                <div class="text-sm text-gray-500">Gespeeld</div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <div class="text-2xl font-bold text-gray-600">{{ $stats['wedstrijden_totaal'] }}</div>
                <div class="text-sm text-gray-500">Totaal wedstrijden</div>
            </div>
        </div>

        <!-- Mat Selectie -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Matten</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @foreach($matten as $mat)
                <a href="/{{ $toernooi->organisator?->slug ?? 'offline' }}/{{ $toernooi->slug }}/mat/{{ $mat->id }}"
                   class="block p-6 bg-blue-50 border-2 border-blue-200 rounded-lg hover:bg-blue-100 hover:border-blue-400 transition text-center">
                    <div class="text-3xl font-bold text-blue-700">Mat {{ $mat->nummer }}</div>
                    @if($mat->naam)
                    <div class="text-sm text-blue-500 mt-1">{{ $mat->naam }}</div>
                    @endif
                </a>
                @endforeach
            </div>
        </div>

        <!-- Snelkoppelingen -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Interfaces</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="/{{ $toernooi->organisator?->slug ?? 'offline' }}/{{ $toernooi->slug }}/weging"
                   class="block p-4 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition">
                    <h3 class="font-bold text-amber-800">Weging</h3>
                    <p class="text-sm text-amber-600">Gewicht registreren per judoka</p>
                </a>

                <a href="/{{ $toernooi->organisator?->slug ?? 'offline' }}/{{ $toernooi->slug }}"
                   class="block p-4 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition">
                    <h3 class="font-bold text-indigo-800">Publiek scherm</h3>
                    <p class="text-sm text-indigo-600">Live scorebord voor publiek</p>
                </a>
            </div>
        </div>

        <!-- Upload Resultaten -->
        <div class="bg-white rounded-lg shadow p-6 mb-6" x-data="uploadManager()">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Resultaten uploaden naar cloud</h2>
            <p class="text-sm text-gray-600 mb-4">
                Na het toernooi, als er weer internet is, kun je alle resultaten uploaden naar judotournament.org.
            </p>

            <div x-show="status === 'idle'">
                <button @click="upload()" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium">
                    Upload resultaten naar cloud
                </button>
            </div>

            <div x-show="status === 'uploading'" class="text-blue-600">
                Bezig met uploaden...
            </div>

            <div x-show="status === 'success'" class="text-green-600 font-medium">
                <span x-text="message"></span>
            </div>

            <div x-show="status === 'error'" class="text-red-600">
                <p class="font-medium">Upload mislukt</p>
                <p class="text-sm" x-text="message"></p>
                <button @click="upload()" class="mt-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm">
                    Opnieuw proberen
                </button>
            </div>
        </div>

        <script>
            function uploadManager() {
                return {
                    status: 'idle',
                    message: '',

                    async upload() {
                        this.status = 'uploading';

                        try {
                            // First, get results from local database
                            const localRes = await fetch('/offline/export-resultaten');
                            if (!localRes.ok) throw new Error('Kan lokale resultaten niet ophalen');
                            const data = await localRes.json();

                            if (data.count === 0) {
                                this.status = 'success';
                                this.message = 'Geen gespeelde wedstrijden om te uploaden.';
                                return;
                            }

                            // Upload to cloud
                            const cloudUrl = prompt(
                                'Voer de cloud server URL in:',
                                'https://judotournament.org'
                            );

                            if (!cloudUrl) {
                                this.status = 'idle';
                                return;
                            }

                            const uploadRes = await fetch(cloudUrl + '/noodplan/upload-resultaten', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ resultaten: data.resultaten }),
                            });

                            if (!uploadRes.ok) throw new Error('Server antwoordde met status ' + uploadRes.status);
                            const result = await uploadRes.json();

                            this.status = 'success';
                            this.message = `${result.synced} wedstrijden geupload, ${result.skipped} overgeslagen (al op server).`;
                        } catch (e) {
                            this.status = 'error';
                            this.message = e.message;
                        }
                    }
                };
            }
        </script>

        <!-- Footer -->
        <div class="text-center text-sm text-gray-400 mt-8">
            JudoToernooi Offline Server &middot; judotournament.org
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js" defer></script>
</body>
</html>
