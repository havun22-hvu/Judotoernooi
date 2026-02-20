<x-legal-layout>
    <div class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    {{ __('Algemene Voorwaarden') }}
                </h1>
                <p class="text-sm text-gray-600">
                    {{ __('Laatst bijgewerkt') }}: {{ date('d-m-Y') }}
                </p>
            </div>

            <!-- Content -->
            <div class="bg-white rounded-lg shadow-sm p-8 space-y-8">

                <!-- 1. Inleiding -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('1. Inleiding') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            {{ __('Welkom bij JudoToernooi (judotournament.org). Deze algemene voorwaarden zijn van toepassing op het gebruik van ons platform. Door gebruik te maken van JudoToernooi, gaat u akkoord met deze voorwaarden.') }}
                        </p>
                        <p class="mt-3">
                            {{ __('JudoToernooi is een SaaS-platform van Havun, waarmee judoscholen en organisatoren hun toernooien kunnen beheren.') }}
                        </p>
                    </div>
                </section>

                <!-- 2. Diensten -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('2. Beschrijving van de Diensten') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">{{ __('JudoToernooi biedt de volgende diensten aan organisatoren:') }}</p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>{{ __('Toernooi aanmaken en configureren (categorieën, gewichtsklassen, blokken)') }}</li>
                            <li>{{ __('Deelnemers importeren en classificeren') }}</li>
                            <li>{{ __('Poule-indeling en wedstrijdschema\'s genereren') }}</li>
                            <li>{{ __('Weging en aanwezigheidsregistratie') }}</li>
                            <li>{{ __('Mat interface voor wedstrijdscoring') }}</li>
                            <li>{{ __('Eliminatie systeem (double elimination)') }}</li>
                            <li>{{ __('Coach portal voor deelnemende clubs') }}</li>
                            <li>{{ __('Inschrijfgeld verwerking via Mollie') }}</li>
                            <li>{{ __('Real-time updates en spreker interface') }}</li>
                        </ul>
                    </div>
                </section>

                <!-- 3. Privacy & Gegevensbescherming -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('3. Privacy & Gegevensbescherming') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            <strong>{{ __('Veilige opslag van persoonsgegevens:') }}</strong><br>
                            {{ __('Wij doen er alles aan om persoonsgegevens veilig te bewaren. Gegevens worden uitsluitend gebruikt voor het leveren van onze diensten en worden nooit verkocht aan derden.') }}
                        </p>
                        <p class="mb-3">
                            <strong>{{ __('Gegevensverwerking:') }}</strong><br>
                            {!! __('Persoonsgegevens worden verwerkt conform de Algemene Verordening Gegevensbescherming (AVG/GDPR). Voor meer details, zie onze <a href=":url" class="text-blue-600 hover:underline">Privacyverklaring</a>.', ['url' => route('legal.privacy')]) !!}
                        </p>
                        <p>
                            <strong>{{ __('Uw rechten:') }}</strong><br>
                            {{ __('U heeft het recht om uw gegevens in te zien, te wijzigen of te verwijderen. Neem hiervoor contact met ons op via de contactgegevens onderaan deze pagina.') }}
                        </p>
                    </div>
                </section>

                <!-- 4. Betalingen -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('4. Betalingen & Inschrijfgeld') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            <strong>{{ __('Platform betalingen:') }}</strong><br>
                            {{ __('Organisatoren kunnen een betaald abonnement afsluiten voor uitgebreide functies. Betalingen worden verwerkt via Mollie (iDEAL, creditcard, Bancontact).') }}
                        </p>
                        <p class="mb-3">
                            <strong>{{ __('Inschrijfgeld:') }}</strong><br>
                            {{ __('Organisatoren kunnen via het platform inschrijfgeld innen bij deelnemende clubs. Dit kan via de eigen Mollie-account van de organisator (Connect modus) of via het JudoToernooi platform (Platform modus).') }}
                        </p>
                    </div>
                </section>

                <!-- 5. Verantwoordelijkheid organisator -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('5. Verantwoordelijkheid Organisator') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            {{ __('De organisator is zelf verantwoordelijk voor:') }}
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>{{ __('De correctheid van ingevoerde deelnemersgegevens') }}</li>
                            <li>{{ __('Naleving van JBN-reglementen en eigen toernooiregels') }}</li>
                            <li>{{ __('De veiligheid van judoka\'s tijdens het toernooi') }}</li>
                            <li>{{ __('Het instellen van een lokale server als fallback') }}</li>
                            <li>{{ __('Het bijhouden van een papieren schaduwadministratie') }}</li>
                        </ul>
                    </div>
                </section>

                <!-- 6. Aansprakelijkheid -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('6. Aansprakelijkheid') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            {{ __('JudoToernooi (Havun) is niet aansprakelijk voor:') }}
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>{{ __('Verlies van gegevens door technische storingen') }}</li>
                            <li>{{ __('Onbeschikbaarheid van de dienst door onderhoud of technische problemen') }}</li>
                            <li>{{ __('Fouten in poule-indelingen, uitslagen of classificaties') }}</li>
                            <li>{{ __('Schade ontstaan door internet- of serverproblemen tijdens toernooien') }}</li>
                            <li>{{ __('Gevolgen van onjuist ingevoerde deelnemersgegevens') }}</li>
                        </ul>
                        <p class="mt-3">
                            {!! __('Zie onze <a href=":url" class="text-blue-600 hover:underline">Disclaimer</a> voor volledige details.', ['url' => route('legal.disclaimer')]) !!}
                        </p>
                    </div>
                </section>

                <!-- 7. Intellectueel eigendom -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('7. Intellectueel Eigendom') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            {{ __('Alle teksten, afbeeldingen, logo\'s, software en andere materialen van JudoToernooi zijn eigendom van Havun en beschermd door auteursrecht. Gebruik zonder toestemming is niet toegestaan.') }}
                        </p>
                    </div>
                </section>

                <!-- 8. Wijzigingen -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('8. Wijzigingen in Voorwaarden') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            {{ __('Wij behouden ons het recht voor om deze algemene voorwaarden te wijzigen. Wijzigingen worden van kracht na publicatie op deze pagina. Wij adviseren om regelmatig deze pagina te raadplegen.') }}
                        </p>
                    </div>
                </section>

                <!-- 9. Toepasselijk recht -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('9. Toepasselijk Recht') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            {{ __('Op deze algemene voorwaarden is Nederlands recht van toepassing. Geschillen zullen worden voorgelegd aan de bevoegde rechter in Nederland.') }}
                        </p>
                    </div>
                </section>

                <!-- Contact -->
                <section class="pt-6 border-t border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('Contact') }}</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            {{ __('Voor vragen over deze algemene voorwaarden kunt u contact met ons opnemen via:') }}<br>
                            <strong>{{ __('E-mail') }}:</strong> <a href="mailto:havun22@gmail.com" class="text-blue-600 hover:underline">havun22@gmail.com</a>
                        </p>
                    </div>
                </section>

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
