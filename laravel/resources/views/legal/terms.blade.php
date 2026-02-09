<x-legal-layout>
    <div class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    Algemene Voorwaarden
                </h1>
                <p class="text-sm text-gray-600">
                    Laatst bijgewerkt: {{ date('d-m-Y') }}
                </p>
            </div>

            <!-- Content -->
            <div class="bg-white rounded-lg shadow-sm p-8 space-y-8">

                <!-- 1. Inleiding -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">1. Inleiding</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            Welkom bij JudoToernooi (judotournament.org). Deze algemene voorwaarden zijn van toepassing op het gebruik van ons platform.
                            Door gebruik te maken van JudoToernooi, gaat u akkoord met deze voorwaarden.
                        </p>
                        <p class="mt-3">
                            JudoToernooi is een SaaS-platform van Havun, waarmee judoscholen en organisatoren hun toernooien kunnen beheren.
                        </p>
                    </div>
                </section>

                <!-- 2. Diensten -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">2. Beschrijving van de Diensten</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">JudoToernooi biedt de volgende diensten aan organisatoren:</p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Toernooi aanmaken en configureren (categorieën, gewichtsklassen, blokken)</li>
                            <li>Deelnemers importeren en classificeren</li>
                            <li>Poule-indeling en wedstrijdschema's genereren</li>
                            <li>Weging en aanwezigheidsregistratie</li>
                            <li>Mat interface voor wedstrijdscoring</li>
                            <li>Eliminatie systeem (double elimination)</li>
                            <li>Coach portal voor deelnemende clubs</li>
                            <li>Inschrijfgeld verwerking via Mollie</li>
                            <li>Real-time updates en spreker interface</li>
                        </ul>
                    </div>
                </section>

                <!-- 3. Privacy & Gegevensbescherming -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">3. Privacy & Gegevensbescherming</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            <strong>Veilige opslag van persoonsgegevens:</strong><br>
                            Wij doen er alles aan om persoonsgegevens veilig te bewaren. Gegevens worden uitsluitend gebruikt voor
                            het leveren van onze diensten en worden nooit verkocht aan derden.
                        </p>
                        <p class="mb-3">
                            <strong>Gegevensverwerking:</strong><br>
                            Persoonsgegevens worden verwerkt conform de Algemene Verordening Gegevensbescherming (AVG/GDPR).
                            Voor meer details, zie onze <a href="{{ route('legal.privacy') }}" class="text-blue-600 hover:underline">Privacyverklaring</a>.
                        </p>
                        <p>
                            <strong>Uw rechten:</strong><br>
                            U heeft het recht om uw gegevens in te zien, te wijzigen of te verwijderen. Neem hiervoor contact met ons op
                            via de contactgegevens onderaan deze pagina.
                        </p>
                    </div>
                </section>

                <!-- 4. Betalingen -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">4. Betalingen & Inschrijfgeld</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            <strong>Platform betalingen:</strong><br>
                            Organisatoren kunnen een betaald abonnement afsluiten voor uitgebreide functies.
                            Betalingen worden verwerkt via Mollie (iDEAL, creditcard, Bancontact).
                        </p>
                        <p class="mb-3">
                            <strong>Inschrijfgeld:</strong><br>
                            Organisatoren kunnen via het platform inschrijfgeld innen bij deelnemende clubs.
                            Dit kan via de eigen Mollie-account van de organisator (Connect modus) of via het JudoToernooi platform (Platform modus).
                        </p>
                    </div>
                </section>

                <!-- 5. Verantwoordelijkheid organisator -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">5. Verantwoordelijkheid Organisator</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            De organisator is zelf verantwoordelijk voor:
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>De correctheid van ingevoerde deelnemersgegevens</li>
                            <li>Naleving van JBN-reglementen en eigen toernooiregels</li>
                            <li>De veiligheid van judoka's tijdens het toernooi</li>
                            <li>Het instellen van een lokale server als fallback</li>
                            <li>Het bijhouden van een papieren schaduwadministratie</li>
                        </ul>
                    </div>
                </section>

                <!-- 6. Aansprakelijkheid -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">6. Aansprakelijkheid</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            JudoToernooi (Havun) is niet aansprakelijk voor:
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Verlies van gegevens door technische storingen</li>
                            <li>Onbeschikbaarheid van de dienst door onderhoud of technische problemen</li>
                            <li>Fouten in poule-indelingen, uitslagen of classificaties</li>
                            <li>Schade ontstaan door internet- of serverproblemen tijdens toernooien</li>
                            <li>Gevolgen van onjuist ingevoerde deelnemersgegevens</li>
                        </ul>
                        <p class="mt-3">
                            Zie onze <a href="{{ route('legal.disclaimer') }}" class="text-blue-600 hover:underline">Disclaimer</a> voor volledige details.
                        </p>
                    </div>
                </section>

                <!-- 7. Intellectueel eigendom -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">7. Intellectueel Eigendom</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            Alle teksten, afbeeldingen, logo's, software en andere materialen van JudoToernooi zijn eigendom van Havun
                            en beschermd door auteursrecht. Gebruik zonder toestemming is niet toegestaan.
                        </p>
                    </div>
                </section>

                <!-- 8. Wijzigingen -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">8. Wijzigingen in Voorwaarden</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            Wij behouden ons het recht voor om deze algemene voorwaarden te wijzigen. Wijzigingen worden van kracht
                            na publicatie op deze pagina. Wij adviseren om regelmatig deze pagina te raadplegen.
                        </p>
                    </div>
                </section>

                <!-- 9. Toepasselijk recht -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">9. Toepasselijk Recht</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            Op deze algemene voorwaarden is Nederlands recht van toepassing. Geschillen zullen worden voorgelegd
                            aan de bevoegde rechter in Nederland.
                        </p>
                    </div>
                </section>

                <!-- Contact -->
                <section class="pt-6 border-t border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Contact</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            Voor vragen over deze algemene voorwaarden kunt u contact met ons opnemen via:<br>
                            <strong>E-mail:</strong> <a href="mailto:havun22@gmail.com" class="text-blue-600 hover:underline">havun22@gmail.com</a>
                        </p>
                    </div>
                </section>

            </div>

            <!-- Back button -->
            <div class="mt-8">
                <a href="{{ url()->previous() }}" class="text-blue-600 hover:text-blue-700">
                    ← Terug
                </a>
            </div>
        </div>
    </div>
</x-legal-layout>
