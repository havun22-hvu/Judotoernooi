<x-legal-layout>
    <div class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    Disclaimer
                </h1>
                <p class="text-sm text-gray-600">
                    Laatst bijgewerkt: {{ date('d-m-Y') }}
                </p>
            </div>

            <!-- Content -->
            <div class="bg-white rounded-lg shadow-sm p-8 space-y-8">

                <!-- 1. Algemeen -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">1. Algemene Disclaimer</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            JudoToernooi wordt aangeboden "zoals het is" (as is), zonder enige garantie van volledigheid,
                            juistheid, beschikbaarheid of geschiktheid voor een bepaald doel. Gebruik van de diensten is geheel op eigen risico.
                        </p>
                    </div>
                </section>

                <!-- 2. Platform beschikbaarheid -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">2. Platform Beschikbaarheid</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            <strong>Geen uptime-garantie:</strong><br>
                            JudoToernooi wordt zo betrouwbaar mogelijk aangeboden, maar er wordt <strong>geen enkele garantie</strong>
                            gegeven op beschikbaarheid of uptime. Wij behouden ons het recht voor om de dienst op elk moment te wijzigen,
                            onderbreken of staken zonder voorafgaande kennisgeving.
                        </p>
                        <p class="mb-3">
                            <strong>Redenen voor onderbreking kunnen zijn:</strong>
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Technisch onderhoud en updates</li>
                            <li>Serveruitval of technische problemen</li>
                            <li>Internetproblemen (provider, DNS, routing)</li>
                            <li>DDoS-aanvallen of veiligheidsincidenten</li>
                            <li>Overmacht (stroomuitval, natuurrampen)</li>
                        </ul>
                    </div>
                </section>

                <!-- 3. KRITIEK: Internet/server problemen tijdens toernooien -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">3. Internet- en Serverproblemen Tijdens Toernooien</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <div class="p-4 bg-red-50 border border-red-200 rounded-lg mb-4">
                            <strong>BELANGRIJK:</strong><br>
                            Havun is <strong>NIET aansprakelijk</strong> voor enige schade, vertraging of verstoring van uw toernooi
                            als gevolg van internet-, server- of softwareproblemen. Dit omvat, maar is niet beperkt tot:
                            <ul class="list-disc pl-6 space-y-1 mt-2">
                                <li>Uitval van de internetverbinding op de toernooilocatie</li>
                                <li>Serveruitval of trage responstijden</li>
                                <li>Softwarefouten of onverwacht gedrag</li>
                                <li>Dataverlies door technische storingen</li>
                                <li>Problemen met real-time synchronisatie tussen devices</li>
                            </ul>
                        </div>

                        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg mb-4">
                            <strong>AANBEVELING - Lokale Server:</strong><br>
                            Wij raden <strong>dringend</strong> aan om voor elk toernooi een <strong>lokale server op het lokaal netwerk</strong>
                            te installeren als hot standby. Dit zorgt ervoor dat het toernooi kan doorgaan bij uitval van de internetverbinding.
                            <br><br>
                            Zie de documentatie over de <strong>Lokale Server Handleiding</strong> in het platform voor installatie-instructies.
                        </div>

                        <div class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                            <strong>AANBEVELING - Schaduwadministratie op Papier:</strong><br>
                            Houd <strong>altijd</strong> een papieren schaduwadministratie bij als ultieme fallback. Print vóór aanvang van het toernooi
                            de volgende documenten uit via het <strong>Noodplan</strong> in het platform:
                            <ul class="list-disc pl-6 space-y-1 mt-2">
                                <li>Poule-indelingen per blok</li>
                                <li>Weeglijsten</li>
                                <li>Wedstrijdschema's (lege formulieren)</li>
                                <li>Zaaloverzicht (mat-verdeling)</li>
                                <li>Contactlijst (coaches, juryleden)</li>
                            </ul>
                            <p class="mt-2">
                                Het Noodplan bevat ook een <strong>offline pakket</strong> dat u kunt downloaden als standalone HTML-bestand.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- 4. Data en backups -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">4. Dataverlies & Back-ups</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            <strong>Risico van dataverlies:</strong><br>
                            Hoewel wij dagelijkse backups maken, kunnen wij niet garanderen dat gegevens altijd bewaard blijven.
                        </p>
                        <p class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <strong>Advies:</strong><br>
                            Exporteer belangrijke toernooigegevens (uitslagen, deelnemerslijsten) regelmatig via de exportfuncties in het platform.
                        </p>
                        <p class="mt-3">
                            JudoToernooi is <strong>niet aansprakelijk</strong> voor dataverlies, ongeacht de oorzaak.
                        </p>
                    </div>
                </section>

                <!-- 5. Uitslagen en classificaties -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">5. Uitslagen, Classificaties & Poule-indelingen</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            Het platform berekent automatisch classificaties (leeftijdscategorieën, gewichtsklassen),
                            poule-indelingen en wedstrijdschema's. Hoewel wij streven naar correctheid:
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>De organisator is verantwoordelijk voor controle van alle automatische berekeningen</li>
                            <li>De organisator is verantwoordelijk voor naleving van JBN-reglementen</li>
                            <li>Havun is niet aansprakelijk voor onjuiste classificaties of indelingen</li>
                            <li>Handmatige aanpassingen door de organisator zijn altijd mogelijk en aanbevolen</li>
                        </ul>
                    </div>
                </section>

                <!-- 6. Betalingen -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">6. Betalingen</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            Betalingen worden verwerkt via Mollie. Havun is niet verantwoordelijk voor:
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Storingen bij de betaalprovider (Mollie)</li>
                            <li>Mislukte of vertraagde betalingen</li>
                            <li>Geschillen tussen organisator en deelnemende clubs over inschrijfgeld</li>
                        </ul>
                    </div>
                </section>

                <!-- 7. Beperking aansprakelijkheid -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">7. Beperking van Aansprakelijkheid</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            JudoToernooi (Havun) is <strong>niet aansprakelijk</strong> voor:
                        </p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Directe of indirecte schade door gebruik van de dienst</li>
                            <li>Verlies van gegevens of toernooiresultaten</li>
                            <li>Onderbreking van het toernooi door technische problemen</li>
                            <li>Schade door internet-, server- of softwareproblemen</li>
                            <li>Fouten in automatische berekeningen</li>
                            <li>Ongeautoriseerde toegang tot accounts</li>
                        </ul>
                        <p class="mt-3">
                            De maximale aansprakelijkheid is in alle gevallen beperkt tot het bedrag dat u heeft betaald voor de dienst
                            in de afgelopen 12 maanden.
                        </p>
                    </div>
                </section>

                <!-- 8. Wijzigingen -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">8. Wijzigingen in deze Disclaimer</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            Wij behouden ons het recht voor om deze disclaimer op elk moment te wijzigen. De meest recente versie
                            is altijd te vinden op deze pagina.
                        </p>
                    </div>
                </section>

                <!-- Contact -->
                <section class="pt-6 border-t border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Contact</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            Voor vragen over deze disclaimer:<br>
                            <strong>E-mail:</strong> <a href="mailto:havun22@gmail.com" class="text-blue-600 hover:underline">havun22@gmail.com</a>
                        </p>
                    </div>
                </section>

            </div>

            <!-- Related links -->
            <div class="mt-8 p-4 bg-gray-100 rounded-lg">
                <p class="text-sm text-gray-700 mb-2">Gerelateerde documenten:</p>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('legal.terms') }}" class="text-blue-600 hover:underline text-sm">→ Algemene Voorwaarden</a>
                    <a href="{{ route('legal.privacy') }}" class="text-blue-600 hover:underline text-sm">→ Privacyverklaring</a>
                </div>
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
