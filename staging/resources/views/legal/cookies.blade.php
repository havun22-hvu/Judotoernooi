<x-legal-layout>
    <div class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    {{ __('Cookiebeleid') }}
                </h1>
                <p class="text-sm text-gray-600">
                    {{ __('Laatst bijgewerkt') }}: {{ date('d-m-Y') }}
                </p>
            </div>

            <!-- Content -->
            <div class="bg-white rounded-lg shadow-sm p-8 space-y-8">

                <!-- 1. Wat zijn cookies -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('1. Wat Zijn Cookies') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            {{ __('Cookies zijn kleine tekstbestanden die op uw computer of mobiele apparaat worden opgeslagen wanneer u een website bezoekt. Ze helpen websites om gebruikersvoorkeuren te onthouden en de functionaliteit te verbeteren.') }}
                        </p>
                    </div>
                </section>

                <!-- 2. Welke cookies -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('2. Welke Cookies Gebruikt JudoToernooi') }}</h2>

                    <!-- Functionele cookies -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">{{ __('Functionele Cookies (Noodzakelijk)') }}</h3>
                        <div class="prose prose-sm max-w-none text-gray-700">
                            <p class="mb-3">{{ __('Deze cookies zijn essentieel voor het functioneren van de website en kunnen niet worden uitgeschakeld.') }}</p>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Cookie') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Doel') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Bewaartermijn') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-mono">laravel_session</td>
                                            <td class="px-4 py-2 text-sm">{{ __('Sessie beheer & authenticatie') }}</td>
                                            <td class="px-4 py-2 text-sm">{{ __('2 uur') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-mono">XSRF-TOKEN</td>
                                            <td class="px-4 py-2 text-sm">{{ __('Beveiliging tegen CSRF aanvallen') }}</td>
                                            <td class="px-4 py-2 text-sm">{{ __('2 uur') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-mono">remember_web_*</td>
                                            <td class="px-4 py-2 text-sm">{{ __('"Onthoud mij" functionaliteit') }}</td>
                                            <td class="px-4 py-2 text-sm">{{ __('5 jaar (indien gekozen)') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Voorkeur cookies -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">{{ __('Voorkeur Cookies') }}</h3>
                        <div class="prose prose-sm max-w-none text-gray-700">
                            <p class="mb-3">{{ __('Deze cookies onthouden uw voorkeuren voor een betere gebruikservaring.') }}</p>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead>
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Opslag') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Doel') }}</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('Bewaartermijn') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-mono">locale</td>
                                            <td class="px-4 py-2 text-sm">{{ __('Taalvoorkeur (NL/EN)') }}</td>
                                            <td class="px-4 py-2 text-sm">{{ __('Sessie') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="px-4 py-2 text-sm font-mono">device_token_*</td>
                                            <td class="px-4 py-2 text-sm">{{ __('Device binding voor interfaces') }}</td>
                                            <td class="px-4 py-2 text-sm">{{ __('Sessie') }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy-vriendelijk -->
                    <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                        <p class="text-sm text-green-900">
                            <strong>{{ __('Privacy-vriendelijk:') }}</strong><br>
                            {!! __('JudoToernooi gebruikt <strong>GEEN</strong> tracking cookies van derden zoals Google Analytics, Facebook Pixel of andere advertentie-netwerken. Uw surfgedrag wordt niet gedeeld met adverteerders.') !!}
                        </p>
                    </div>
                </section>

                <!-- 3. Cookies beheren -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('3. Cookies Beheren') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            <strong>{{ __('Functionele cookies:') }}</strong><br>
                            {{ __('Deze kunnen niet worden uitgeschakeld omdat ze essentieel zijn voor het functioneren van de website.') }}
                        </p>
                        <p class="mb-3">
                            <strong>{{ __('Cookies verwijderen:') }}</strong><br>
                            {{ __('U kunt cookies verwijderen via uw browser instellingen:') }}
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li><strong>Chrome:</strong> {{ __('Instellingen → Privacy en beveiliging → Cookies') }}</li>
                            <li><strong>Firefox:</strong> {{ __('Opties → Privacy & Beveiliging → Cookies') }}</li>
                            <li><strong>Safari:</strong> {{ __('Voorkeuren → Privacy → Website-gegevens') }}</li>
                            <li><strong>Edge:</strong> {{ __('Instellingen → Cookies en sitemachtigingen') }}</li>
                        </ul>
                        <p class="mt-3">
                            <strong>{{ __('Let op:') }}</strong> {{ __('Het uitschakelen van functionele cookies kan de werking van de website beperken.') }}
                        </p>
                    </div>
                </section>

                <!-- 4. Third-party -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('4. Third-Party Cookies') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            {{ __('JudoToernooi gebruikt minimale third-party services:') }}
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li><strong>Mollie:</strong> {{ __('Betaalverwerking (cookies alleen op checkout pagina)') }}</li>
                            <li><strong>Fonts.bunny.net:</strong> {{ __('Privacy-vriendelijke font hosting (geen tracking)') }}</li>
                        </ul>
                    </div>
                </section>

                <!-- 5. Updates -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('5. Updates van dit Cookiebeleid') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            {{ __('Wij kunnen dit cookiebeleid bijwerken. De meest recente versie is altijd te vinden op deze pagina.') }}
                        </p>
                    </div>
                </section>

                <!-- Contact -->
                <section class="pt-6 border-t border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Vragen') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            {{ __('Voor vragen over ons cookiebeleid:') }}<br>
                            <strong>{{ __('E-mail') }}:</strong> <a href="mailto:havun22@gmail.com" class="text-blue-600 hover:underline">havun22@gmail.com</a>
                        </p>
                    </div>
                </section>

            </div>

            <!-- Related links -->
            <div class="mt-8 p-4 bg-gray-100 rounded-lg">
                <p class="text-sm text-gray-700 mb-2">{{ __('Gerelateerde documenten:') }}</p>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('legal.privacy') }}" class="text-blue-600 hover:underline text-sm">→ {{ __('Privacyverklaring') }}</a>
                    <a href="{{ route('legal.terms') }}" class="text-blue-600 hover:underline text-sm">→ {{ __('Algemene Voorwaarden') }}</a>
                </div>
            </div>

            <!-- Back button -->
            <div class="mt-8">
                <a href="{{ url()->previous() }}" class="text-blue-600 hover:text-blue-700">
                    ← {{ __('Terug') }}
                </a>
            </div>
        </div>
    </div>
</x-legal-layout>
