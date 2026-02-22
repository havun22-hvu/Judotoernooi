<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Dashboard') }} - {{ __('JudoToernooi') }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-white shadow-sm" x-data="{ mobileOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-gray-800">{{ __('JudoToernooi') }}</h1>
                </div>

                {{-- Desktop menu --}}
                <div class="hidden sm:flex items-center space-x-4">
                    @if($organisator->isSitebeheerder())
                    <a href="{{ route('admin.index') }}" class="text-purple-600 hover:text-purple-800 font-medium">{{ __('Alle Organisatoren') }}</a>
                    @endif
                    {{-- Taalkiezer --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" @click.away="open = false" class="text-gray-500 hover:text-gray-700 text-sm focus:outline-none" title="{{ __('Taal') }}">
                            @include('partials.flag-icon', ['lang' => app()->getLocale()])
                            <svg class="ml-1 w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition class="absolute right-0 mt-2 w-40 bg-white rounded-lg shadow-lg py-1 z-50">
                            <form action="{{ route('locale.switch', 'nl') }}" method="POST">
                                @csrf
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'nl' ? 'font-bold' : '' }}">
                                    @include('partials.flag-icon', ['lang' => 'nl']) Nederlands
                                </button>
                            </form>
                            <form action="{{ route('locale.switch', 'en') }}" method="POST">
                                @csrf
                                <button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-gray-700 hover:bg-gray-100 {{ app()->getLocale() === 'en' ? 'font-bold' : '' }}">
                                    @include('partials.flag-icon', ['lang' => 'en']) English
                                </button>
                            </form>
                        </div>
                    </div>
                    <span class="text-gray-600">{{ $organisator->naam }}</span>
                    @if($organisator->isSitebeheerder())
                    <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-1 rounded">{{ __('Sitebeheerder') }}</span>
                    @endif
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-600 hover:text-gray-800">
                            {{ __('Uitloggen') }}
                        </button>
                    </form>
                </div>

                {{-- Hamburger button (mobile) --}}
                <div class="flex items-center sm:hidden">
                    <button @click="mobileOpen = !mobileOpen" class="text-gray-600 hover:text-gray-800 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            <path x-show="mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile menu --}}
        <div x-show="mobileOpen" x-transition class="sm:hidden border-t bg-white">
            <div class="px-4 py-3 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-gray-600 font-medium">{{ $organisator->naam }}</span>
                    @if($organisator->isSitebeheerder())
                    <span class="bg-purple-100 text-purple-800 text-xs font-medium px-2 py-1 rounded">{{ __('Sitebeheerder') }}</span>
                    @endif
                </div>
                @if($organisator->isSitebeheerder())
                <a href="{{ route('admin.index') }}" class="block text-purple-600 hover:text-purple-800 font-medium">{{ __('Alle Organisatoren') }}</a>
                @endif
                <div class="flex items-center gap-3">
                    <span class="text-gray-500 text-sm">{{ __('Taal') }}:</span>
                    <form action="{{ route('locale.switch', 'nl') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="flex items-center gap-1 {{ app()->getLocale() === 'nl' ? 'font-bold' : 'opacity-60' }}">
                            @include('partials.flag-icon', ['lang' => 'nl']) <span class="text-sm">Nederlands</span>
                        </button>
                    </form>
                    <form action="{{ route('locale.switch', 'en') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="flex items-center gap-1 {{ app()->getLocale() === 'en' ? 'font-bold' : 'opacity-60' }}">
                            @include('partials.flag-icon', ['lang' => 'en']) <span class="text-sm">English</span>
                        </button>
                    </form>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-red-600 hover:text-red-800 font-medium">{{ __('Uitloggen') }}</button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            {{ session('success') }}
        </div>
        @endif

        <div class="mb-6 flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-800">
                @if($organisator->isSitebeheerder())
                    {{ __('Alle Toernooien') }}
                @else
                    {{ __('Mijn Toernooien') }}
                @endif
            </h2>
            {{-- DO NOT REMOVE: Action buttons - Wimpeltoernooi, Mijn Judoka's, Mijn Clubs, Nieuw Toernooi --}}
            <div class="flex space-x-3">
                @if(!$organisator->isSitebeheerder() || auth('organisator')->id() !== $organisator->id)
                {{-- Wimpel knop alleen tonen voor reguliere organisatoren, niet voor sitebeheerder op eigen "alle toernooien" dashboard --}}
                <a href="{{ route('organisator.wimpel.index', $organisator) }}"
                   class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                    {{ __('Wimpeltoernooi') }}
                </a>
                <a href="{{ route('organisator.stambestand.index', $organisator) }}"
                   class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                    {{ __('Mijn Judoka\'s') }}
                </a>
                <a href="{{ route('organisator.clubs.index', $organisator) }}"
                   class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition-colors">
                    {{ __('Mijn Clubs') }}
                </a>
                @endif
                <a href="{{ route('toernooi.create', ['organisator' => $organisator]) }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                    {{ __('Nieuw Toernooi') }}
                </a>
            </div>
        </div>

        {{-- Templates Section --}}
        <div x-data="templateManager()" class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-700">{{ __('Mijn Templates') }}</h3>
                <button @click="open = !open" class="text-blue-600 hover:text-blue-800 text-sm">
                    <span x-show="!open">{{ __('Toon templates') }}</span>
                    <span x-show="open">{{ __('Verberg') }}</span>
                </button>
            </div>

            <div x-show="open" x-collapse class="bg-white rounded-lg shadow p-4">
                <template x-if="templates.length === 0">
                    <p class="text-gray-500 text-sm">{{ __('Nog geen templates. Sla instellingen op vanuit een bestaand toernooi.') }}</p>
                </template>
                <template x-if="templates.length > 0">
                    <div class="space-y-3">
                        <template x-for="template in templates" :key="template.id">
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded border">
                                <div>
                                    <span class="font-medium" x-text="template.naam"></span>
                                    <span x-show="template.beschrijving" class="text-gray-500 text-sm ml-2" x-text="'- ' + template.beschrijving"></span>
                                    <span x-show="template.max_judokas" class="text-gray-400 text-xs ml-2" x-text="'(max ' + template.max_judokas + ' judokas)'"></span>
                                </div>
                                <button @click="deleteTemplate(template.id)" class="text-red-500 hover:text-red-700 text-sm">{{ __('Verwijderen') }}</button>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <script>
            function templateManager() {
                return {
                    open: false,
                    templates: @json($organisator->toernooiTemplates ?? []),
                    async deleteTemplate(id) {
                        if (!confirm('Weet je zeker dat je deze template wilt verwijderen?')) return;
                        const response = await fetch(`/{{ $organisator->slug }}/templates/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            }
                        });
                        if (response.ok) {
                            this.templates = this.templates.filter(t => t.id !== id);
                        }
                    }
                }
            }
        </script>

        @if($toernooien->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-600 mb-4">{{ __('Je hebt nog geen toernooien aangemaakt.') }}</p>
            <a href="{{ route('toernooi.create', ['organisator' => $organisator]) }}"
               class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors inline-block">
                {{ __('Maak je eerste toernooi aan') }}
            </a>
        </div>
        @else
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($toernooien as $toernooi)
            <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <h3 class="text-lg font-bold text-gray-800">{{ $toernooi->naam }}</h3>
                    @if($organisator->isSitebeheerder() && $toernooi->organisator_id !== $organisator->id)
                    <span class="text-xs px-2 py-1 rounded bg-purple-100 text-purple-800">
                        Admin ({{ $toernooi->organisator?->naam }})
                    </span>
                    @elseif($toernooi->pivot)
                    <span class="text-xs px-2 py-1 rounded
                        @if($toernooi->pivot->rol === 'eigenaar') bg-blue-100 text-blue-800
                        @else bg-gray-100 text-gray-800 @endif">
                        {{ ucfirst($toernooi->pivot->rol) }}
                    </span>
                    @endif
                </div>

                {{-- Toernooi datum --}}
                <div class="flex items-center text-gray-600 mb-2">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="font-medium">{{ $toernooi->datum ? $toernooi->datum->format('d-m-Y') : __('Geen datum') }}</span>
                </div>

                {{-- Plan Badge --}}
                <div class="mb-3">
                    @if($toernooi->isPaidTier())
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                            {{ __('Betaald') }} ({{ $toernooi->paid_tier }})
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                            {{ __('Gratis (max 50)') }}
                        </span>
                    @endif
                </div>

                {{-- Statistieken --}}
                <div class="grid grid-cols-2 gap-2 text-sm text-gray-500 mb-3">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $toernooi->judokas_count ?? $toernooi->judokas()->count() }}/{{ $toernooi->getEffectiveMaxJudokas() }} judoka's
                    </div>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        {{ $toernooi->poules_count ?? $toernooi->poules()->count() }} poules
                    </div>
                </div>

                {{-- Timestamps --}}
                <div class="text-xs text-gray-400 border-t pt-3 space-y-1">
                    <div class="flex justify-between">
                        <span>{{ __('Aangemaakt') }}:</span>
                        <span>{{ $toernooi->created_at ? $toernooi->created_at->format('d-m-Y H:i') : '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('Laatst bewerkt') }}:</span>
                        <span>{{ $toernooi->updated_at ? $toernooi->updated_at->diffForHumans() : '-' }}</span>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex items-center justify-between mt-4 pt-3 border-t">
                    <a href="{{ route('toernooi.show', $toernooi->routeParams()) }}"
                       class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-4 rounded transition-colors">
                        {{ __('Start') }}
                    </a>
                    <div class="flex items-center gap-2">
                        {{-- Reset knop --}}
                        <form action="{{ route('toernooi.reset', $toernooi->routeParams()) }}" method="POST" class="inline"
                              onsubmit="return confirm('Weet je zeker dat je \'{{ $toernooi->naam }}\' wilt resetten?\n\nDit verwijdert:\n- Alle judoka\'s\n- Alle poules\n- Alle wedstrijden\n- Alle wegingen\n\nDe toernooi naam en instellingen blijven behouden.')">
                            @csrf
                            <button type="submit" class="text-orange-400 hover:text-orange-600" title="{{ __('Reset toernooi (verwijder judoka\'s en poules)') }}">
                                🔄
                            </button>
                        </form>
                        {{-- Delete knop --}}
                        <form action="{{ route('toernooi.destroy', $toernooi->routeParams()) }}" method="POST" class="inline"
                              onsubmit="return confirm('Weet je zeker dat je \'{{ $toernooi->naam }}\' wilt verwijderen?\n\nDit verwijdert ALLE data:\n- Judoka\'s\n- Poules\n- Wedstrijden\n\nDit kan niet ongedaan worden!')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-600" title="{{ __('Verwijder toernooi') }}">
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </main>

    {{-- Idle Timeout - Auto logout after 20 minutes inactivity --}}
    <script>
        (function() {
            const IDLE_TIMEOUT = 20 * 60 * 1000; // 20 minutes in ms
            const WARNING_BEFORE = 2 * 60 * 1000; // Show warning 2 min before
            let idleTimer;
            let warningTimer;
            let warningShown = false;

            function resetTimers() {
                clearTimeout(idleTimer);
                clearTimeout(warningTimer);
                warningShown = false;

                const warning = document.getElementById('idle-warning');
                if (warning) warning.remove();

                warningTimer = setTimeout(showWarning, IDLE_TIMEOUT - WARNING_BEFORE);
                idleTimer = setTimeout(doLogout, IDLE_TIMEOUT);
            }

            function showWarning() {
                if (warningShown) return;
                warningShown = true;

                const warning = document.createElement('div');
                warning.id = 'idle-warning';
                warning.innerHTML = `
                    <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
                        <div style="background:white;padding:24px;border-radius:8px;max-width:400px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.3);">
                            <h3 style="font-size:18px;font-weight:bold;margin-bottom:12px;color:#b91c1c;">{{ __('Sessie verloopt bijna') }}</h3>
                            <p style="margin-bottom:16px;color:#374151;">{{ __('Je wordt over 2 minuten automatisch uitgelogd wegens inactiviteit.') }}</p>
                            <button onclick="document.getElementById('idle-warning').remove();resetIdleTimers();"
                                    style="background:#2563eb;color:white;padding:10px 24px;border-radius:6px;border:none;cursor:pointer;font-weight:500;">
                                {{ __('Actief blijven') }}
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(warning);
            }

            function doLogout() {
                const logoutForm = document.querySelector('form[action*="logout"]');
                if (logoutForm) {
                    logoutForm.submit();
                } else {
                    window.location.href = '{{ route("login") }}';
                }
            }

            window.resetIdleTimers = resetTimers;

            ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'].forEach(event => {
                document.addEventListener(event, resetTimers, { passive: true });
            });

            resetTimers();
        })();
    </script>
</body>
</html>
