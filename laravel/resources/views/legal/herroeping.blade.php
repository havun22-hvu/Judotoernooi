<x-legal-layout>
    <div class="py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('Herroepingsformulier') }}</h1>
                <p class="text-sm text-gray-600">{{ __('Modelformulier voor herroeping (EU)') }}</p>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-amber-800">
                    <strong>{{ __('Let op:') }}</strong>
                    {{ __('Inschrijfgeld voor toernooien kan niet worden teruggevorderd via herroeping, omdat u bij het afrekenen uitdrukkelijk afstand heeft gedaan van dit recht (directe verwerking van de inschrijving).') }}
                    {{ __('Dit formulier is uitsluitend van toepassing op platform abonnementen die nog niet zijn gebruikt.') }}
                </p>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-8 space-y-4 text-gray-700">
                <p><strong>{{ __('Aan:') }}</strong> Havun — JudoToernooi<br>
                {{ __('E-mail:') }} <a href="mailto:havun22@gmail.com" class="text-blue-600 hover:underline">havun22@gmail.com</a></p>

                <p>{{ __('Ik deel u hierbij mede dat ik mijn overeenkomst betreffende de levering van de volgende dienst herroep:') }}</p>

                <div class="border border-gray-200 rounded-lg p-4 space-y-3 bg-gray-50">
                    <p><strong>{{ __('Dienst:') }}</strong> {{ __('JudoToernooi platform abonnement') }}</p>
                    <p><strong>{{ __('Besteld op / betaald op:') }}</strong> _______________</p>
                    <p><strong>{{ __('Naam:') }}</strong> _______________</p>
                    <p><strong>{{ __('E-mailadres waarmee besteld:') }}</strong> _______________</p>
                    <p><strong>{{ __('Datum:') }}</strong> _______________</p>
                </div>

                <p class="text-sm text-gray-500">
                    {{ __('Stuur dit formulier per e-mail naar') }}
                    <a href="mailto:havun22@gmail.com" class="text-blue-600 hover:underline">havun22@gmail.com</a>
                    {{ __('binnen 14 dagen na aankoop. Wij bevestigen ontvangst en verwerken de terugbetaling binnen 14 dagen.') }}
                </p>
            </div>

            <div class="mt-8">
                <a href="{{ url()->previous() }}" class="text-blue-600 hover:text-blue-700">
                    ← {{ __('Terug') }}
                </a>
            </div>
        </div>
    </div>
</x-legal-layout>
