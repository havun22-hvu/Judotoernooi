<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Registreren') }} - {{ __('JudoToernooi') }}</title>
    @vite(["resources/css/app.css", "resources/js/app.js"])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ __('JudoToernooi') }}</h1>
            <p class="text-gray-600">{{ __('Organisator Registratie') }}</p>
        </div>

        @if($errors->any())
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <p class="text-gray-600 mb-6 text-sm">
            {{ __('Vul je gegevens in. We sturen een activatielink naar je e-mailadres.') }}
        </p>

        <form action="{{ route('register.submit') }}" method="POST" id="registerForm">
            @csrf

            <div class="mb-4">
                <label for="organisatie_naam" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Naam judoschool / organisatie') }}
                </label>
                <input type="text"
                       id="organisatie_naam"
                       name="organisatie_naam"
                       value="{{ old('organisatie_naam') }}"
                       required
                       autofocus
                       placeholder="{{ __('Naam van uw judoschool') }}"
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500 @error('organisatie_naam') border-red-500 @enderror">
                @error('organisatie_naam')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="naam" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Uw naam') }} <span class="text-gray-400">({{ __('contactpersoon') }})</span>
                </label>
                <input type="text"
                       id="naam"
                       name="naam"
                       value="{{ old('naam') }}"
                       required
                       placeholder="{{ __('bijv. Jan Jansen') }}"
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500 @error('naam') border-red-500 @enderror">
                @error('naam')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('E-mailadres') }}
                </label>
                <input type="email"
                       id="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500 @error('email') border-red-500 @enderror">
                @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="telefoon" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ __('Telefoonnummer') }} <span class="text-gray-400">({{ __('optioneel') }})</span>
                </label>
                <input type="tel"
                       id="telefoon"
                       name="telefoon"
                       value="{{ old('telefoon') }}"
                       class="w-full border-2 border-gray-300 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-blue-500">
            </div>

            {{-- DO NOT REMOVE: Registration submit button --}}
            <button type="submit" id="submitBtn"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                {{ __('Registratielink versturen') }}
            </button>

            <p class="text-xs text-gray-500 text-center mt-3">
                {{ __('We sturen een activatielink naar je e-mailadres. Geen wachtwoord nodig.') }}
            </p>
        </form>

        {{-- DO NOT REMOVE: Login link for existing users --}}
        <div class="mt-6 text-center">
            <p class="text-gray-600">
                {{ __('Al een account?') }}
                <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-800 font-medium">
                    {{ __('Log hier in') }}
                </a>
            </p>
        </div>
    </div>

    <script>
    document.getElementById('registerForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = @json(__('Bezig met versturen...'));
        btn.classList.add('opacity-75', 'cursor-not-allowed');
    });
    </script>
</body>
</html>
