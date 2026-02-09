<x-legal-layout>
    <div class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    Privacyverklaring
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
                            JudoToernooi (Havun) respecteert uw privacy en doet er alles aan om persoonsgegevens te beschermen.
                            Deze privacyverklaring legt uit welke gegevens wij verzamelen, hoe we deze gebruiken en welke rechten u heeft.
                        </p>
                        <p class="mt-3">
                            Deze privacyverklaring is opgesteld conform de Algemene Verordening Gegevensbescherming (AVG/GDPR).
                        </p>
                    </div>
                </section>

                <!-- 2. Welke gegevens -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">2. Welke Gegevens Verzamelen Wij</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3"><strong>Organisator gegevens:</strong></p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Naam, e-mailadres en organisatienaam (bij registratie)</li>
                            <li>KvK-nummer en BTW-nummer (bij KYC-verificatie)</li>
                            <li>Wachtwoord (versleuteld opgeslagen)</li>
                        </ul>

                        <p class="mt-4 mb-3"><strong>Deelnemersgegevens (judoka's):</strong></p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Naam en geboortejaar</li>
                            <li>Geslacht en bandkleur</li>
                            <li>Gewicht (bij weging)</li>
                            <li>Clubnaam</li>
                        </ul>

                        <p class="mt-4 mb-3"><strong>Technische gegevens:</strong></p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>IP-adres (voor beveiliging)</li>
                            <li>Browser type en apparaat informatie</li>
                            <li>Sessie gegevens en cookies</li>
                        </ul>

                        <p class="mt-4 mb-3"><strong>Betaalgegevens:</strong></p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Transactie ID's en betaalstatus</li>
                            <li>Geen creditcard gegevens (verwerkt door Mollie)</li>
                        </ul>
                    </div>
                </section>

                <!-- 3. Hoe gebruiken wij uw gegevens -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">3. Hoe Gebruiken Wij Uw Gegevens</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3"><strong>Doeleinden:</strong></p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Het leveren van onze diensten (toernooi management, poule-indeling, scoring)</li>
                            <li>Accountbeheer en authenticatie</li>
                            <li>Verwerking van betalingen (platform abonnementen en inschrijfgeld)</li>
                            <li>Technisch onderhoud en beveiliging</li>
                            <li>Naleving van wettelijke verplichtingen</li>
                        </ul>

                        <p class="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <strong>Garantie:</strong><br>
                            Wij gebruiken persoonsgegevens <strong>uitsluitend</strong> voor het leveren van onze diensten.
                            Gegevens worden <strong>nooit verkocht, verhuurd of gedeeld met derden voor commerciële doeleinden</strong>.
                        </p>
                    </div>
                </section>

                <!-- 4. Gegevensdeling -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">4. Gegevensdeling met Derden</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">
                            Wij delen gegevens alleen met de volgende partijen, uitsluitend voor het leveren van onze diensten:
                        </p>

                        <p class="mb-2"><strong>Betaalverwerkers:</strong></p>
                        <ul class="list-disc pl-6 space-y-1 mb-4">
                            <li><strong>Mollie:</strong> Voor verwerking van iDEAL, creditcard en Bancontact betalingen</li>
                        </ul>

                        <p class="mb-2"><strong>Hosting:</strong></p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>Hetzner (server hosting, datacenter in EU)</li>
                        </ul>
                    </div>
                </section>

                <!-- 5. Beveiliging -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">5. Beveiliging van Uw Gegevens</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3"><strong>Beveiligingsmaatregelen:</strong></p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li>SSL/TLS versleuteling voor alle datatransmissie</li>
                            <li>Versleutelde opslag van wachtwoorden (bcrypt hashing)</li>
                            <li>Regelmatige security updates</li>
                            <li>Automatische sessie time-outs bij inactiviteit</li>
                            <li>Device binding voor vrijwilligers-interfaces</li>
                            <li>Rate limiting op login en API endpoints</li>
                        </ul>

                        <p class="mt-4">
                            <strong>Datalek Protocol:</strong><br>
                            In het geval van een datalek melden wij dit binnen 72 uur bij de Autoriteit Persoonsgegevens
                            en informeren wij getroffen gebruikers conform AVG wetgeving.
                        </p>
                    </div>
                </section>

                <!-- 6. Cookies -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">6. Cookies</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            Voor informatie over cookies verwijzen wij naar ons
                            <a href="{{ route('legal.cookies') }}" class="text-blue-600 hover:underline">Cookiebeleid</a>.
                        </p>
                    </div>
                </section>

                <!-- 7. Bewaartermijnen -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">7. Bewaartermijnen</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3"><strong>Gegevens worden bewaard:</strong></p>
                        <ul class="list-disc pl-6 space-y-1">
                            <li><strong>Organisator gegevens:</strong> Zolang het account actief is</li>
                            <li><strong>Toernooi gegevens:</strong> Zolang de organisator het toernooi bewaart</li>
                            <li><strong>Deelnemersgegevens:</strong> Gekoppeld aan het toernooi</li>
                            <li><strong>Betaalgegevens:</strong> 7 jaar (wettelijke verplichting)</li>
                            <li><strong>Log files:</strong> Maximum 90 dagen</li>
                        </ul>
                    </div>
                </section>

                <!-- 8. Uw rechten -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">8. Uw Rechten onder de AVG</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p class="mb-3">U heeft de volgende rechten:</p>
                        <ul class="list-disc pl-6 space-y-2">
                            <li><strong>Recht op inzage:</strong> U kunt opvragen welke gegevens wij van u hebben</li>
                            <li><strong>Recht op rectificatie:</strong> U kunt onjuiste gegevens laten corrigeren</li>
                            <li><strong>Recht op verwijdering:</strong> U kunt verzoeken om verwijdering van uw gegevens</li>
                            <li><strong>Recht op dataportabiliteit:</strong> U kunt uw gegevens in machine-leesbaar formaat opvragen</li>
                            <li><strong>Recht op bezwaar:</strong> U kunt bezwaar maken tegen gegevensverwerking</li>
                        </ul>

                        <p class="mt-4">
                            <strong>Uitoefenen van uw rechten:</strong><br>
                            Neem contact op via <a href="mailto:havun22@gmail.com" class="text-blue-600 hover:underline">havun22@gmail.com</a>.
                            Wij reageren binnen 30 dagen.
                        </p>

                        <p class="mt-3">
                            <strong>Klacht indienen:</strong><br>
                            U kunt een klacht indienen bij de <a href="https://autoriteitpersoonsgegevens.nl" target="_blank" rel="noopener" class="text-blue-600 hover:underline">Autoriteit Persoonsgegevens</a>.
                        </p>
                    </div>
                </section>

                <!-- 9. Wijzigingen -->
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">9. Wijzigingen in deze Privacyverklaring</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            Wij kunnen deze privacyverklaring aanpassen. De meest recente versie is altijd te vinden op deze pagina.
                        </p>
                    </div>
                </section>

                <!-- Contact -->
                <section class="pt-6 border-t border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Contact</h2>
                    <div class="prose prose-sm max-w-none text-gray-700">
                        <p>
                            <strong>JudoToernooi (Havun)</strong><br>
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
