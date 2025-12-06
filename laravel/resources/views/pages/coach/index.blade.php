<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $club->naam }} - {{ $toernooi->naam }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center py-12 px-4">
        <div class="max-w-md w-full">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center mb-8">
                    <h1 class="text-2xl font-bold text-gray-800">{{ $toernooi->naam }}</h1>
                    <p class="text-gray-600 mt-2">{{ $toernooi->datum->format('d F Y') }}</p>
                    <p class="text-lg font-medium text-blue-600 mt-4">{{ $club->naam }}</p>
                </div>

                @if(session('error'))
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
                    {{ session('error') }}
                </div>
                @endif

                @if(session('success'))
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
                    {{ session('success') }}
                </div>
                @endif

                @if(!$toernooi->isInschrijvingOpen())
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded mb-6">
                    <strong>Let op:</strong> De inschrijving is gesloten sinds {{ $toernooi->inschrijving_deadline->format('d-m-Y') }}.
                    Je kunt nog wel je judoka's bekijken.
                </div>
                @endif

                @if($isGeregistreerd)
                <!-- Login form -->
                <h2 class="text-xl font-bold text-gray-800 mb-4">Inloggen</h2>
                <form action="{{ route('coach.login', $uitnodiging->token) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="wachtwoord" class="block text-gray-700 font-medium mb-2">Wachtwoord</label>
                        <input type="password" name="wachtwoord" id="wachtwoord" required
                               class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg">
                        Inloggen
                    </button>
                </form>
                @else
                <!-- Registration form -->
                <h2 class="text-xl font-bold text-gray-800 mb-4">Account Aanmaken</h2>
                <p class="text-gray-600 mb-6">Maak een wachtwoord aan om toegang te krijgen tot het inschrijfformulier.</p>
                <form action="{{ route('coach.registreer', $uitnodiging->token) }}" method="POST">
                    @csrf
                    <div class="mb-4">
                        <label for="wachtwoord" class="block text-gray-700 font-medium mb-2">Wachtwoord</label>
                        <input type="password" name="wachtwoord" id="wachtwoord" required minlength="6"
                               class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500
                               @error('wachtwoord') border-red-500 @enderror">
                        @error('wachtwoord')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="mb-6">
                        <label for="wachtwoord_confirmation" class="block text-gray-700 font-medium mb-2">Bevestig Wachtwoord</label>
                        <input type="password" name="wachtwoord_confirmation" id="wachtwoord_confirmation" required
                               class="w-full border rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg">
                        Account Aanmaken
                    </button>
                </form>
                @endif

                <div class="mt-8 pt-6 border-t text-center text-sm text-gray-500">
                    @if($toernooi->inschrijving_deadline)
                    <p>Inschrijving mogelijk tot: <strong>{{ $toernooi->inschrijving_deadline->format('d-m-Y') }}</strong></p>
                    @endif
                    @if($toernooi->max_judokas)
                    <p>Max deelnemers: <strong>{{ $toernooi->max_judokas }}</strong></p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
